<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboardService): Response
    {
        $organization = $request->user()->currentOrganization;

        abort_if($organization === null, 403);

        $overview = $dashboardService->overview($organization);

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_servers' => $overview['total_servers'],
                'online_servers' => $overview['online_servers'],
                'offline_servers' => $overview['offline_servers'],
                'pending_servers' => $overview['pending_servers'],
                'total_websites' => $overview['total_websites'],
                'healthy_websites' => $overview['healthy_websites'],
                'failed_websites' => $overview['failed_websites'],
                'cpu_average' => $overview['cpu_average'],
                'ram_average' => $overview['ram_average'],
                'disk_alerts' => $overview['disk_alerts'],
            ],
            'servers' => $overview['servers']->map(fn ($server) => [
                'id' => $server->id,
                'name' => $server->name,
                'hostname' => $server->hostname,
                'status' => $server->status->value,
                'websites_count' => $server->websites->count(),
                'services_count' => $server->services()->count(),
                'cpu' => $server->latestMetric?->cpu_percent,
                'ram' => $server->latestMetric?->memory_percent,
                'disk' => $server->latestMetric?->disk_percent,
                'last_seen_at' => $server->last_seen_at?->toIso8601String(),
            ]),
            'activity' => $overview['recent_activity']->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'result' => $log->result,
                'user' => $log->user?->name,
                'server' => $log->server?->name,
                'website' => $log->website?->primary_domain,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
