<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import StatusBadge from '@/components/status/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    server: { id: number; name: string; status: string };
    path: string;
    job: {
        uuid: string;
        status: string;
        result: { entries?: Array<Record<string, unknown>> } | null;
        error_message: string | null;
    };
}>();

const pathInput = ref(props.path);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Servers', href: '/servers' },
        ],
    },
});

function browse() {
    router.get(`/servers/${props.server.id}/files`, { path: pathInput.value });
}

function confirmDelete(path: string) {
    if (!confirm(`Delete ${path}? This action cannot be undone.`)) {
        return;
    }

    router.post(`/servers/${props.server.id}/files/delete`, { path });
}
</script>

<template>
    <Head :title="`Files · ${server.name}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">File Manager</h1>
                <p class="text-sm text-muted-foreground">
                    {{ server.name }} · sandboxed browse via agent jobs
                </p>
            </div>
            <Button variant="outline" as-child>
                <Link :href="`/servers/${server.id}`">Back to server</Link>
            </Button>
        </div>

        <div class="flex gap-2">
            <Input v-model="pathInput" class="font-mono text-sm" />
            <Button @click="browse">Browse</Button>
        </div>

        <div class="rounded-xl border p-4 text-sm">
            <div class="mb-3 flex items-center gap-2">
                <span>Job</span>
                <StatusBadge :status="job.status" />
                <code class="text-xs text-muted-foreground">{{ job.uuid }}</code>
            </div>
            <p v-if="job.error_message" class="text-rose-600">
                {{ job.error_message }}
            </p>
            <p
                v-else-if="job.status === 'pending' || job.status === 'running'"
                class="text-muted-foreground"
            >
                Waiting for agent to return directory listing…
            </p>
            <div v-else class="overflow-hidden rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Mode</th>
                            <th class="px-3 py-2">Size</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in job.result?.entries || []"
                            :key="String(entry.path || entry.name)"
                            class="border-t"
                        >
                            <td class="px-3 py-2 font-mono text-xs">
                                {{ entry.name }}
                            </td>
                            <td class="px-3 py-2">
                                {{ entry.type || 'file' }}
                            </td>
                            <td class="px-3 py-2">{{ entry.mode || '—' }}</td>
                            <td class="px-3 py-2">{{ entry.size || '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <Button
                                    v-if="entry.type === 'dir'"
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        router.get(
                                            `/servers/${server.id}/files`,
                                            { path: String(entry.path) },
                                        )
                                    "
                                    >Open</Button
                                >
                                <Button
                                    v-else
                                    size="sm"
                                    variant="destructive"
                                    @click="confirmDelete(String(entry.path))"
                                    >Delete</Button
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
