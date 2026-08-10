<?php

namespace App\Http\Controllers\Api\Agent\V1;

use App\Enums\AgentCommand;
use App\Enums\ServerJobStatus;
use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\ServerJob;
use App\Services\AgentService;
use App\Services\JobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    public function __construct(
        private readonly AgentService $agentService,
        private readonly JobService $jobService,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'installation_token' => ['required', 'string', 'min:20'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'agent_version' => ['nullable', 'string', 'max:50'],
            'os' => ['nullable', 'array'],
            'os.name' => ['nullable', 'string', 'max:100'],
            'os.version' => ['nullable', 'string', 'max:100'],
            'ip_address' => ['nullable', 'ip'],
        ]);

        $result = $this->agentService->register(
            $data['installation_token'],
            $data,
            $request->ip(),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'server_id' => $result['server']->id,
                'agent_id' => $result['agent_id'],
                'agent_secret' => $result['agent_secret'],
                'poll_interval_seconds' => 15,
                'heartbeat_interval_seconds' => 30,
            ],
        ], 201);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        /** @var Server $server */
        $server = $request->attributes->get('agent_server');

        $data = $request->validate([
            'hostname' => ['nullable', 'string', 'max:255'],
            'agent_version' => ['nullable', 'string', 'max:50'],
            'timestamp' => ['nullable', 'date'],
        ]);

        $this->agentService->heartbeat($server, $data);

        return response()->json([
            'success' => true,
            'data' => [
                'server_status' => $server->fresh()->status->value,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    public function discovery(Request $request): JsonResponse
    {
        /** @var Server $server */
        $server = $request->attributes->get('agent_server');

        $data = $request->validate([
            'hostname' => ['nullable', 'string', 'max:255'],
            'os' => ['nullable', 'array'],
            'cpu' => ['nullable', 'array'],
            'memory' => ['nullable', 'array'],
            'disk' => ['nullable', 'array'],
            'webservers' => ['nullable', 'array'],
            'php' => ['nullable', 'array'],
            'services' => ['nullable', 'array'],
        ]);

        $this->agentService->storeDiscovery($server, $data);

        return response()->json([
            'success' => true,
            'data' => ['stored' => true],
        ]);
    }

    public function websites(Request $request): JsonResponse
    {
        /** @var Server $server */
        $server = $request->attributes->get('agent_server');

        $data = $request->validate([
            'websites' => ['required', 'array'],
            'websites.*.domain' => ['required_without:websites.*.primary_domain', 'string', 'max:255'],
            'websites.*.primary_domain' => ['required_without:websites.*.domain', 'string', 'max:255'],
            'websites.*.aliases' => ['nullable', 'array'],
            'websites.*.root_path' => ['nullable', 'string', 'max:1024'],
            'websites.*.config_path' => ['nullable', 'string', 'max:1024'],
            'websites.*.webserver' => ['nullable', 'string', 'max:50'],
            'websites.*.ssl' => ['nullable', 'boolean'],
            'websites.*.php_version' => ['nullable', 'string', 'max:20'],
            'websites.*.status' => ['nullable', 'string', 'max:50'],
            'websites.*.framework' => ['nullable', 'string', 'max:50'],
        ]);

        $this->agentService->syncWebsites($server, $data['websites']);

        return response()->json([
            'success' => true,
            'data' => ['count' => count($data['websites'])],
        ]);
    }

    public function metrics(Request $request): JsonResponse
    {
        /** @var Server $server */
        $server = $request->attributes->get('agent_server');

        $data = $request->validate([
            'cpu_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'memory_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'memory_used' => ['nullable', 'integer', 'min:0'],
            'disk_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'disk_used' => ['nullable', 'integer', 'min:0'],
            'load' => ['nullable', 'array'],
            'load_1' => ['nullable', 'numeric'],
            'load_5' => ['nullable', 'numeric'],
            'load_15' => ['nullable', 'numeric'],
            'uptime_seconds' => ['nullable', 'integer', 'min:0'],
            'network' => ['nullable', 'array'],
        ]);

        $metric = $this->agentService->storeMetrics($server, $data);

        return response()->json([
            'success' => true,
            'data' => ['metric_id' => $metric->id],
        ]);
    }

    public function jobs(Request $request): JsonResponse
    {
        /** @var Server $server */
        $server = $request->attributes->get('agent_server');

        $job = $this->jobService->claimNext($server);

        if ($job === null) {
            return response()->json([
                'success' => true,
                'data' => ['job' => null],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'job' => [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'type' => $job->type->value,
                    'payload' => $job->payload,
                    'timeout_seconds' => $job->type->timeoutSeconds(),
                    'expires_at' => $job->expires_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    public function jobResult(Request $request, ServerJob $job): JsonResponse
    {
        /** @var Server $server */
        $server = $request->attributes->get('agent_server');

        if ((int) $job->server_id !== (int) $server->id) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'JOB_NOT_ALLOWED', 'message' => 'Job does not belong to this agent.'],
            ], 403);
        }

        if ($job->status === ServerJobStatus::Expired || $job->isExpired()) {
            $job->update(['status' => ServerJobStatus::Expired, 'completed_at' => now()]);

            return response()->json([
                'success' => false,
                'error' => ['code' => 'JOB_EXPIRED', 'message' => 'Job expired.'],
            ], 410);
        }

        $data = $request->validate([
            'success' => ['required', 'boolean'],
            'result' => ['nullable', 'array'],
            'error' => ['nullable', 'array'],
            'error.code' => ['nullable', 'string', 'max:100'],
            'error.message' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! in_array($job->type->value, AgentCommand::values(), true)) {
            $job->markFailed('JOB_NOT_ALLOWED', 'Unknown command rejected.');

            return response()->json([
                'success' => false,
                'error' => ['code' => 'JOB_NOT_ALLOWED', 'message' => 'Unknown command.'],
            ], 422);
        }

        if ($data['success']) {
            $job->markSuccess($data['result'] ?? []);
        } else {
            $job->markFailed(
                $data['error']['code'] ?? 'OPERATION_FAILED',
                $data['error']['message'] ?? 'Agent reported failure.',
                $data['result'] ?? null,
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'job_uuid' => $job->uuid,
                'status' => $job->fresh()->status->value,
            ],
        ]);
    }
}
