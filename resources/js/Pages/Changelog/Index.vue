<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    entries: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Changelog" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Changelog
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                <p
                    v-if="entries.length === 0"
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Nothing here yet.
                </p>

                <article
                    v-for="entry in entries"
                    :key="entry.date"
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800"
                >
                    <div class="border-b border-gray-100 px-6 py-3 dark:border-gray-700">
                        <time class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ entry.date }}
                        </time>
                    </div>
                    <div
                        class="changelog-entry px-6 py-4 text-sm text-gray-700 dark:text-gray-300"
                        v-html="entry.html"
                    />
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.changelog-entry :deep(h3) {
    @apply mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400 first:mt-0 dark:text-gray-500;
}
.changelog-entry :deep(ul) {
    @apply list-disc space-y-1 ps-5;
}
.changelog-entry :deep(li) {
    @apply leading-relaxed;
}
.changelog-entry :deep(strong) {
    @apply font-semibold text-gray-900 dark:text-gray-100;
}
.changelog-entry :deep(code) {
    @apply rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-900;
}
.changelog-entry :deep(a) {
    @apply text-indigo-600 underline hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300;
}
</style>
