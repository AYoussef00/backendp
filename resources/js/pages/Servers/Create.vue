<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Servers', href: '/servers' },
            { title: 'Add Server', href: '/servers/create' },
        ],
    },
});
</script>

<template>
    <Head title="Add Server" />

    <div class="mx-auto flex w-full max-w-xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-semibold">Add Server</h1>
            <p class="text-sm text-muted-foreground">
                Generate a one-time installation token and install the ZYROX
                agent.
            </p>
        </div>

        <Form
            action="/servers"
            method="post"
            class="space-y-4 rounded-xl border p-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Server name</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    placeholder="production-01"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing"
                    >Generate install command</Button
                >
                <Button variant="outline" as-child>
                    <Link href="/servers">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
