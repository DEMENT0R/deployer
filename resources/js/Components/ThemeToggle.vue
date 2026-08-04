<script setup>
import { useTheme } from '@/composables/useTheme';
import { computed } from 'vue';

const { mode, cycleMode } = useTheme();

const labels = {
    system: 'Theme: system',
    light: 'Theme: light',
    dark: 'Theme: dark',
};

const label = computed(() => labels[mode.value]);
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center rounded-md p-2 text-gray-500 transition hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:text-gray-200"
        :aria-label="label"
        :title="label"
        @click="cycleMode"
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
                v-if="mode === 'light'"
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 3v1.5m0 15V21m9-9h-1.5m-15 0H3m15.364-6.364l-1.06 1.06M6.696 17.304l-1.06 1.06m12.728 0l-1.06-1.06M6.696 6.696l-1.06-1.06M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"
            />
            <path
                v-else-if="mode === 'dark'"
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"
            />
            <path
                v-else
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25A2.25 2.25 0 015.25 3h13.5A2.25 2.25 0 0121 5.25z"
            />
        </svg>
    </button>
</template>
