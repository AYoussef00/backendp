<?php

namespace App\Http\Controllers\Web;

use App\Enums\AgentCommand;
use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\AuditLogger;
use App\Services\JobService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FileController extends Controller
{
    public function __construct(
        private readonly JobService $jobService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request, Server $server): Response
    {
        $this->authorize('view', $server);
        abort_unless($request->user()->can(Permissions::FILES_VIEW), 403);

        $path = $request->string('path')->toString() ?: '/var/www';

        $job = $this->jobService->dispatch(
            server: $server,
            type: AgentCommand::ListFiles,
            payload: ['path' => $path],
            user: $request->user(),
            idempotencyKey: 'list_files:'.$server->id.':'.md5($path).':'.now()->format('YmdHi'),
            expiresInMinutes: 5,
        );

        return Inertia::render('Files/Index', [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'status' => $server->status->value,
            ],
            'path' => $path,
            'job' => [
                'uuid' => $job->uuid,
                'status' => $job->status->value,
                'result' => $job->result,
                'error_message' => $job->error_message,
            ],
        ]);
    }

    public function write(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);
        abort_unless($request->user()->can(Permissions::FILES_WRITE), 403);

        $data = $request->validate([
            'path' => ['required', 'string', 'max:1024'],
            'content' => ['required', 'string', 'max:1048576'],
        ]);

        $job = $this->jobService->dispatch(
            server: $server,
            type: AgentCommand::WriteFile,
            payload: $data,
            user: $request->user(),
        );

        $this->auditLogger->log(
            organization: $server->organization,
            action: 'file.written',
            user: $request->user(),
            server: $server,
            payload: ['path' => $data['path'], 'job_uuid' => $job->uuid],
            request: $request,
        );

        return back()->with('success', 'Write file job queued.');
    }

    public function destroy(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);
        abort_unless($request->user()->can(Permissions::FILES_DELETE), 403);

        $data = $request->validate([
            'path' => ['required', 'string', 'max:1024'],
        ]);

        $job = $this->jobService->dispatch(
            server: $server,
            type: AgentCommand::DeleteFile,
            payload: $data,
            user: $request->user(),
        );

        $this->auditLogger->log(
            organization: $server->organization,
            action: 'file.deleted',
            user: $request->user(),
            server: $server,
            payload: ['path' => $data['path'], 'job_uuid' => $job->uuid],
            request: $request,
        );

        return back()->with('success', 'Delete file job queued.');
    }

    public function read(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('view', $server);
        abort_unless($request->user()->can(Permissions::FILES_READ), 403);

        $data = $request->validate([
            'path' => ['required', 'string', 'max:1024'],
        ]);

        $this->jobService->dispatch(
            server: $server,
            type: AgentCommand::ReadFile,
            payload: $data,
            user: $request->user(),
            expiresInMinutes: 5,
        );

        return back()->with('success', 'Read file job queued.');
    }

    public function serviceAction(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);
        abort_unless($request->user()->can(Permissions::SERVICES_MANAGE), 403);

        $data = $request->validate([
            'service' => ['required', 'string', Rule::in(['nginx', 'apache2', 'php-fpm', 'mysql', 'mariadb', 'redis', 'supervisor', 'docker'])],
            'action' => ['required', 'string', Rule::in(['start', 'stop', 'restart', 'reload'])],
        ]);

        $command = match ($data['action']) {
            'start' => AgentCommand::ServiceStart,
            'stop' => AgentCommand::ServiceStop,
            'restart' => AgentCommand::ServiceRestart,
            'reload' => AgentCommand::ServiceReload,
        };

        $job = $this->jobService->dispatch(
            server: $server,
            type: $command,
            payload: ['service' => $data['service']],
            user: $request->user(),
        );

        $this->auditLogger->log(
            organization: $server->organization,
            action: 'service.'.$data['action'],
            user: $request->user(),
            server: $server,
            payload: ['service' => $data['service'], 'job_uuid' => $job->uuid],
            request: $request,
        );

        return back()->with('success', 'Service job queued.');
    }
}
