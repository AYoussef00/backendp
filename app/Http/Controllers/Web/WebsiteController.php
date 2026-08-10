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

    public function status(Website $website): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $website);

        $website->loadMissing('server');
        $website->refresh();

        $latestJob = ServerJob::query()
            ->where('website_id', $website->id)
            ->latest('id')
            ->first();

        return response()->json([
            'website' => [
                'id' => $website->id,
                'status' => $website->status->value,
                'last_synced_at' => $website->last_synced_at?->toIso8601String(),
            ],
            'server' => [
                'id' => $website->server_id,
                'last_seen_at' => $website->server?->last_seen_at?->toIso8601String(),
            ],
            'latest_job' => $latestJob ? [
                'uuid' => $latestJob->uuid,
                'type' => $latestJob->type->value,
                'status' => $latestJob->status->value,
                'error_code' => $latestJob->error_code,
                'error_message' => $latestJob->error_message,
                'completed_at' => $latestJob->completed_at?->toIso8601String(),
            ] : null,
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
        // Only servers explicitly marked as local — never guess from filesystem paths
        // (remote sites can share the same config basename and hang the panel request).
        $localIds = config('zyrox.local_server_ids', []);

        return is_array($localIds) && in_array((int) $website->server_id, $localIds, true);
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

        // Never block the HTTP request (nginx 504). The Show page polls /status.
        return redirect()->route('websites.show', $website);
    }
}
