<script setup>
import { computed } from 'vue';

const props = defineProps({
    health: {
        type: Object,
        default: null,
    },
    checking: {
        type: Boolean,
        default: false,
    },
});

const classes = {
    up: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    down: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    unreachable: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    not_configured: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    checking: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
};

const labels = {
    up: 'Up',
    down: 'HTTP error',
    unreachable: 'Unreachable',
    not_configured: 'No URL',
    checking: 'Checking…',
};

const status = computed(() => {
    if (props.checking) return 'checking';

    return props.health?.status ?? 'checking';
});

const detail = computed(() => {
    if (props.checking || !props.health) return '';

    const parts = [];
    if (props.health.code) parts.push(props.health.code);
    if (props.health.duration_ms !== null && props.health.duration_ms !== undefined) {
        parts.push(`${props.health.duration_ms} ms`);
    }

    return parts.join(' · ');
});
</script>

<template>
    <span class="inline-flex items-center gap-1.5" :title="health?.message ?? ''">
        <span
            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="classes[status] ?? classes.checking"
        >
            {{ labels[status] ?? status }}
        </span>
        <span v-if="detail" class="text-xs text-gray-500 dark:text-gray-400">{{ detail }}</span>
    </span>
</template>
