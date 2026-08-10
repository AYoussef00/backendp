<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StatusBadge from '@/components/status/StatusBadge.vue';

defineProps<{
    jobs: {
        data: Array<{
            id: number;
            uuid: string;
            type: string;
            status: string;
            error_message: string | null;
            created_at: string | null;
            completed_at: string | null;
            server: { id: number; name: string } | null;
            website: { id: number; primary_domain: string } | null;
            creator: { id: number; name: string } | null;
        }>;
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
    <Head title="Jobs" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-semibold">Jobs</h1>
            <p class="text-sm text-muted-foreground">
                Agent job queue and execution history
            </p>
        </div>

        <div class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Server</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3">Completed</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="job in jobs.data" :key="job.id" class="border-t">
                        <td class="px-4 py-3">
                            <Link
                                :href="`/jobs/${job.id}`"
                                class="font-medium hover:underline"
                                >{{ job.type }}</Link
                            >
                        </td>
                        <td class="px-4 py-3">
                            {{ job.server?.name || '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge :status="job.status" />
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ job.created_at }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ job.completed_at || '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
