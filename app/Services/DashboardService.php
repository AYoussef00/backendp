<?php

namespace App\Services;

use App\Enums\ServerStatus;
use App\Enums\WebsiteStatus;
use App\Models\Organization;
use App\Models\Server;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(Organization $organization): array
    {
        $servers = Server::query()
            ->where('organization_id', $organization->id)
            ->with(['latestMetric', 'websites'])
            ->get();

        $online = $servers->where('status', ServerStatus::Online)->count();
        $offline = $servers->where('status', ServerStatus::Offline)->count();
        $websites = $servers->sum(fn (Server $server) => $server->websites->count());

        return [
            'total_servers' => $servers->count(),
            'online_servers' => $online,
            'offline_servers' => $offline,
            'pending_servers' => $servers->where('status', ServerStatus::Pending)->count(),
            'total_websites' => $websites,
            'healthy_websites' => $servers->sum(fn (Server $s) => $s->websites->where('status', WebsiteStatus::Active)->count()),
            'failed_websites' => $servers->sum(fn (Server $s) => $s->websites->where('status', WebsiteStatus::Error)->count()),
            'cpu_average' => $this->average($servers, 'cpu_percent'),
            'ram_average' => $this->average($servers, 'memory_percent'),
            'disk_alerts' => $servers->filter(fn (Server $s) => ($s->latestMetric?->disk_percent ?? 0) >= 90)->count(),
            'recent_activity' => $organization->auditLogs()
                ->latest('created_at')
                ->limit(10)
                ->with(['user', 'server', 'website'])
                ->get(),
            'servers' => $servers,
        ];
    }

    private function average(Collection $servers, string $field): ?float
    {
        $values = $servers
            ->map(fn (Server $server) => $server->latestMetric?->{$field})
            ->filter(fn ($value) => $value !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return round((float) $values->avg(), 1);
    }
}
