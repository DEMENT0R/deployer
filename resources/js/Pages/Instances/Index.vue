<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeployStatusBadge from '@/Components/DeployStatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    instances: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <Head title="Instances" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Instances
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    v-if="instances.length === 0"
                    class="rounded-lg bg-white p-6 text-gray-500 shadow-sm"
                >
                    No instances available.
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="instance in instances"
                        :key="instance.id"
                        class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-gray-300 hover:shadow"
                    >
                        <div class="mb-2 flex items-start justify-between gap-2">
                            <Link
                                :href="route('instances.show', instance.id)"
                                class="font-medium text-gray-900 hover:text-indigo-700"
                            >
                                {{ instance.name }}
                            </Link>
                            <DeployStatusBadge
                                v-if="instance.latest_deployment"
                                :status="instance.latest_deployment.status"
                            />
                        </div>
                        <p class="truncate text-sm text-gray-500">
                            {{ instance.path }}
                        </p>
                        <p
                            v-if="instance.latest_deployment"
                            class="mt-3 text-xs text-gray-400"
                        >
                            Last: {{ instance.latest_deployment.action }}
                            <span v-if="instance.latest_deployment.branch">
                                ({{ instance.latest_deployment.branch }})
                            </span>
                        </p>
                        <a
                            v-if="instance.url"
                            :href="instance.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-3 inline-block truncate text-xs font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            {{ instance.url }} ↗
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
