<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    instance: {
        type: Object,
        required: true,
    },
    env: {
        type: Object,
        required: true,
    },
});

const statusLabels = {
    missing: '.env not found',
    unreadable: '.env is not readable',
    too_large: '.env is too large to parse',
    path_error: 'Instance path problem',
};

const problem = computed(() =>
    props.env.status === 'ok' ? null : statusLabels[props.env.status] ?? 'Unknown problem'
);

const formatSize = (bytes) => `${bytes} B`;

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString() : '—');
</script>

<template>
    <Head :title="`${instance.name} · .env`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ instance.name }} · .env
                </h2>
                <Link
                    :href="route('admin.instances.index')"
                    class="text-sm text-indigo-600 hover:text-indigo-800"
                >
                    Back to instances
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <dl class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">File</dt>
                            <dd class="mt-1 break-all font-mono text-sm text-gray-900">
                                {{ env.file?.path ?? `${instance.path}/.env` }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Size</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ env.file ? formatSize(env.file.size) : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Modified</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ formatDate(env.file?.modified_at) }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    v-if="problem"
                    class="rounded-lg border border-amber-200 bg-amber-50 p-4"
                >
                    <div class="font-medium text-amber-900">{{ problem }}</div>
                    <p class="mt-1 break-all font-mono text-sm text-amber-800">{{ env.message }}</p>
                </div>

                <div v-else class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-3 font-medium text-gray-900">
                        Environment
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Key</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="variable in env.variables" :key="variable.key">
                                <td class="px-4 py-2 font-mono text-sm text-gray-900">{{ variable.key }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <span v-if="!variable.present" class="text-gray-400">not set</span>
                                    <span v-else-if="variable.value === ''" class="text-gray-400">empty</span>
                                    <span v-else class="break-all font-mono text-gray-900">{{ variable.value }}</span>
                                    <span
                                        v-if="variable.masked"
                                        class="ms-2 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600"
                                    >
                                        masked
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="border-t border-gray-200 px-4 py-3 text-sm text-gray-500">
                        Only keys from <span class="font-mono">deployer.env_visible_keys</span> are shown;
                        secrets are masked before leaving the server.
                        <template v-if="env.hidden_count">
                            {{ env.hidden_count }} other variable(s) in this file are not displayed.
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
