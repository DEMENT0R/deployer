<script setup>
import { computed } from 'vue';

const props = defineProps({
    // null — ещё не загружали; иначе ответ эндпоинта commits
    result: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const heading = computed(() =>
    props.result?.direction === 'reverted'
        ? 'Commits rolled back'
        : 'Commits in this deploy',
);

const commits = computed(() => props.result?.commits ?? []);

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString() : '');
</script>

<template>
    <div class="rounded-lg border border-gray-200 dark:border-gray-700">
        <div
            class="flex items-baseline justify-between border-b border-gray-200 px-4 py-2 dark:border-gray-700"
        >
            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ heading }}</span>
            <span v-if="commits.length" class="text-xs text-gray-500 dark:text-gray-400">
                {{ commits.length }}{{ result.truncated ? '+' : '' }}
            </span>
        </div>

        <p v-if="loading" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
            Loading commits…
        </p>

        <p
            v-else-if="result && result.status !== 'ok'"
            class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400"
        >
            {{ result.message }}
        </p>

        <p
            v-else-if="result && commits.length === 0"
            class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400"
        >
            No commits in this range.
        </p>

        <ul v-else-if="result" class="divide-y divide-gray-100 dark:divide-gray-700">
            <li
                v-for="commit in commits"
                :key="commit.sha"
                class="px-4 py-2"
            >
                <div class="flex items-baseline gap-2">
                    <code class="shrink-0 font-mono text-xs text-indigo-700 dark:text-indigo-400">
                        {{ commit.short_sha }}
                    </code>
                    <span class="break-words text-sm text-gray-900 dark:text-gray-100">
                        {{ commit.subject }}
                    </span>
                </div>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ commit.author }}
                    <span v-if="commit.committed_at">
                        · {{ formatDate(commit.committed_at) }}
                    </span>
                </p>
            </li>
        </ul>

        <p
            v-if="result?.truncated"
            class="border-t border-gray-100 px-4 py-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400"
        >
            Only the first {{ commits.length }} commits are shown.
        </p>
    </div>
</template>
