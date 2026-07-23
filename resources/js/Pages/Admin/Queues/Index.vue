<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeployStatusBadge from '@/Components/DeployStatusBadge.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
    connection: String,
    summary: Object,
    deployments: Object,
    jobs: Array,
    failed_jobs: Array,
    active_deployments: Array,
    recent_deployments: Array,
});

let pollInterval = null;

onMounted(() => {
    pollInterval = setInterval(() => {
        router.reload({ preserveScroll: true });
    }, 5000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});

const retry = (uuid) => {
    router.post(route('admin.queues.retry', uuid), {}, { preserveScroll: true });
};

const forget = (uuid) => {
    if (confirm('Remove this failed job?')) {
        router.delete(route('admin.queues.forget', uuid), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Queues" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Queues &amp; Background Jobs
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <p class="text-sm text-gray-500">
                    Connection: <span class="font-mono">{{ connection }}</span>
                    · auto-refresh every 5s
                </p>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-2xl font-semibold text-gray-900">{{ summary.pending }}</div>
                        <div class="text-sm text-gray-500">Pending jobs</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-2xl font-semibold text-blue-600">{{ summary.running }}</div>
                        <div class="text-sm text-gray-500">Running jobs</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-2xl font-semibold text-gray-900">{{ summary.delayed }}</div>
                        <div class="text-sm text-gray-500">Delayed jobs</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-2xl font-semibold text-red-600">{{ summary.failed }}</div>
                        <div class="text-sm text-gray-500">Failed jobs</div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-lg font-medium">{{ deployments.pending }}</div>
                        <div class="text-sm text-gray-500">Deployments pending</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-lg font-medium text-blue-600">{{ deployments.running }}</div>
                        <div class="text-sm text-gray-500">Deployments running</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-lg font-medium text-red-600">{{ deployments.failed_recent }}</div>
                        <div class="text-sm text-gray-500">Failed deployments (24h)</div>
                    </div>
                </div>

                <div
                    v-if="active_deployments.length"
                    class="overflow-hidden rounded-lg bg-white shadow-sm"
                >
                    <div class="border-b border-gray-200 px-4 py-3 font-medium text-gray-900">
                        Active deployments
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">ID</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Instance</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">User</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Action</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Step</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="item in active_deployments" :key="item.id">
                                <td class="px-4 py-2 text-sm">
                                    <Link
                                        :href="route('instances.show', item.instance_id)"
                                        class="text-indigo-600 hover:underline"
                                    >
                                        #{{ item.id }}
                                    </Link>
                                </td>
                                <td class="px-4 py-2 text-sm">{{ item.instance }}</td>
                                <td class="px-4 py-2 text-sm">{{ item.user }}</td>
                                <td class="px-4 py-2 text-sm">{{ item.action }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <DeployStatusBadge :status="item.status" />
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ item.current_step ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-3 font-medium text-gray-900">
                        Queue jobs ({{ summary.total_in_queue }})
                    </div>
                    <div v-if="!jobs.length" class="p-4 text-sm text-gray-500">No jobs in queue.</div>
                    <table v-else class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">ID</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Job</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Queue</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Attempts</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Deployment</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="job in jobs" :key="job.id">
                                <td class="px-4 py-2 text-sm font-mono">{{ job.id }}</td>
                                <td class="px-4 py-2 text-sm">{{ job.name }}</td>
                                <td class="px-4 py-2 text-sm">{{ job.queue }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <DeployStatusBadge :status="job.status === 'running' ? 'running' : 'pending'" />
                                </td>
                                <td class="px-4 py-2 text-sm">{{ job.attempts }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <Link
                                        v-if="job.instance_id"
                                        :href="route('instances.show', job.instance_id)"
                                        class="text-indigo-600 hover:underline"
                                    >
                                        #{{ job.deployment_id }}
                                    </Link>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-3 font-medium text-gray-900">
                        Failed jobs
                    </div>
                    <div v-if="!failed_jobs.length" class="p-4 text-sm text-gray-500">No failed jobs.</div>
                    <table v-else class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Job</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Queue</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Failed at</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Error</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="job in failed_jobs" :key="job.uuid">
                                <td class="px-4 py-2 text-sm">{{ job.name }}</td>
                                <td class="px-4 py-2 text-sm">{{ job.queue }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ job.failed_at }}</td>
                                <td class="px-4 py-2 text-sm font-mono text-red-700">{{ job.exception }}</td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <SecondaryButton class="me-2" @click="retry(job.uuid)">Retry</SecondaryButton>
                                    <button
                                        type="button"
                                        class="text-red-600 hover:underline"
                                        @click="forget(job.uuid)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-3 font-medium text-gray-900">
                        Recent deployments
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">ID</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Instance</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Action</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Finished</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="item in recent_deployments" :key="item.id">
                                <td class="px-4 py-2 text-sm">
                                    <Link
                                        :href="route('instances.show', item.instance_id)"
                                        class="text-indigo-600 hover:underline"
                                    >
                                        #{{ item.id }}
                                    </Link>
                                </td>
                                <td class="px-4 py-2 text-sm">{{ item.instance }}</td>
                                <td class="px-4 py-2 text-sm">{{ item.action }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <DeployStatusBadge :status="item.status" />
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ item.finished_at ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
