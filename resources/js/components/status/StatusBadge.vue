<script setup lang="ts">
defineProps<{
    status: string;
}>();

const colorMap: Record<string, string> = {
    online: 'bg-emerald-500',
    active: 'bg-emerald-500',
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    pending: 'bg-amber-500',
    running: 'bg-sky-500',
    offline: 'bg-rose-500',
    error: 'bg-rose-500',
    failed: 'bg-rose-500',
    disabled: 'bg-zinc-400',
    unknown: 'bg-zinc-400',
    expired: 'bg-zinc-400',
    cancelled: 'bg-zinc-400',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-2 rounded-md border px-2 py-0.5 text-xs font-medium capitalize"
    >
        <span
            class="size-2 rounded-full"
            :class="colorMap[status] ?? 'bg-zinc-400'"
        />
        {{ status }}
    </span>
</template>
