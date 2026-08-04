<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeployStatusBadge from '@/Components/DeployStatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    deployments: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    statuses: {
        type: Array,
        default: () => [],
    },
});

const rows = computed(() => props.deployments.data ?? []);

const pages = computed(() =>
    (props.deployments.links ?? []).filter(
        (link) => link.url || link.active,
    ),
);

const filterBy = (status) => {
    router.get(
        route('activity.index'),
        status ? { status } : {},
        { preserveScroll: true, preserveState: true },
    );
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString() : '—');

const formatDuration = (seconds) => {
    if (seconds === null || seconds === undefined) return '—';
    if (seconds < 60) return `${seconds}s`;

    return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
};
</script>

<template>
    <Head title="Activity" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Activity
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-medium transition"
                        :class="
                            !filters.status
                                ? 'bg-indigo-600 text-white'
                                : 'bg-white text-gray-600 shadow-sm hover:text-gray-900 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-100'
                        "
                        @click="filterBy(null)"
                    >
                        All
                    </button>
                    <button
                        v-for="status in statuses"
                        :key="status"
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-medium capitalize transition"
                        :class="
                            filters.status === status
                                ? 'bg-indigo-600 text-white'
                                : 'bg-white text-gray-600 shadow-sm hover:text-gray-900 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-100'
                        "
                        @click="filterBy(status)"
                    >
                        {{ status }}
                    </button>
                </div>

                <div
                    v-if="rows.length === 0"
                    class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow-sm dark:bg-gray-800 dark:text-gray-400"
                >
                    No deployments yet.
                </div>

                <div
                    v-else
                    class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800"
                >
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">When</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Instance</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Action</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Branch</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">By</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Took</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="deployment in rows"
                                    :key="deployment.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                >
                                    <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(deployment.started_at ?? deployment.created_at) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm">
                                        <Link
                                            :href="route('instances.show', deployment.instance.id)"
                                            class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            {{ deployment.instance.name }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ deployment.action }}</td>
                                    <td class="px-4 py-2 font-mono text-sm text-gray-900 dark:text-gray-100">
                                        {{ deployment.branch ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ deployment.user ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDuration(deployment.duration_seconds) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2">
                                        <DeployStatusBadge :status="deployment.status" />
                                        <span
                                            v-if="deployment.is_stale"
                                            class="ms-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                        >
                                            stale
                                        </span>
                                        <span
                                            v-else-if="deployment.exit_code"
                                            class="ms-2 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            exit {{ deployment.exit_code }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="pages.length > 3" class="mt-4 flex flex-wrap gap-1">
                    <template v-for="(link, index) in pages" :key="index">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="rounded px-3 py-1 text-sm"
                            :class="
                                link.active
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-600 shadow-sm hover:text-gray-900 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-100'
                            "
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="rounded px-3 py-1 text-sm text-gray-400 dark:text-gray-500"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
