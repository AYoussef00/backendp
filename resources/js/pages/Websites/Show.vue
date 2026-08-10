<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import StatusBadge from '@/components/status/StatusBadge.vue';
import { Button } from '@/components/ui/button';

type LatestJob = {
    uuid: string;
    type: string;
    status: string;
    error_code: string | null;
    error_message: string | null;
    completed_at: string | null;
};

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
    latest_job: LatestJob | null;
    control_mode?: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Websites', href: '/websites' },
        ],
    },
});

const busy = computed(() => {
    const status = props.latest_job?.status;
    return status === 'pending' || status === 'running';
});

const jobLabel = computed(() => {
    const type = props.latest_job?.type ?? '';
    return (
        {
            website_start: 'Starting website…',
            website_stop: 'Stopping website…',
            website_restart: 'Restarting website…',
            website_enable: 'Enabling website…',
            website_disable: 'Disabling website…',
        }[type] ?? 'Applying changes…'
    );
});

let pollTimer: ReturnType<typeof setInterval> | null = null;
const lastNotifiedUuid = ref<string | null>(null);
const pollStartedAt = ref<number | null>(null);

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    pollStartedAt.value = null;
}

function startPolling() {
    if (pollTimer) {
        return;
    }

    pollStartedAt.value = Date.now();

    pollTimer = setInterval(() => {
        if (
            pollStartedAt.value &&
            Date.now() - pollStartedAt.value > 90_000
        ) {
            stopPolling();
            toast.error(
                'Remote agent did not respond. Check syshealthd on that server.',
            );
            return;
        }

        router.reload({
            only: ['website', 'latest_job', 'server'],
            preserveScroll: true,
            preserveState: true,
        });
    }, 1000);
}

function notifyJobResult(job: LatestJob) {
    if (lastNotifiedUuid.value === `${job.uuid}:${job.status}`) {
        return;
    }

    lastNotifiedUuid.value = `${job.uuid}:${job.status}`;

    if (job.status === 'success') {
        const message =
            {
                website_start: 'Website started',
                website_stop: 'Website stopped',
                website_restart: 'Website restarted',
                website_enable: 'Website enabled',
                website_disable: 'Website disabled',
            }[job.type] ?? 'Action completed';
        toast.success(message);
        return;
    }

    if (job.status === 'failed' || job.status === 'expired') {
        toast.error(job.error_message || 'Action failed on the server');
    }
}

watch(
    () => props.latest_job,
    (job, previous) => {
        if (job && (job.status === 'pending' || job.status === 'running')) {
            startPolling();
            return;
        }

        stopPolling();

        const wasBusy =
            previous &&
            (previous.status === 'pending' || previous.status === 'running');

        if (
            wasBusy &&
            job &&
            (job.status === 'success' ||
                job.status === 'failed' ||
                job.status === 'expired')
        ) {
            notifyJobResult(job);
        }
    },
    { immediate: true, deep: true },
);

onUnmounted(() => {
    stopPolling();
});

function confirmAction(action: string, path: string) {
    if (busy.value) {
        return;
    }

    if (
        !confirm(
            `Are you sure you want to ${action} ${props.website.primary_domain}?`,
        )
    ) {
        return;
    }

    router.post(path, {}, {
        preserveScroll: true,
        onSuccess: () => {
            // Ensure polling starts immediately after an agent-backed action.
            if (
                props.latest_job &&
                (props.latest_job.status === 'pending' ||
                    props.latest_job.status === 'running')
            ) {
                startPolling();
            }
        },
    });
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
                <p
                    v-if="busy"
                    class="mt-2 text-sm text-amber-600 dark:text-amber-400"
                >
                    {{ jobLabel }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    :disabled="busy"
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
                    :disabled="busy"
                    @click="
                        confirmAction('stop', `/websites/${website.id}/stop`)
                    "
                    >Stop</Button
                >
                <Button
                    variant="outline"
                    :disabled="busy"
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
