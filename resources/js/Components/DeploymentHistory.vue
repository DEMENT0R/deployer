<script setup>
import DeployStatusBadge from '@/Components/DeployStatusBadge.vue';

defineProps({
    deployments: {
        type: Array,
        default: () => [],
    },
});

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString() : '—');

const formatDuration = (seconds) => {
    if (seconds === null || seconds === undefined) return '—';
    if (seconds < 60) return `${seconds}s`;

    return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
};
</script>

<template>
    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
        <div class="border-b border-gray-200 px-6 py-4 font-medium text-gray-900">
            Recent deployments
        </div>

        <div
            v-if="deployments.length === 0"
            class="px-6 py-4 text-sm text-gray-500"
        >
            Nothing has been deployed to this instance yet.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">When</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Action</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Branch</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">By</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Took</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="deployment in deployments" :key="deployment.id">
                        <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                            {{ formatDate(deployment.started_at ?? deployment.finished_at) }}
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-900">{{ deployment.action }}</td>
                        <td class="px-4 py-2 font-mono text-sm text-gray-900">
                            {{ deployment.branch ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-500">{{ deployment.user ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                            {{ formatDuration(deployment.duration_seconds) }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-2">
                            <DeployStatusBadge :status="deployment.status" />
                            <span
                                v-if="deployment.exit_code"
                                class="ms-2 text-xs text-gray-500"
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
