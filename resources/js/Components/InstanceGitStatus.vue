<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: Object,
        required: true,
    },
});

const problems = {
    path_error: 'Instance path problem',
    git_error: 'Failed to read the working tree',
};

const problem = computed(() =>
    props.status.status === 'ok' ? null : (problems[props.status.status] ?? 'Unknown problem'),
);

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString() : '—');
</script>

<template>
    <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <h3 class="mb-4 font-medium text-gray-900 dark:text-gray-100">Working tree</h3>

        <div
            v-if="problem"
            class="rounded-md border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/50 dark:bg-amber-900/20"
        >
            <div class="font-medium text-amber-900 dark:text-amber-200">{{ problem }}</div>
            <p class="mt-1 break-all font-mono text-sm text-amber-800 dark:text-amber-300">
                {{ status.message }}
            </p>
        </div>

        <template v-else>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Checked out</dt>
                    <dd class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">
                        {{ status.branch ?? 'detached HEAD' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        vs {{ status.tracking ?? 'remote' }}
                    </dt>
                    <dd class="mt-1 text-sm">
                        <span
                            v-if="status.ahead === null"
                            class="text-gray-400 dark:text-gray-500"
                        >
                            no remote-tracking branch
                        </span>
                        <span
                            v-else-if="!status.ahead && !status.behind"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            up to date
                        </span>
                        <span v-else class="font-medium text-amber-700 dark:text-amber-400">
                            <template v-if="status.behind">{{ status.behind }} behind</template>
                            <template v-if="status.behind && status.ahead"> · </template>
                            <template v-if="status.ahead">{{ status.ahead }} ahead</template>
                        </span>
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">HEAD</dt>
                    <dd v-if="status.commit" class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        <span class="font-mono">{{ status.commit.short_sha }}</span>
                        <span class="ms-2">{{ status.commit.subject }}</span>
                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ status.commit.author }} · {{ formatDate(status.commit.committed_at) }}
                        </div>
                    </dd>
                    <dd v-else class="mt-1 text-sm text-gray-400 dark:text-gray-500">no commits</dd>
                </div>
            </dl>

            <div
                class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4 text-xs dark:border-gray-700"
            >
                <span
                    class="inline-flex rounded-full px-2.5 py-0.5 font-medium"
                    :class="
                        status.dirty
                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                            : 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                    "
                >
                    <template v-if="status.dirty">
                        {{ status.changed_files }} uncommitted change(s)
                    </template>
                    <template v-else>clean</template>
                </span>
                <span
                    v-if="status.stashes"
                    class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                    title="Auto-stash never unwinds itself — someone has to go through git stash list."
                >
                    {{ status.stashes }} stash(es)
                </span>
            </div>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Ahead/behind is read from local refs — it is as fresh as the last fetch.
            </p>
        </template>
    </div>
</template>
