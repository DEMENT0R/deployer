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
                    <Link
                        v-for="instance in instances"
                        :key="instance.id"
                        :href="route('instances.show', instance.id)"
                        class="block rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-gray-300 hover:shadow"
                    >
                        <div class="mb-2 flex items-start justify-between gap-2">
                            <h3 class="font-medium text-gray-900">
                                {{ instance.name }}
                            </h3>
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
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
