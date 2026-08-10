<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import StatusBadge from '@/components/status/StatusBadge.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    website: {
        id: number;
        name: string | null;
        primary_domain: string;
        domains: string[] | null;
        root_path: string | null;
        webserver: string | null;
        config_path: string | null;
        php_version: string | null;
        ssl_enabled: boolean;
        status: string;
        framework: string | null;
        framework_version: string | null;
        last_synced_at: string | null;
    };
    server: { id: number; name: string; status: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Websites', href: '/websites' },
        ],
    },
});

function confirmAction(action: string, path: string) {
    if (
        !confirm(
            `Are you sure you want to ${action} ${props.website.primary_domain}?`,
        )
    ) {
        return;
    }

    router.post(path);
}
</script>

<template>
    <Head :title="website.primary_domain" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">
                        {{ website.primary_domain }}
                    </h1>
                    <StatusBadge :status="website.status" />
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    on
                    <Link
                        :href="`/servers/${server.id}`"
                        class="hover:underline"
                        >{{ server.name }}</Link
                    >
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    @click="
                        confirmAction(
                            'restart',
                            `/websites/${website.id}/restart`,
                        )
                    "
                    >Restart</Button
                >
                <Button
                    variant="outline"
                    @click="
                        confirmAction('stop', `/websites/${website.id}/stop`)
                    "
                    >Stop</Button
                >
                <Button
                    variant="outline"
                    @click="
                        confirmAction('start', `/websites/${website.id}/start`)
                    "
                    >Start</Button
                >
                <Button variant="outline" as-child>
                    <Link
                        :href="`/servers/${server.id}/files?path=${encodeURIComponent(website.root_path || '/var/www')}`"
                        >Files</Link
                    >
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Web server</p>
                <p class="mt-2 font-medium">{{ website.webserver || '—' }}</p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">PHP</p>
                <p class="mt-2 font-medium">{{ website.php_version || '—' }}</p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">SSL</p>
                <p class="mt-2 font-medium">
                    {{ website.ssl_enabled ? 'Enabled' : 'Disabled' }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Root path</p>
                <p class="mt-2 break-all font-medium">
                    {{ website.root_path || '—' }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Config path</p>
                <p class="mt-2 break-all font-medium">
                    {{ website.config_path || '—' }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Framework</p>
                <p class="mt-2 font-medium">
                    {{ website.framework || 'Unknown' }}
                    <span v-if="website.framework_version"
                        >{{ website.framework_version }}</span
                    >
                </p>
            </div>
        </div>

        <div class="rounded-xl border p-4">
            <p class="text-sm font-medium">Aliases</p>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ (website.domains || []).join(', ') || 'None' }}
            </p>
        </div>
    </div>
</template>
