<?php

namespace App\Http\Controllers\Web;

use App\Enums\AgentCommand;
use App\Http\Controllers\Controller;
use App\Models\ServerJob;
use App\Models\Website;
use App\Services\AuditLogger;
use App\Services\JobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteController extends Controller
{
    public function __construct(
        private readonly JobService $jobService,
        private readonly AuditLogger $auditLogger,
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
        ]);
    }

    public function start(Request $request, Website $website): RedirectResponse
    {
        return $this->queueWebsiteAction($request, $website, AgentCommand::WebsiteStart, 'website.started');
    }

    public function stop(Request $request, Website $website): RedirectResponse
    {
        return $this->queueWebsiteAction($request, $website, AgentCommand::WebsiteStop, 'website.stopped');
    }

    public function restart(Request $request, Website $website): RedirectResponse
    {
        return $this->queueWebsiteAction($request, $website, AgentCommand::WebsiteRestart, 'website.restarted');
    }

    public function enable(Request $request, Website $website): RedirectResponse
    {
        return $this->queueWebsiteAction($request, $website, AgentCommand::WebsiteEnable, 'website.enabled');
    }

    public function disable(Request $request, Website $website): RedirectResponse
    {
        return $this->queueWebsiteAction($request, $website, AgentCommand::WebsiteDisable, 'website.disabled');
    }

    private function queueWebsiteAction(Request $request, Website $website, AgentCommand $command, string $auditAction): RedirectResponse
    {
        $this->authorize('manage', $website);

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
            idempotencyKey: $command->value.':'.$website->id.':'.now()->format('YmdHi'),
        );

        $this->auditLogger->log(
            organization: $website->server->organization,
            action: $auditAction,
            user: $request->user(),
            server: $website->server,
            website: $website,
            payload: ['job_uuid' => $job->uuid],
            request: $request,
        );

        return back()->with('success', 'Action queued — waiting for the server…');
    }
}
