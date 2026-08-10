<?php

namespace App\Http\Controllers;

use App\Models\AgentInstallation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InstallScriptController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $prefix = substr($token, 0, 8);

        $installation = AgentInstallation::query()
            ->with('server')
            ->where('token_prefix', $prefix)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->get()
            ->first(fn (AgentInstallation $item) => $item->verify($token));

        abort_if($installation === null, 404, 'Invalid or expired installation token.');

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
}
