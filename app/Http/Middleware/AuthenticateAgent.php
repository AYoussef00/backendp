<?php

namespace App\Http\Middleware;

use App\Services\AgentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    public function __construct(private readonly AgentService $agentService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $agentId = $request->header('X-Agent-Id') ?: $request->input('agent_id');
        $agentSecret = $request->header('X-Agent-Secret') ?: $request->input('agent_secret');

        if (blank($agentId) || blank($agentSecret)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AGENT_UNAUTHORIZED',
                    'message' => 'Missing agent credentials.',
                ],
            ], 401);
        }

        $server = $this->agentService->authenticate((string) $agentId, (string) $agentSecret);
        $request->attributes->set('agent_server', $server);

        return $next($request);
    }
}
