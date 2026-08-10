<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Power, PowerOff } from '@lucide/vue';
import { ref } from 'vue';
import StatusBadge from '@/components/status/StatusBadge.vue';
import { Button } from '@/components/ui/button';

type WebsiteRow = {
    id: number;
    primary_domain: string;
    status: string;
    webserver: string | null;
    php_version: string | null;
    ssl_enabled: boolean;
};

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
    websites: WebsiteRow[];
};

defineProps<{
    servers: {
        data: ServerRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const expanded = ref<Record<number, boolean>>({});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Servers', href: '/servers' },
        ],
    },
});

function toggle(serverId: number) {
    expanded.value[serverId] = !expanded.value[serverId];
}

function startWebsite(website: WebsiteRow) {
    if (!confirm(`Start website ${website.primary_domain}?`)) {
        return;
    }

    router.post(`/websites/${website.id}/start`, {}, { preserveScroll: true });
}

function stopWebsite(website: WebsiteRow) {
    if (!confirm(`Stop website ${website.primary_domain}?`)) {
        return;
    }

    router.post(`/websites/${website.id}/stop`, {}, { preserveScroll: true });
}
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
                        <th class="w-10 px-4 py-3"></th>
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
                            colspan="8"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No servers registered yet.
                        </td>
                    </tr>

                    <template
                        v-for="server in servers.data"
                        :key="server.id"
                    >
                        <tr
                            class="border-t cursor-pointer hover:bg-muted/30"
                            @click="toggle(server.id)"
                        >
                            <td class="px-4 py-3 text-muted-foreground">
                                <ChevronDown
                                    v-if="expanded[server.id]"
                                    class="size-4"
                                />
                                <ChevronRight v-else class="size-4" />
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/servers/${server.id}`"
                                    class="font-medium hover:underline"
                                    @click.stop
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

                        <tr v-if="expanded[server.id]" class="border-t bg-muted/20">
                            <td colspan="8" class="px-4 py-4">
                                <div
                                    v-if="server.websites.length === 0"
                                    class="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground"
                                >
                                    No websites discovered on this server yet.
                                </div>

                                <div v-else class="space-y-2">
                                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        Websites on {{ server.name }}
                                    </p>

                                    <div
                                        v-for="website in server.websites"
                                        :key="website.id"
                                        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-background px-3 py-2"
                                    >
                                        <div class="min-w-0">
                                            <Link
                                                :href="`/websites/${website.id}`"
                                                class="font-medium hover:underline"
                                            >
                                                {{ website.primary_domain }}
                                            </Link>
                                            <p class="text-xs text-muted-foreground">
                                                {{ website.webserver || 'web' }}
                                                · PHP {{ website.php_version || '?' }}
                                                · SSL {{ website.ssl_enabled ? 'on' : 'off' }}
                                            </p>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <StatusBadge :status="website.status" />
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="gap-1"
                                                @click.stop="startWebsite(website)"
                                            >
                                                <Power class="size-3.5" />
                                                Start
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                class="gap-1"
                                                @click.stop="stopWebsite(website)"
                                            >
                                                <PowerOff class="size-3.5" />
                                                Stop
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
