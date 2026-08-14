<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted } from 'vue';

defineProps({
    instances: {
        type: Array,
        required: true,
    },
    // id инстанса → имя БД из его .env. Приезжает отдельным запросом: см. Inertia::defer.
    databases: {
        type: Object,
        default: null,
    },
});

onMounted(() => {
    router.reload({ only: ['databases'] });
});

const destroy = (id) => {
    if (confirm('Delete this instance?')) {
        router.delete(route('admin.instances.destroy', id));
    }
};

const cloneRepo = (instance) => {
    if (confirm(`Clone the repository into ${instance.path}?`)) {
        router.post(route('admin.instances.clone', instance.id));
    }
};
</script>

<template>
    <Head title="Admin Instances" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Admin Instances
                </h2>
                <Link :href="route('admin.instances.create')">
                    <PrimaryButton>Add instance</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Path</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">DB</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">URL</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Testers</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Active</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="instance in instances" :key="instance.id">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ instance.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ instance.path }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    <span v-if="!databases" class="text-gray-300 dark:text-gray-600">…</span>
                                    <span v-else-if="databases[instance.id]">
                                        {{ databases[instance.id] }}
                                    </span>
                                    <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <a
                                        v-if="instance.url"
                                        :href="instance.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="block text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        {{ instance.url }}
                                    </a>
                                    <span
                                        v-else-if="!instance.tunnel_url"
                                        class="text-gray-400 dark:text-gray-500"
                                    >
                                        —
                                    </span>
                                    <a
                                        v-if="instance.tunnel_url"
                                        :href="instance.tunnel_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="block text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        {{ instance.tunnel_url }}
                                        <span class="text-gray-400 dark:text-gray-500">(tunnel)</span>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ instance.users_count }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ instance.is_active ? 'Yes' : 'No' }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <Link
                                        :href="route('admin.instances.env', instance.id)"
                                        class="me-4 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        Env
                                    </Link>
                                    <Link
                                        :href="route('admin.instances.edit', instance.id)"
                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        Edit
                                    </Link>
                                    <Link
                                        :href="route('admin.instances.duplicate', instance.id)"
                                        class="ms-4 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        Duplicate
                                    </Link>
                                    <button
                                        v-if="instance.repository_url"
                                        type="button"
                                        class="ms-4 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        @click="cloneRepo(instance)"
                                    >
                                        Clone repo
                                    </button>
                                    <button
                                        type="button"
                                        class="ms-4 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                        @click="destroy(instance.id)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
