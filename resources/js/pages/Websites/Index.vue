<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StatusBadge from '@/components/status/StatusBadge.vue';

type WebsiteRow = {
    id: number;
    primary_domain: string;
    status: string;
    webserver: string | null;
    php_version: string | null;
    ssl_enabled: boolean;
    root_path: string | null;
    framework: string | null;
    last_synced_at: string | null;
    server: { id: number; name: string; status: string };
};

defineProps<{
    websites: {
        data: WebsiteRow[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Websites', href: '/websites' },
        ],
    },
});
</script>

<template>
    <Head title="Websites" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-semibold">Websites</h1>
            <p class="text-sm text-muted-foreground">
                Discovered sites across your servers
            </p>
        </div>

        <div class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr>
                        <th class="px-4 py-3">Domain</th>
                        <th class="px-4 py-3">Server</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Web server</th>
                        <th class="px-4 py-3">PHP</th>
                        <th class="px-4 py-3">SSL</th>
                        <th class="px-4 py-3">Root</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="websites.data.length === 0">
                        <td
                            colspan="7"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No websites discovered yet.
                        </td>
                    </tr>
                    <tr
                        v-for="site in websites.data"
                        :key="site.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="`/websites/${site.id}`"
                                class="font-medium hover:underline"
                            >
                                {{ site.primary_domain }}
                            </Link>
                            <p
                                v-if="site.framework"
                                class="text-xs text-muted-foreground"
                            >
                                {{ site.framework }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <Link
                                :href="`/servers/${site.server.id}`"
                                class="hover:underline"
                                >{{ site.server.name }}</Link
                            >
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge :status="site.status" />
                        </td>
                        <td class="px-4 py-3">{{ site.webserver || '—' }}</td>
                        <td class="px-4 py-3">{{ site.php_version || '—' }}</td>
                        <td class="px-4 py-3">
                            {{ site.ssl_enabled ? 'Yes' : 'No' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ site.root_path || '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
