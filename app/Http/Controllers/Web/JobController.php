<?php

namespace App\Http\Controllers\Web;

use App\Enums\AgentCommand;
use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\ServerJob;
use App\Services\JobService;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(Permissions::SERVERS_VIEW), 403);

        $jobs = ServerJob::query()
            ->where('organization_id', $request->user()->current_organization_id)
            ->with(['server:id,name', 'website:id,primary_domain', 'creator:id,name'])
            ->latest()
            ->paginate(40)
            ->through(fn (ServerJob $job) => [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'type' => $job->type->value,
                'status' => $job->status->value,
                'error_code' => $job->error_code,
                'error_message' => $job->error_message,
                'created_at' => $job->created_at?->toIso8601String(),
                'completed_at' => $job->completed_at?->toIso8601String(),
                'server' => $job->server?->only(['id', 'name']),
                'website' => $job->website ? ['id' => $job->website->id, 'primary_domain' => $job->website->primary_domain] : null,
                'creator' => $job->creator?->only(['id', 'name']),
            ]);

        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs,
        ]);
    }

    public function show(Request $request, ServerJob $job): Response
    {
        abort_unless((int) $job->organization_id === (int) $request->user()->current_organization_id, 403);
        abort_unless($request->user()->can(Permissions::SERVERS_VIEW), 403);

        $job->load(['server', 'website', 'creator']);

        return Inertia::render('Jobs/Show', [
            'job' => [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'type' => $job->type->value,
                'status' => $job->status->value,
                'payload' => $job->payload,
                'result' => $job->result,
                'error_code' => $job->error_code,
                'error_message' => $job->error_message,
                'attempts' => $job->attempts,
                'created_at' => $job->created_at?->toIso8601String(),
                'started_at' => $job->started_at?->toIso8601String(),
                'completed_at' => $job->completed_at?->toIso8601String(),
                'server' => $job->server?->only(['id', 'name', 'status']),
                'website' => $job->website?->only(['id', 'primary_domain']),
                'creator' => $job->creator?->only(['id', 'name']),
            ],
        ]);
    }

    public function logs(Request $request, Server $server, JobService $jobService): Response
    {
        $this->authorize('view', $server);
        abort_unless($request->user()->can(Permissions::LOGS_VIEW), 403);

        $source = $request->string('source')->toString() ?: 'nginx_error';
        $lines = min(500, max(50, (int) $request->integer('lines', 200)));

        $job = $jobService->dispatch(
            server: $server,
            type: AgentCommand::GetLogs,
            payload: [
                'source' => $source,
                'lines' => $lines,
                'search' => $request->string('search')->toString() ?: null,
            ],
            user: $request->user(),
            expiresInMinutes: 5,
        );

        return Inertia::render('Logs/Index', [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'status' => $server->status->value,
            ],
            'source' => $source,
            'lines' => $lines,
            'job' => [
                'uuid' => $job->uuid,
                'status' => $job->status->value,
                'result' => $job->fresh()->result,
                'error_message' => $job->error_message,
            ],
            'sources' => [
                'nginx_access',
                'nginx_error',
                'apache_access',
                'apache_error',
                'php_fpm',
                'agent',
                'syslog',
            ],
        ]);
    }
}
