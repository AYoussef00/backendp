<?php

namespace App\Http\Controllers;

use App\Models\AgentInstallation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class InstallScriptController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $prefix = substr($token, 0, 8);

        $candidates = AgentInstallation::query()
            ->with('server')
            ->where('token_prefix', $prefix)
            ->orderByDesc('id')
            ->get();

        $installation = $candidates->first(
            fn (AgentInstallation $item) => Hash::check($token, $item->token_hash),
        );

        if ($installation === null) {
            return $this->plainError(404, 'Installation token not found.');
        }

        if ($installation->used_at !== null) {
            return $this->plainError(410, 'Installation token already used. Generate a new one from the dashboard.');
        }

        if ($installation->expires_at === null || $installation->expires_at->isPast()) {
            return $this->plainError(410, 'Installation token expired. Generate a new one from the dashboard.');
        }

        $panelUrl = rtrim((string) config('app.url'), '/');
        $version = config('zyrox.agent_version', '1.0.0');
        $downloadBase = $panelUrl.'/agent/download';

        $script = view('install.agent', [
            'token' => $token,
            'panelUrl' => $panelUrl,
            'version' => $version,
            'downloadBase' => $downloadBase,
            'serverName' => $installation->server->name,
        ])->render();

        return response($script, 200, [
            'Content-Type' => 'text/x-shellscript; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function plainError(int $status, string $message): Response
    {
        return response($message."\n", $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
