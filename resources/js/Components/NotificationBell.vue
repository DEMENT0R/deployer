<script setup>
import DeployStatusBadge from '@/Components/DeployStatusBadge.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const page = usePage();

const open = ref(false);
const loading = ref(false);
const error = ref('');
const items = ref([]);

// Пока выпадашку не открывали, счётчик берём из общих пропсов.
const localCount = ref(null);
const unread = computed(() => localCount.value ?? page.props.unreadNotifications ?? 0);

const badge = computed(() => (unread.value > 9 ? '9+' : String(unread.value)));

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(route('notifications.index'));
        items.value = data.notifications ?? [];
        localCount.value = data.unread_count ?? 0;
    } catch (e) {
        error.value =
            e.response?.data?.message ?? 'Failed to load notifications.';
    } finally {
        loading.value = false;
    }
};

const toggle = () => {
    open.value = !open.value;

    if (open.value) {
        load();
    }
};

const markAllRead = async () => {
    try {
        const { data } = await axios.post(route('notifications.read-all'));
        localCount.value = data.unread_count ?? 0;
        items.value = items.value.map((item) => ({ ...item, read: true }));
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Failed to mark as read.';
    }
};

const openItem = async (item) => {
    open.value = false;

    if (!item.read) {
        try {
            const { data } = await axios.post(
                route('notifications.read', item.id),
            );
            localCount.value = data.unread_count ?? 0;
        } catch {
            // Не смогли отметить прочитанным — всё равно ведём на инстанс.
        }
    }

    if (item.data?.instance_id) {
        router.visit(route('instances.show', item.data.instance_id));
    }
};

const label = (item) =>
    [item.data?.instance_name, item.data?.action, item.data?.branch]
        .filter(Boolean)
        .join(' · ');

const UNITS = [
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
];

const relativeTime = (iso) => {
    const seconds = (Date.now() - new Date(iso).getTime()) / 1000;

    if (Number.isNaN(seconds)) return '';
    if (seconds < 60) return 'just now';

    const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    for (const [unit, size] of UNITS) {
        if (seconds >= size) {
            return formatter.format(-Math.floor(seconds / size), unit);
        }
    }

    return '';
};

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative inline-flex items-center rounded-md p-2 text-gray-500 transition hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:text-gray-200"
            :aria-label="
                unread ? `Notifications, ${unread} unread` : 'Notifications'
            "
            @click="toggle"
        >
            <svg
                class="h-5 w-5"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.8"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"
                />
            </svg>
            <span
                v-if="unread > 0"
                class="absolute -end-0.5 -top-0.5 inline-flex min-w-[18px] justify-center rounded-full bg-red-600 px-1 text-[11px] font-semibold leading-[18px] text-white"
            >
                {{ badge }}
            </span>
        </button>

        <div
            v-show="open"
            class="fixed inset-0 z-40"
            @click="open = false"
        ></div>

        <div
            v-show="open"
            class="absolute z-50 mt-2 w-80 overflow-hidden rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 ltr:end-0 rtl:start-0 dark:bg-gray-700 dark:ring-white dark:ring-opacity-10"
        >
            <div
                class="flex items-baseline justify-between border-b border-gray-200 px-4 py-2 dark:border-gray-600"
            >
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                    Notifications
                </span>
                <button
                    v-if="unread > 0"
                    type="button"
                    class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                    @click="markAllRead"
                >
                    Mark all read
                </button>
            </div>

            <p v-if="loading" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                Loading…
            </p>

            <p v-else-if="error" class="px-4 py-3 text-sm text-red-600 dark:text-red-400">
                {{ error }}
            </p>

            <p
                v-else-if="items.length === 0"
                class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400"
            >
                Nothing here yet.
            </p>

            <ul
                v-else
                class="max-h-80 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-600"
            >
                <li v-for="item in items" :key="item.id">
                    <button
                        type="button"
                        class="block w-full px-4 py-2 text-start transition hover:bg-gray-50 dark:hover:bg-gray-600"
                        :class="{ 'bg-indigo-50/60 dark:bg-indigo-900/30': !item.read }"
                        @click="openItem(item)"
                    >
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="truncate text-sm text-gray-900 dark:text-gray-100">
                                {{ label(item) }}
                            </span>
                            <DeployStatusBadge
                                v-if="item.data?.status"
                                :status="item.data.status"
                            />
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ relativeTime(item.created_at) }}
                        </span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
