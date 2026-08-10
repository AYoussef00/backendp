<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import StatusBadge from '@/components/status/StatusBadge.vue';
import { Button } from '@/components/ui/button';

type ServerDetail = {
    id: number;
    name: string;
    hostname: string | null;
    ip_address: string | null;
    status: string;
    os_name: string | null;
    os_version: string | null;
    cpu_cores: number | null;
    memory_total: number | null;
    disk_total: number | null;
    agent_version: string | null;
    last_seen_at: string | null;
    registered_at: string | null;
    metrics: {
        cpu: number | null;
        ram: number | null;
        disk: number | null;
        load_1: number | null;
        uptime_seconds: number | null;
    } | null;
};

const props = defineProps<{
    server: ServerDetail;
    websites: Array<{
        id: number;
        primary_domain: string;
        status: string;
        webserver: string | null;
        php_version: string | null;
        ssl_enabled: boolean;
        root_path: string | null;
        framework: string | null;
        last_synced_at: string | null;
    }>;
    services: Array<{
        id: number;
        name: string;
        display_name: string | null;
        status: string;
        enabled: boolean;
        version: string | null;
    }>;
    jobs: Array<{
        id: number;
        uuid: string;
        type: string;
        status: string;
        error_message: string | null;
        created_at: string | null;
        completed_at: string | null;
    }>;
    install_command?: string | null;
    installation_token?: string | null;
}>();

const page = usePage();
const installCommand = computed(
    () =>
        props.install_command ||
        (page.props.flash as { install_command?: string })?.install_command ||
        null,
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Servers', href: '/servers' },
        ],
    },
});

function confirmDelete() {
    if (
        confirm(
            `Delete server "${props.server.name}"? This cannot be undone.`,
        )
    ) {
        router.delete(`/servers/${props.server.id}`);
    }
}

function serviceAction(service: string, action: string) {
    if (
        !confirm(
            `Are you sure you want to ${action} ${service}? This may interrupt websites.`,
        )
    ) {
        return;
    }

    router.post(`/servers/${props.server.id}/services`, { service, action });
}
</script>

<template>
    <Head :title="server.name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ server.name }}</h1>
                    <StatusBadge :status="server.status" />
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ server.hostname || 'Waiting for agent connection' }}
                    <span v-if="server.ip_address"> · {{ server.ip_address }}</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Form
                    :action="`/servers/${server.id}/discover`"
                    method="post"
                    v-slot="{ processing }"
                >
                    <Button type="submit" variant="outline" :disabled="processing"
                        >Scan server</Button
                    >
                </Form>
                <Form
                    :action="`/servers/${server.id}/regenerate-token`"
                    method="post"
                    v-slot="{ processing }"
                >
                    <Button type="submit" variant="outline" :disabled="processing"
                        >New install token</Button
                    >
                </Form>
                <Button variant="outline" as-child>
                    <Link :href="`/servers/${server.id}/files`">Files</Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="`/servers/${server.id}/logs`">Logs</Link>
                </Button>
                <Button variant="destructive" @click="confirmDelete"
                    >Delete</Button
                >
            </div>
        </div>

        <div
            v-if="installCommand"
            class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4"
        >
            <p class="text-sm font-medium">Installation command</p>
            <p class="mt-1 text-xs text-muted-foreground">
                Run this on the target Linux server as root. Token is one-time
                use.
            </p>
            <pre
                class="mt-3 overflow-x-auto rounded-lg bg-zinc-950 p-3 text-xs text-zinc-100"
                >{{ installCommand }}</pre
            >
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">CPU</p>
                <p class="mt-2 text-2xl font-semibold">
                    {{ server.metrics?.cpu ?? '—' }}%
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">RAM</p>
                <p class="mt-2 text-2xl font-semibold">
                    {{ server.metrics?.ram ?? '—' }}%
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Disk</p>
                <p class="mt-2 text-2xl font-semibold">
                    {{ server.metrics?.disk ?? '—' }}%
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Load</p>
                <p class="mt-2 text-2xl font-semibold">
                    {{ server.metrics?.load_1 ?? '—' }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="space-y-3">
                <h2 class="text-lg font-medium">Websites</h2>
                <div class="overflow-hidden rounded-xl border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="px-3 py-2">Domain</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Stack</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="websites.length === 0">
                                <td
                                    colspan="3"
                                    class="px-3 py-6 text-center text-muted-foreground"
                                >
                                    No websites discovered yet.
                                </td>
                            </tr>
                            <tr
                                v-for="site in websites"
                                :key="site.id"
                                class="border-t"
                            >
                                <td class="px-3 py-2">
                                    <Link
                                        :href="`/websites/${site.id}`"
                                        class="font-medium hover:underline"
                                    >
                                        {{ site.primary_domain }}
                                    </Link>
                                </td>
                                <td class="px-3 py-2">
                                    <StatusBadge :status="site.status" />
                                </td>
                                <td class="px-3 py-2 text-muted-foreground">
                                    {{ site.webserver }} · PHP
                                    {{ site.php_version || '?' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-lg font-medium">Services</h2>
                <div class="overflow-hidden rounded-xl border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="px-3 py-2">Service</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="services.length === 0">
                                <td
                                    colspan="3"
                                    class="px-3 py-6 text-center text-muted-foreground"
                                >
                                    No services reported.
                                </td>
                            </tr>
                            <tr
                                v-for="service in services"
                                :key="service.id"
                                class="border-t"
                            >
                                <td class="px-3 py-2">
                                    {{ service.display_name || service.name }}
                                    <p
                                        v-if="service.version"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ service.version }}
                                    </p>
                                </td>
                                <td class="px-3 py-2">
                                    <StatusBadge :status="service.status" />
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex gap-1">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="
                                                serviceAction(
                                                    service.name,
                                                    'restart',
                                                )
                                            "
                                            >Restart</Button
                                        >
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="
                                                serviceAction(
                                                    service.name,
                                                    'reload',
                                                )
                                            "
                                            >Reload</Button
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium">Recent jobs</h2>
                <Button variant="outline" size="sm" as-child>
                    <Link href="/jobs">View all</Link>
                </Button>
            </div>
            <div class="overflow-hidden rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="job in jobs" :key="job.id" class="border-t">
                            <td class="px-3 py-2">
                                <Link
                                    :href="`/jobs/${job.id}`"
                                    class="hover:underline"
                                    >{{ job.type }}</Link
                                >
                            </td>
                            <td class="px-3 py-2">
                                <StatusBadge :status="job.status" />
                            </td>
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ job.created_at }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
