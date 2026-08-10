<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Server } from '@lucide/vue';
import StatusBadge from '@/components/status/StatusBadge.vue';
import { Button } from '@/components/ui/button';

type StatProps = {
    total_servers: number;
    online_servers: number;
    offline_servers: number;
    pending_servers: number;
    total_websites: number;
    healthy_websites: number;
    failed_websites: number;
    cpu_average: number | null;
    ram_average: number | null;
    disk_alerts: number;
};

type ServerCard = {
    id: number;
    name: string;
    hostname: string | null;
    status: string;
    websites_count: number;
    services_count: number;
    cpu: number | null;
    ram: number | null;
    disk: number | null;
};

type ActivityItem = {
    id: number;
    action: string;
    result: string | null;
    user: string | null;
    server: string | null;
    website: string | null;
    created_at: string | null;
};

defineProps<{
    stats: StatProps;
    servers: ServerCard[];
    activity: ActivityItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Overview</h1>
                <p class="text-sm text-muted-foreground">
                    Fleet health across your organization
                </p>
            </div>
            <Button as-child>
                <Link href="/servers/create">Add Server</Link>
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">Total Servers</p>
                <p class="mt-2 text-3xl font-semibold">
                    {{ stats.total_servers }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ stats.online_servers }} online ·
                    {{ stats.offline_servers }} offline
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">Websites</p>
                <p class="mt-2 text-3xl font-semibold">
                    {{ stats.total_websites }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ stats.healthy_websites }} healthy ·
                    {{ stats.failed_websites }} failed
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">CPU / RAM avg</p>
                <p class="mt-2 text-3xl font-semibold">
                    {{ stats.cpu_average ?? '—' }}% /
                    {{ stats.ram_average ?? '—' }}%
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">Disk alerts</p>
                <p class="mt-2 text-3xl font-semibold">
                    {{ stats.disk_alerts }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">Disk ≥ 90%</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-4 xl:col-span-2">
                <h2 class="text-lg font-medium">Servers</h2>
                <div
                    v-if="servers.length === 0"
                    class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
                >
                    No servers yet. Add your first server to begin.
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <Link
                        v-for="server in servers"
                        :key="server.id"
                        :href="`/servers/${server.id}`"
                        class="rounded-xl border p-4 transition hover:bg-muted/40"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <Server class="size-4 text-muted-foreground" />
                                <div>
                                    <p class="font-medium">{{ server.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ server.hostname || 'Awaiting agent' }}
                                    </p>
                                </div>
                            </div>
                            <StatusBadge :status="server.status" />
                        </div>
                        <div
                            class="mt-4 grid grid-cols-3 gap-2 text-center text-xs"
                        >
                            <div class="rounded-lg bg-muted/50 p-2">
                                <p class="text-muted-foreground">CPU</p>
                                <p class="mt-1 font-medium">
                                    {{ server.cpu ?? '—' }}%
                                </p>
                            </div>
                            <div class="rounded-lg bg-muted/50 p-2">
                                <p class="text-muted-foreground">RAM</p>
                                <p class="mt-1 font-medium">
                                    {{ server.ram ?? '—' }}%
                                </p>
                            </div>
                            <div class="rounded-lg bg-muted/50 p-2">
                                <p class="text-muted-foreground">Disk</p>
                                <p class="mt-1 font-medium">
                                    {{ server.disk ?? '—' }}%
                                </p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            {{ server.websites_count }} websites ·
                            {{ server.services_count }} services
                        </p>
                    </Link>
                </div>
            </div>

            <div class="space-y-4">
                <h2 class="text-lg font-medium">Recent activity</h2>
                <div class="rounded-xl border">
                    <div
                        v-if="activity.length === 0"
                        class="p-6 text-sm text-muted-foreground"
                    >
                        No activity yet.
                    </div>
                    <div
                        v-for="item in activity"
                        :key="item.id"
                        class="border-b p-4 last:border-b-0"
                    >
                        <p class="text-sm font-medium">{{ item.action }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ item.server || 'System' }}
                            <span v-if="item.website">
                                · {{ item.website }}</span
                            >
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ item.created_at }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
