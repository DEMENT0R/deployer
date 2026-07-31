<script setup>
const props = defineProps({
    steps: {
        type: Object,
        default: () => ({}),
    },
    currentStep: {
        type: String,
        default: null,
    },
});

const stepOrder = [
    'clone',
    'copy',
    'rollback',
    'git',
    'composer',
    'migrate',
    'frontend',
];

const stepLabels = {
    clone: 'Clone',
    copy: 'Copy files',
    rollback: 'Rollback',
    git: 'Git',
    composer: 'Composer',
    migrate: 'Migrate',
    frontend: 'Frontend',
};

const visibleSteps = () =>
    stepOrder.filter((step) => props.steps[step] !== undefined);

const icon = (status) => {
    if (status === 'success' || status === 'skipped') return '✓';
    if (status === 'failed') return '✗';
    if (status === 'running') return '…';
    return '○';
};

const statusClass = (status) => {
    if (status === 'success' || status === 'skipped') return 'text-green-600';
    if (status === 'failed') return 'text-red-600';
    if (status === 'running') return 'text-blue-600';
    return 'text-gray-400';
};
</script>

<template>
    <div class="flex flex-wrap gap-4">
        <div
            v-for="step in visibleSteps()"
            :key="step"
            class="flex items-center gap-2 text-sm"
            :class="statusClass(steps[step])"
        >
            <span class="font-mono">{{ icon(steps[step]) }}</span>
            <span>{{ stepLabels[step] ?? step }}</span>
        </div>
    </div>
</template>
