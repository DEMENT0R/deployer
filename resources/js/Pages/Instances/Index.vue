<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeployStatusBadge from '@/Components/DeployStatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, reactive } from 'vue';

const props = defineProps({
    instances: {
        type: Array,
        required: true,
    },
    // id инстанса → имя БД из его .env. Приезжает отдельным запросом: см. Inertia::defer.
    databases: {
        type: Object,
        default: null,
    },
    // id инстанса → размеры логов, кэшей, загрузок и дампов. Тоже отложенный, своей группой.
    disk: {
        type: Object,
        default: null,
    },
    // Разделы, на которых лежат инстансы и дампы: свободно / всего. Та же отложенная группа.
    volumes: {
        type: Array,
        default: null,
    },
});

// Меньше десятой части раздела — пора чистить, пока деплой не упал посреди npm install.
const isLow = (volume) => volume.total > 0 && volume.free / volume.total < 0.1;

const diskAreas = [
    { key: 'logs', label: 'Logs' },
    { key: 'cache', label: 'Cache' },
    { key: 'uploads', label: 'Uploads' },
    { key: 'backups', label: 'Backups' },
];

const formatSize = (bytes) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 ** 2) return `${Math.round(bytes / 1024)} KB`;
    if (bytes < 1024 ** 3) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
    return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
};

const dependencyAreas = [
    { key: 'vendor', label: 'vendor' },
    { key: 'node_modules', label: 'node_modules' },
];

// id инстанса → { loading, error, data }. vendor и node_modules считаются только по кнопке:
// обход занимает секунды, а нужен редко.
const dependencies = reactive({});

const loadDependencies = async (id) => {
    dependencies[id] = { loading: true, error: '', data: dependencies[id]?.data ?? null };

    try {
        const { data } = await axios.get(route('instances.disk.dependencies', id));
        dependencies[id].data = data;
    } catch (error) {
        dependencies[id].error = error.response?.data?.message ?? 'Failed to count dependencies.';
    } finally {
        dependencies[id].loading = false;
    }
};

const clearingLogs = reactive({});

const clearLogs = async (instance) => {
    if (!confirm(`Clear the logs of ${instance.name}? Files written within the last day are emptied, older ones are deleted.`)) return;

    clearingLogs[instance.id] = true;

    try {
        await axios.delete(route('instances.log.destroy', instance.id));
        router.reload({ only: ['disk', 'volumes'] });
    } catch (error) {
        alert(error.response?.data?.message ?? 'Failed to clear the logs.');
    } finally {
        clearingLogs[instance.id] = false;
    }
};

const sumAreas = (usage, areas) => areas.reduce((sum, { key }) => sum + (usage?.[key]?.bytes ?? 0), 0);

const instanceTotal = (id) =>
    sumAreas(props.disk?.[id], diskAreas) + sumAreas(dependencies[id]?.data, dependencyAreas);

const grandTotal = computed(() =>
    props.disk ? Object.keys(props.disk).reduce((sum, id) => sum + instanceTotal(id), 0) : null,
);

const formatMoment = (value) =>
    new Date(value).toLocaleString(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    });

onMounted(() => {
    router.reload({ only: ['databases'] });
});
</script>

<template>
    <Head title="Instances" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Instances
                </h2>
                <div class="flex flex-wrap items-baseline justify-end gap-x-4 gap-y-1 text-sm">
                    <span
                        v-if="grandTotal !== null && instances.length > 0"
                        class="text-gray-500 dark:text-gray-400"
                        title="Logs, caches, uploads and DB dumps of all listed instances; vendor and node_modules only where counted"
                    >
                        Disk: {{ formatSize(grandTotal) }}
                    </span>
                    <span
                        v-for="volume in volumes ?? []"
                        :key="volume.path"
                        :class="
                            isLow(volume)
                                ? 'font-medium text-red-600 dark:text-red-400'
                                : 'text-gray-500 dark:text-gray-400'
                        "
                        :title="`Partition of ${volume.path}`"
                    >
                        Free: {{ formatSize(volume.free) }} of {{ formatSize(volume.total) }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    v-if="instances.length === 0"
                    class="rounded-lg bg-white p-6 text-gray-500 shadow-sm dark:bg-gray-800 dark:text-gray-400"
                >
                    No instances available.
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="instance in instances"
                        :key="instance.id"
                        class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-gray-300 hover:shadow dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600"
                    >
                        <div class="mb-2 flex items-start justify-between gap-2">
                            <Link
                                :href="route('instances.show', instance.id)"
                                class="font-medium text-gray-900 hover:text-indigo-700 dark:text-gray-100 dark:hover:text-indigo-400"
                            >
                                {{ instance.name }}
                            </Link>
                            <DeployStatusBadge
                                v-if="instance.latest_deployment"
                                :status="instance.latest_deployment.status"
                            />
                        </div>
                        <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                            {{ instance.path }}
                        </p>
                        <p
                            v-if="instance.hold"
                            class="mt-2 truncate rounded bg-amber-50 px-2 py-1 text-xs text-amber-900 dark:bg-amber-900/30 dark:text-amber-200"
                        >
                            Taken by {{ instance.hold.user }} until
                            {{ formatMoment(instance.hold.until) }}
                            <span v-if="instance.hold.note">— {{ instance.hold.note }}</span>
                        </p>
                        <div class="mt-3 space-y-1 text-xs text-gray-400 dark:text-gray-500">
                            <p v-if="instance.latest_deployment">
                                Last: {{ instance.latest_deployment.action }}
                                <span v-if="instance.latest_deployment.branch">
                                    ({{ instance.latest_deployment.branch }})
                                </span>
                            </p>
                            <p class="truncate">
                                DB:
                                <span v-if="!databases" class="text-gray-300 dark:text-gray-600">…</span>
                                <span v-else>{{ databases[instance.id] ?? 'unknown' }}</span>
                            </p>
                        </div>
                        <div class="mt-3 border-t border-gray-100 pt-3 text-xs dark:border-gray-700">
                            <p v-if="!disk" class="text-gray-300 dark:text-gray-600">Disk usage…</p>
                            <p v-else-if="!disk[instance.id]" class="text-gray-400 dark:text-gray-500">
                                Disk usage unknown: instance path is not readable.
                            </p>
                            <dl v-else class="grid grid-cols-2 gap-x-4 gap-y-1">
                                <div
                                    v-for="area in diskAreas"
                                    :key="area.key"
                                    class="flex justify-between gap-2"
                                >
                                    <dt class="text-gray-400 dark:text-gray-500">{{ area.label }}</dt>
                                    <dd
                                        v-if="disk[instance.id][area.key]"
                                        class="text-gray-700 dark:text-gray-300"
                                        :title="`${disk[instance.id][area.key].files} files${disk[instance.id][area.key].partial ? ', counting stopped early' : ''}`"
                                    >
                                        <span v-if="disk[instance.id][area.key].partial">≥ </span>{{ formatSize(disk[instance.id][area.key].bytes) }}
                                        <button
                                            v-if="area.key === 'logs' && instance.can_clear_log && disk[instance.id].logs.bytes > 0"
                                            type="button"
                                            class="ml-1 font-medium text-red-600 hover:text-red-800 disabled:cursor-wait disabled:opacity-50 dark:text-red-400 dark:hover:text-red-300"
                                            :disabled="clearingLogs[instance.id]"
                                            title="Empty logs written within the last day, delete older ones"
                                            @click="clearLogs(instance)"
                                        >
                                            clear
                                        </button>
                                        <span
                                            v-if="area.key === 'backups'"
                                            class="text-gray-400 dark:text-gray-500"
                                        >({{ disk[instance.id][area.key].files }})</span>
                                    </dd>
                                    <dd v-else class="text-gray-300 dark:text-gray-600">—</dd>
                                </div>
                                <template v-if="dependencies[instance.id]?.data">
                                    <div
                                        v-for="area in dependencyAreas"
                                        :key="area.key"
                                        class="flex justify-between gap-2"
                                    >
                                        <dt class="truncate text-gray-400 dark:text-gray-500">{{ area.label }}</dt>
                                        <dd
                                            v-if="dependencies[instance.id].data[area.key]"
                                            class="whitespace-nowrap text-gray-700 dark:text-gray-300"
                                            :title="`${dependencies[instance.id].data[area.key].files} files${dependencies[instance.id].data[area.key].partial ? ', counting stopped early' : ''}`"
                                        >
                                            <span v-if="dependencies[instance.id].data[area.key].partial">≥ </span>{{ formatSize(dependencies[instance.id].data[area.key].bytes) }}
                                        </dd>
                                        <dd v-else class="text-gray-300 dark:text-gray-600">—</dd>
                                    </div>
                                </template>
                                <div class="col-span-2 flex justify-between gap-2 font-medium">
                                    <dt class="text-gray-500 dark:text-gray-400">Total</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">
                                        {{ formatSize(instanceTotal(instance.id)) }}
                                    </dd>
                                </div>
                            </dl>
                            <div v-if="disk && disk[instance.id]" class="mt-2">
                                <button
                                    type="button"
                                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800 disabled:cursor-wait disabled:opacity-50 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    :disabled="dependencies[instance.id]?.loading"
                                    @click="loadDependencies(instance.id)"
                                >
                                    <template v-if="dependencies[instance.id]?.loading">Counting vendor and node_modules…</template>
                                    <template v-else-if="dependencies[instance.id]?.data">Recount vendor and node_modules</template>
                                    <template v-else>Count vendor and node_modules</template>
                                </button>
                                <p
                                    v-if="dependencies[instance.id]?.error"
                                    class="mt-1 text-red-600 dark:text-red-400"
                                >
                                    {{ dependencies[instance.id].error }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 space-y-1">
                            <a
                                v-if="instance.url"
                                :href="instance.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block truncate text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                            >
                                {{ instance.url }} ↗
                            </a>
                            <a
                                v-if="instance.tunnel_url"
                                :href="instance.tunnel_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block truncate text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                            >
                                {{ instance.tunnel_url }} ↗ <span class="text-gray-400 dark:text-gray-500">(tunnel)</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
