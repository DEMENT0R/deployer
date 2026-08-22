<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    entries: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    actions: {
        type: Array,
        default: () => [],
    },
});

const rows = computed(() => props.entries.data ?? []);

const pages = computed(() =>
    (props.entries.links ?? []).filter((link) => link.url || link.active),
);

const filterBy = (action) => {
    router.get(
        route('admin.audit.index'),
        action ? { action } : {},
        { preserveScroll: true, preserveState: true },
    );
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString() : '—');
</script>

<template>
    <Head title="Audit" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Audit
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    Everything changed outside a deployment: instances, users, screen sessions and
                    the <span class="font-mono">.env</span> of a stand. Deployments themselves are on
                    the
                    <Link
                        :href="route('activity.index')"
                        class="text-indigo-600 hover:underline dark:text-indigo-400"
                        >Activity</Link
                    >
                    page.
                </p>

                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-medium transition"
                        :class="
                            !filters.action
                                ? 'bg-indigo-600 text-white'
                                : 'bg-white text-gray-600 shadow-sm hover:text-gray-900 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-100'
                        "
                        @click="filterBy(null)"
                    >
                        All
                    </button>
                    <button
                        v-for="action in actions"
                        :key="action"
                        type="button"
                        class="rounded-full px-3 py-1 font-mono text-xs transition"
                        :class="
                            filters.action === action
                                ? 'bg-indigo-600 text-white'
                                : 'bg-white text-gray-600 shadow-sm hover:text-gray-900 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-100'
                        "
                        @click="filterBy(action)"
                    >
                        {{ action }}
                    </button>
                </div>

                <div
                    v-if="rows.length === 0"
                    class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow-sm dark:bg-gray-800 dark:text-gray-400"
                >
                    Nothing recorded yet.
                </div>

                <div v-else class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">When</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Who</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Action</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Subject</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="entry in rows"
                                    :key="entry.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                >
                                    <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(entry.created_at) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                        {{ entry.user ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 font-mono text-sm text-gray-900 dark:text-gray-100">
                                        {{ entry.action }}
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <Link
                                            v-if="entry.instance"
                                            :href="route('instances.show', entry.instance.id)"
                                            class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            {{ entry.subject ?? entry.instance.name }}
                                        </Link>
                                        <span v-else class="text-gray-900 dark:text-gray-100">
                                            {{ entry.subject ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                        {{ entry.summary ?? '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="pages.length > 3" class="mt-4 flex flex-wrap gap-1">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="link in pages"
                        :key="link.label"
                        :href="link.url"
                        class="rounded px-3 py-1 text-sm"
                        :class="
                            link.active
                                ? 'bg-indigo-600 text-white'
                                : 'bg-white text-gray-600 shadow-sm dark:bg-gray-800 dark:text-gray-400'
                        "
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
