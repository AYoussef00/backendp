<?php

namespace App\Http\Controllers\Web;

use App\Enums\AgentCommand;
use App\Enums\ServerJobStatus;
use App\Http\Controllers\Controller;
use App\Models\ServerJob;
use App\Models\Website;
use App\Services\AuditLogger;
use App\Services\JobService;
use App\Services\WebsiteControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class WebsiteController extends Controller
{
    public function __construct(
        private readonly JobService $jobService,
        private readonly AuditLogger $auditLogger,
        private readonly WebsiteControlService $websiteControl,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Website::class);

        $websites = Website::query()
            ->whereHas('server', fn ($q) => $q->where('organization_id', $request->user()->current_organization_id))
            ->with('server:id,name,status')
            ->latest()
            ->paginate(30)
            ->through(fn (Website $website) => [
                'id' => $website->id,
                'primary_domain' => $website->primary_domain,
                'status' => $website->status->value,
                'webserver' => $website->webserver,
                'php_version' => $website->php_version,
                'ssl_enabled' => $website->ssl_enabled,
                'root_path' => $website->root_path,
                'framework' => $website->framework,
                'last_synced_at' => $website->last_synced_at?->toIso8601String(),
                'server' => [
                    'id' => $website->server->id,
                    'name' => $website->server->name,
                    'status' => $website->server->status->value,
                ],
            ]);

        return Inertia::render('Websites/Index', [
            'websites' => $websites,
        ]);
    }

    public function show(Website $website): Response
    {
        $this->authorize('view', $website);

        $website->load('server');

        $latestJob = ServerJob::query()
            ->where('website_id', $website->id)
            ->latest('id')
            ->first();

        return Inertia::render('Websites/Show', [
            'website' => [
                'id' => $website->id,
                'name' => $website->name,
                'primary_domain' => $website->primary_domain,
                'domains' => $website->domains,
                'root_path' => $website->root_path,
                'webserver' => $website->webserver,
                'config_path' => $website->config_path,
                'php_version' => $website->php_version,
                'ssl_enabled' => $website->ssl_enabled,
                'status' => $website->status->value,
                'framework' => $website->framework,
                'framework_version' => $website->framework_version,
                'meta' => $website->meta,
                'detected_at' => $website->detected_at?->toIso8601String(),
                'last_synced_at' => $website->last_synced_at?->toIso8601String(),
            ],
            'server' => [
                'id' => $website->server->id,
                'name' => $website->server->name,
                'status' => $website->server->status->value,
            ],
            'latest_job' => $latestJob ? [
                'uuid' => $latestJob->uuid,
                'type' => $latestJob->type->value,
                'status' => $latestJob->status->value,
                'error_code' => $latestJob->error_code,
                'error_message' => $latestJob->error_message,
                'completed_at' => $latestJob->completed_at?->toIso8601String(),
            ] : null,
            'control_mode' => config('zyrox.website_actions', 'local'),
        ]);
    }

    public function start(Request $request, Website $website): RedirectResponse
    {
        return $this->handleWebsiteAction($request, $website, 'start', AgentCommand::WebsiteStart, 'website.started', 'Website started');
    }

    public function stop(Request $request, Website $website): RedirectResponse
    {
        return $this->handleWebsiteAction($request, $website, 'stop', AgentCommand::WebsiteStop, 'website.stopped', 'Website stopped');
    }

    public function restart(Request $request, Website $website): RedirectResponse
    {
        return $this->handleWebsiteAction($request, $website, 'restart', AgentCommand::WebsiteRestart, 'website.restarted', 'Website restarted');
    }

    public function enable(Request $request, Website $website): RedirectResponse
    {
        return $this->handleWebsiteAction($request, $website, 'enable', AgentCommand::WebsiteEnable, 'website.enabled', 'Website enabled');
    }

    public function disable(Request $request, Website $website): RedirectResponse
    {
        return $this->handleWebsiteAction($request, $website, 'disable', AgentCommand::WebsiteDisable, 'website.disabled', 'Website disabled');
    }

    private function handleWebsiteAction(
        Request $request,
        Website $website,
        string $action,
        AgentCommand $command,
        string $auditAction,
        string $successMessage,
    ): RedirectResponse {
        $this->authorize('manage', $website);

        $mode = (string) config('zyrox.website_actions', 'auto');

        if ($mode === 'local' || ($mode === 'auto' && $this->shouldRunLocally($website))) {
            try {
                match ($action) {
                    'start', 'enable' => $this->websiteControl->start($website),
                    'stop', 'disable' => $this->websiteControl->stop($website),
                    'restart' => $this->websiteControl->restart($website),
                    default => throw new RuntimeException("Unknown action [{$action}]"),
                };
            } catch (Throwable $e) {
                return back()->with('error', $e->getMessage());
            }

            $this->auditLogger->log(
                organization: $website->server->organization,
                action: $auditAction,
                user: $request->user(),
                server: $website->server,
                website: $website,
                payload: ['mode' => 'local'],
                request: $request,
            );

            return back()->with('success', $successMessage);
        }

        return $this->runViaAgent($request, $website, $command, $auditAction, $successMessage);
    }

    private function shouldRunLocally(Website $website): bool
    {
        $localIds = config('zyrox.local_server_ids', []);
        if (is_array($localIds) && in_array((int) $website->server_id, $localIds, true)) {
            return true;
        }

        // Only treat as local when THIS host actually has that site's config.
        $path = (string) ($website->config_path ?: '');
        if ($path !== '' && (is_file($path) || is_link($path))) {
            return true;
        }

        $base = $path !== '' ? basename($path) : '';
        if ($base !== '') {
            foreach ([
                '/etc/nginx/sites-enabled/'.$base,
                '/etc/nginx/sites-available/'.$base,
                '/etc/apache2/sites-enabled/'.$base,
                '/etc/apache2/sites-available/'.$base,
            ] as $candidate) {
                if (is_file($candidate) || is_link($candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function runViaAgent(
        Request $request,
        Website $website,
        AgentCommand $command,
        string $auditAction,
        string $successMessage,
    ): RedirectResponse {
        // Clear anything pending/running on this server so website actions are handled next.
        ServerJob::query()
            ->where('server_id', $website->server_id)
            ->whereIn('status', [
                ServerJobStatus::Pending->value,
                ServerJobStatus::Running->value,
            ])
            ->update([
                'status' => ServerJobStatus::Cancelled,
                'completed_at' => now(),
                'error_code' => 'JOB_CANCELLED',
                'error_message' => 'Superseded by a website action.',
            ]);

        $job = $this->jobService->dispatch(
            server: $website->server,
            type: $command,
            payload: [
                'website_id' => $website->id,
                'domain' => $website->primary_domain,
                'config_path' => $website->config_path,
                'webserver' => $website->webserver,
                'root_path' => $website->root_path,
            ],
            website: $website,
            user: $request->user(),
            priority: 100,
            idempotencyKey: $command->value.':'.$website->id.':'.now()->format('YmdHis'),
        );

        $this->auditLogger->log(
            organization: $website->server->organization,
            action: $auditAction,
            user: $request->user(),
            server: $website->server,
            website: $website,
            payload: ['job_uuid' => $job->uuid, 'mode' => 'agent'],
            request: $request,
        );

        // Brief wait only — long blocking hits nginx 504 (proxy_read_timeout).
        // If the agent is slow, the website page polls latest_job and toasts the result.
        $deadline = now()->addSeconds(12);
        do {
            $job->refresh();
            if (in_array($job->status, [
                ServerJobStatus::Success,
                ServerJobStatus::Failed,
                ServerJobStatus::Expired,
                ServerJobStatus::Cancelled,
            ], true)) {
                break;
            }
            usleep(200_000);
        } while (now()->lt($deadline));

        $job->refresh();
        $website->refresh();

        if ($job->status === ServerJobStatus::Success) {
            return back()->with('success', $successMessage);
        }

        if ($job->status === ServerJobStatus::Failed) {
            return back()->with('error', $job->error_message ?: 'Action failed on the remote server.');
        }

        // Still pending/running: no flash (avoids "queued" noise). UI keeps polling.
        return back();
    }
}
