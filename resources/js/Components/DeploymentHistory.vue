<script setup>
import DeployStatusBadge from '@/Components/DeployStatusBadge.vue';

defineProps({
    deployments: {
        type: Array,
        default: () => [],
    },
    selectedId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['select']);

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString() : '—');

const formatDuration = (seconds) => {
    if (seconds === null || seconds === undefined) return '—';
    if (seconds < 60) return `${seconds}s`;

    return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
};
</script>

<template>
    <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
        <div
            class="flex items-baseline justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700"
        >
            <span class="font-medium text-gray-900 dark:text-gray-100">Recent deployments</span>
            <span v-if="deployments.length" class="text-xs text-gray-500 dark:text-gray-400">
                Click a row to see its log
            </span>
        </div>

        <div
            v-if="deployments.length === 0"
            class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"
        >
            Nothing has been deployed to this instance yet.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">When</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Action</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Branch</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">By</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Took</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr
                        v-for="deployment in deployments"
                        :key="deployment.id"
                        class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50"
                        :class="{
                            'bg-indigo-50 dark:bg-indigo-900/30':
                                deployment.id === selectedId,
                        }"
                        tabindex="0"
                        @click="emit('select', deployment)"
                        @keydown.enter="emit('select', deployment)"
                    >
                        <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ formatDate(deployment.started_at ?? deployment.finished_at) }}
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
                                v-if="deployment.exit_code"
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
</template>
