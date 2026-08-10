<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgentBinaryController extends Controller
{
    public function __invoke(Request $request, string $binary): BinaryFileResponse
    {
        abort_unless(preg_match('/^zyrox-agent-(linux|darwin)-(amd64|arm64)$/', $binary) === 1, 404);

        $candidates = [
            storage_path('app/agent-binaries/'.$binary),
            base_path('agent/dist/'.$binary),
            public_path('agent/binaries/'.$binary),
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                return response()->file($path, [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Disposition' => 'attachment; filename="'.$binary.'"',
                    'Cache-Control' => 'no-store',
                ]);
            }
        }

        abort(404, 'Agent binary not found. Place it in storage/app/agent-binaries/'.$binary);
    }
}
