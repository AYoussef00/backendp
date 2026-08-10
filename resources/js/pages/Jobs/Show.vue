<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StatusBadge from '@/components/status/StatusBadge.vue';

defineProps<{
    job: {
        id: number;
        uuid: string;
        type: string;
        status: string;
        payload: Record<string, unknown> | null;
        result: Record<string, unknown> | null;
        error_code: string | null;
        error_message: string | null;
        attempts: number;
        created_at: string | null;
        started_at: string | null;
        completed_at: string | null;
        server: { id: number; name: string; status: string } | null;
        website: { id: number; primary_domain: string } | null;
        creator: { id: number; name: string } | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Jobs', href: '/jobs' },
        ],
    },
});
</script>

<template>
    <Head :title="`Job ${job.uuid}`" />

    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">{{ job.type }}</h1>
                <p class="font-mono text-xs text-muted-foreground">
                    {{ job.uuid }}
                </p>
            </div>
            <StatusBadge :status="job.status" />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border p-4 text-sm">
                <p class="text-muted-foreground">Server</p>
                <Link
                    v-if="job.server"
                    :href="`/servers/${job.server.id}`"
                    class="mt-1 font-medium hover:underline"
                    >{{ job.server.name }}</Link
                >
            </div>
            <div class="rounded-xl border p-4 text-sm">
                <p class="text-muted-foreground">Attempts</p>
                <p class="mt-1 font-medium">{{ job.attempts }}</p>
            </div>
        </div>

        <div v-if="job.error_message" class="rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm dark:bg-rose-950/30">
            <p class="font-medium">{{ job.error_code }}</p>
            <p class="mt-1">{{ job.error_message }}</p>
        </div>

        <div class="rounded-xl border p-4">
            <p class="mb-2 text-sm font-medium">Payload</p>
            <pre class="overflow-x-auto rounded-lg bg-muted p-3 text-xs">{{
                JSON.stringify(job.payload, null, 2)
            }}</pre>
        </div>

        <div class="rounded-xl border p-4">
            <p class="mb-2 text-sm font-medium">Result</p>
            <pre class="overflow-x-auto rounded-lg bg-muted p-3 text-xs">{{
                JSON.stringify(job.result, null, 2)
            }}</pre>
        </div>
    </div>
</template>
