<script setup>
defineProps({
    status: {
        type: String,
        required: true,
    },
});

const classes = {
    pending: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    running: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    success: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
};

const labels = {
    pending: 'Pending',
    running: 'Running',
    success: 'Success',
    failed: 'Failed',
};
</script>

<template>
    <span
        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="classes[status] ?? classes.pending"
    >
        {{ labels[status] ?? status }}
    </span>
</template>
