<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    users: {
        type: Array,
        required: true,
    },
});

const destroy = (id) => {
    if (confirm('Delete this user?')) {
        router.delete(route('admin.users.destroy', id));
    }
};
</script>

<template>
    <Head title="Users" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Users
                </h2>
                <Link :href="route('admin.users.create')">
                    <PrimaryButton>Add user</PrimaryButton>
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
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Role</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="user in users" :key="user.id">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ user.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ user.role }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <Link
                                        :href="route('admin.users.edit', user.id)"
                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="ms-4 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                        @click="destroy(user.id)"
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
