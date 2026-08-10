<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import StatusBadge from '@/components/status/StatusBadge.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    server: { id: number; name: string; status: string };
    source: string;
    lines: number;
    sources: string[];
    job: {
        uuid: string;
        status: string;
        result: { lines?: string[] } | null;
        error_message: string | null;
    };
}>();

const source = ref(props.source);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Servers', href: '/servers' },
        ],
    },
});

function loadLogs() {
    router.get(`/servers/${props.server.id}/logs`, {
        source: source.value,
        lines: props.lines,
    });
}
</script>

<template>
    <Head :title="`Logs · ${server.name}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Logs</h1>
                <p class="text-sm text-muted-foreground">{{ server.name }}</p>
            </div>
            <Button variant="outline" as-child>
                <Link :href="`/servers/${server.id}`">Back</Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <select
                v-model="source"
                class="h-9 rounded-md border bg-background px-3 text-sm"
            >
                <option v-for="item in sources" :key="item" :value="item">
                    {{ item }}
                </option>
            </select>
            <Button @click="loadLogs">Load</Button>
            <StatusBadge :status="job.status" />
        </div>

        <pre
            class="min-h-80 overflow-auto rounded-xl border bg-zinc-950 p-4 text-xs text-zinc-100"
            >{{
                job.error_message ||
                (job.result?.lines || []).join('\n') ||
                'Waiting for agent…'
            }}</pre
        >
    </div>
</template>
