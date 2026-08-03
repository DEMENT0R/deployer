<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Deferred, Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    filter: {
        type: String,
        default: null,
    },
    instances: {
        type: Array,
        default: () => [],
    },
    screen: {
        type: Object,
        default: null,
    },
});

let pollInterval = null;

onMounted(() => {
    pollInterval = setInterval(() => {
        router.reload({ only: ['screen'], preserveScroll: true });
    }, 10000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});

const filterByUser = (event) => {
    const user = event.target.value;

    router.get(
        route('admin.screens.index'),
        user ? { user } : {},
        { preserveScroll: true, preserveState: true },
    );
};

const formatUptime = (seconds) => {
    if (seconds < 60) return `${seconds}s`;

    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (days) return `${days}d ${hours}h`;
    if (hours) return `${hours}h ${minutes}m`;

    return `${minutes}m`;
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString() : '—');

const startForm = useForm({ instance_id: '' });
const stopForm = useForm({ session: '' });

// Сессия «жива», если её имя есть в текущем списке. Фильтр по пользователю список сужает,
// поэтому при выбранном фильтре запущенный чужой стенд может выглядеть остановленным.
const runningSessions = computed(
    () => new Set((props.screen?.sessions ?? []).map((session) => session.name).filter(Boolean)),
);

const instanceBySession = computed(() => {
    const map = {};
    props.instances.forEach((instance) => {
        map[instance.screen_session] = instance;
    });

    return map;
});

const stoppedInstances = computed(() =>
    props.instances.filter((instance) => !runningSessions.value.has(instance.screen_session)),
);

const start = () => {
    startForm.post(route('admin.screens.store'), {
        preserveScroll: true,
        onSuccess: () => startForm.reset(),
    });
};

const stop = (session) => {
    if (!confirm(`Stop screen session "${session}"? Anything running inside it is killed.`)) return;

    stopForm.session = session;
    stopForm.delete(route('admin.screens.destroy'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Screens" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Screen sessions
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <Deferred data="screen">
                    <template #fallback>
                        <div class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow-sm">
                            Listing screen sessions…
                        </div>
                    </template>

                    <div class="space-y-6">
                        <div
                            v-if="!screen.available"
                            class="rounded-lg bg-white p-6 shadow-sm"
                        >
                            <div class="font-medium text-gray-900">Not available on this host</div>
                            <p class="mt-1 text-sm text-gray-500">{{ screen.message }}</p>
                        </div>

                        <template v-else>
                            <div
                                class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-4 shadow-sm"
                            >
                                <div class="flex items-center gap-2">
                                    <label for="user" class="text-sm text-gray-600">User</label>
                                    <select
                                        id="user"
                                        :value="filter ?? ''"
                                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        @change="filterByUser"
                                    >
                                        <option value="">All users</option>
                                        <option
                                            v-for="user in screen.users"
                                            :key="user.name"
                                            :value="user.name"
                                        >
                                            {{ user.name }} ({{ user.count }}){{ user.hidden_by_default ? ' — hidden by default' : '' }}
                                        </option>
                                    </select>
                                </div>

                                <p class="text-sm text-gray-500">
                                    {{ screen.sessions.length }} session(s)
                                    <span v-if="!filter && screen.hidden_count">
                                        · {{ screen.hidden_count }} hidden
                                        ({{ screen.hidden_users.join(', ') }})
                                    </span>
                                    · auto-refresh every 10s
                                </p>
                            </div>

                            <div class="rounded-lg bg-white p-4 shadow-sm">
                                <div class="flex flex-wrap items-end gap-3">
                                    <div class="min-w-[16rem] flex-1">
                                        <label for="instance_id" class="block text-sm text-gray-600">
                                            Start an instance
                                        </label>
                                        <select
                                            id="instance_id"
                                            v-model="startForm.instance_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            :disabled="!stoppedInstances.length"
                                        >
                                            <option value="">
                                                {{ stoppedInstances.length ? 'Pick an instance…' : 'Nothing to start' }}
                                            </option>
                                            <option
                                                v-for="instance in stoppedInstances"
                                                :key="instance.id"
                                                :value="instance.id"
                                            >
                                                {{ instance.name }} — {{ instance.screen_session }}:{{ instance.serve_port }}
                                            </option>
                                        </select>
                                    </div>
                                    <PrimaryButton
                                        :disabled="!startForm.instance_id || startForm.processing"
                                        @click="start"
                                    >
                                        {{ startForm.processing ? 'Starting…' : 'Start' }}
                                    </PrimaryButton>
                                </div>

                                <p v-if="!instances.length" class="mt-2 text-xs text-gray-500">
                                    No instance has a screen session name and a serve port yet — set both in
                                    Admin → Instances to start it from here.
                                </p>
                                <p v-else-if="!stoppedInstances.length" class="mt-2 text-xs text-gray-500">
                                    Every configured instance is already running.
                                </p>

                                <InputError class="mt-2" :message="startForm.errors.screen" />
                                <InputError class="mt-2" :message="startForm.errors.instance_id" />
                                <InputError class="mt-2" :message="stopForm.errors.screen" />
                                <InputError class="mt-2" :message="stopForm.errors.session" />
                            </div>

                            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                                <div
                                    v-if="!screen.sessions.length"
                                    class="p-4 text-sm text-gray-500"
                                >
                                    No screen sessions found.
                                    <span v-if="!filter && screen.hidden_count">
                                        Sessions of {{ screen.hidden_users.join(', ') }} are hidden —
                                        pick the user above to see them.
                                    </span>
                                </div>
                                <table v-else class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">PID</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">User</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Session</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Instance</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Started</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Uptime</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Command</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="session in screen.sessions" :key="session.pid">
                                            <td class="px-4 py-2 font-mono text-sm text-gray-900">{{ session.pid }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900">{{ session.user }}</td>
                                            <td class="px-4 py-2 font-mono text-sm text-gray-900">
                                                {{ session.name ?? '—' }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2 text-sm">
                                                <Link
                                                    v-if="instanceBySession[session.name]"
                                                    :href="route('instances.show', instanceBySession[session.name].id)"
                                                    class="text-indigo-600 hover:text-indigo-900"
                                                >
                                                    {{ instanceBySession[session.name].name }}
                                                    <span class="text-gray-400">
                                                        :{{ instanceBySession[session.name].serve_port }}
                                                    </span>
                                                </Link>
                                                <span v-else class="text-gray-400">—</span>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                                                {{ formatDate(session.started_at) }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                                                {{ formatUptime(session.uptime_seconds) }}
                                            </td>
                                            <td class="px-4 py-2 font-mono text-xs text-gray-500">
                                                {{ session.command }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                                <DangerButton
                                                    v-if="session.name"
                                                    :disabled="stopForm.processing"
                                                    @click="stop(session.name)"
                                                >
                                                    Stop
                                                </DangerButton>
                                                <span v-else class="text-xs text-gray-400">
                                                    unnamed
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <p class="text-xs text-gray-400">
                                Read from the process table, so sessions of every user on this host are
                                listed — but whether a session is attached is not shown: that state lives
                                in the screen socket, which only its owner can read. For the same reason
                                Stop only works on sessions owned by the user the panel runs as; stopping
                                anyone else's session fails with an error.
                            </p>
                        </template>
                    </div>
                </Deferred>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
