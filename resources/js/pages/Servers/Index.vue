<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StatusBadge from '@/components/status/StatusBadge.vue';
import { Button } from '@/components/ui/button';

type ServerRow = {
    id: number;
    name: string;
    hostname: string | null;
    status: string;
    websites_count: number;
    os: string;
    cpu: number | null;
    ram: number | null;
    disk: number | null;
    last_seen_at: string | null;
    agent_version: string | null;
};

defineProps<{
    servers: {
        data: ServerRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Servers', href: '/servers' },
        ],
    },
});
</script>

<template>
    <Head title="Servers" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Servers</h1>
                <p class="text-sm text-muted-foreground">
                    Manage connected infrastructure
                </p>
            </div>
            <Button as-child>
                <Link href="/servers/create">Add Server</Link>
            </Button>
        </div>

        <div class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Websites</th>
                        <th class="px-4 py-3 font-medium">CPU</th>
                        <th class="px-4 py-3 font-medium">RAM</th>
                        <th class="px-4 py-3 font-medium">Disk</th>
                        <th class="px-4 py-3 font-medium">Last seen</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="servers.data.length === 0">
                        <td
                            colspan="7"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No servers registered yet.
                        </td>
                    </tr>
                    <tr
                        v-for="server in servers.data"
                        :key="server.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="`/servers/${server.id}`"
                                class="font-medium hover:underline"
                            >
                                {{ server.name }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ server.hostname || '—' }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge :status="server.status" />
                        </td>
                        <td class="px-4 py-3">{{ server.websites_count }}</td>
                        <td class="px-4 py-3">{{ server.cpu ?? '—' }}%</td>
                        <td class="px-4 py-3">{{ server.ram ?? '—' }}%</td>
                        <td class="px-4 py-3">{{ server.disk ?? '—' }}%</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ server.last_seen_at || '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
