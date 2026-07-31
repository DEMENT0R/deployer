<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Deferred, Head, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
    filter: {
        type: String,
        default: null,
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
                                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Started</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Uptime</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Command</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="session in screen.sessions" :key="session.pid">
                                            <td class="px-4 py-2 font-mono text-sm text-gray-900">{{ session.pid }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900">{{ session.user }}</td>
                                            <td class="px-4 py-2 font-mono text-sm text-gray-900">
                                                {{ session.name ?? '—' }}
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
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <p class="text-xs text-gray-400">
                                Read from the process table, so sessions of every user on this host are
                                listed — but whether a session is attached is not shown: that state lives
                                in the screen socket, which only its owner can read.
                            </p>
                        </template>
                    </div>
                </Deferred>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
