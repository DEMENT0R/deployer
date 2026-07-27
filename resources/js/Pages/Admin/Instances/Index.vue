<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    instances: {
        type: Array,
        required: true,
    },
});

const destroy = (id) => {
    if (confirm('Delete this instance?')) {
        router.delete(route('admin.instances.destroy', id));
    }
};
</script>

<template>
    <Head title="Admin Instances" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Admin Instances
                </h2>
                <Link :href="route('admin.instances.create')">
                    <PrimaryButton>Add instance</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Path</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Testers</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Active</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="instance in instances" :key="instance.id">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ instance.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ instance.path }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ instance.users_count }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ instance.is_active ? 'Yes' : 'No' }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <Link
                                        :href="route('admin.instances.env', instance.id)"
                                        class="me-4 text-indigo-600 hover:text-indigo-800"
                                    >
                                        Env
                                    </Link>
                                    <Link
                                        :href="route('admin.instances.edit', instance.id)"
                                        class="text-indigo-600 hover:text-indigo-800"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="ms-4 text-red-600 hover:text-red-800"
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
