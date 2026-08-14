<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeployCommits from '@/Components/DeployCommits.vue';
import DeployLog from '@/Components/DeployLog.vue';
import DeploymentHistory from '@/Components/DeploymentHistory.vue';
import DeployStatusBadge from '@/Components/DeployStatusBadge.vue';
import DeployStepProgress from '@/Components/DeployStepProgress.vue';
import HealthBadge from '@/Components/HealthBadge.vue';
import InstanceGitStatus from '@/Components/InstanceGitStatus.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import { Deferred, Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    instance: {
        type: Object,
        required: true,
    },
    branches: {
        type: Array,
        default: () => [],
    },
    currentBranch: {
        type: String,
        default: null,
    },
    branchError: {
        type: String,
        default: null,
    },
    deployment: {
        type: Object,
        default: null,
    },
    deployments: {
        type: Array,
        default: () => [],
    },
    gitStatus: {
        type: Object,
        default: null,
    },
});

const page = usePage();

const deployError = computed(() => page.props.errors?.deploy);

const selectedBranch = ref(
    props.currentBranch || props.instance.default_branch || '',
);
const branches = ref([...props.branches]);
const refreshing = ref(false);
const refreshError = ref(props.branchError ?? '');

const UNITS = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
];

// «3 days ago» рядом с веткой: без этого сортировка по свежести читается как случайная.
const relativeTime = (iso) => {
    const seconds = (Date.now() - new Date(iso).getTime()) / 1000;

    if (Number.isNaN(seconds)) return null;
    if (seconds < 60) return 'just now';

    // Локаль задаём явно: интерфейс англоязычный, локаль браузера тут не при чём.
    const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    for (const [unit, size] of UNITS) {
        if (seconds >= size) {
            return formatter.format(-Math.floor(seconds / size), unit);
        }
    }

    return null;
};

const branchLabel = (branch) => {
    const when = branch.committed_at ? relativeTime(branch.committed_at) : null;

    return when ? `${branch.name} · ${when}` : branch.name;
};

const form = useForm({
    branch: selectedBranch.value,
    action: 'full',
});

watch(selectedBranch, (value) => {
    form.branch = value;
});

// Брошенный деплой не считаем идущим: кнопки разблокированы, поллинг не нужен.
const isRunning = computed(
    () =>
        !props.deployment?.is_stale &&
        (props.deployment?.status === 'running' ||
            props.deployment?.status === 'pending'),
);

const isStale = computed(() => props.deployment?.is_stale === true);

const canCancel = computed(
    () =>
        props.deployment?.status === 'running' ||
        props.deployment?.status === 'pending',
);

const cancelling = ref(false);

const cancelDeployment = () => {
    cancelling.value = true;

    router.post(
        route('instances.deployments.cancel', [
            props.instance.id,
            props.deployment.id,
        ]),
        {},
        {
            preserveScroll: true,
            onFinish: () => (cancelling.value = false),
        },
    );
};

const deploy = (action) => {
    form.action = action;
    form.branch = selectedBranch.value;
    form.post(route('instances.deploy', props.instance.id), {
        preserveScroll: true,
    });
};

// Есть ли к чему откатываться: хоть один успешный полный деплой или деплой ветки.
const canRollback = computed(() =>
    props.deployments.some(
        (d) => ['full', 'branch'].includes(d.action) && d.status === 'success',
    ),
);

const rollback = () => {
    if (!confirm('Roll back the working tree to the state before the last deploy?')) {
        return;
    }

    router.post(
        route('instances.rollback', props.instance.id),
        {},
        { preserveScroll: true },
    );
};

const refreshBranches = async () => {
    refreshing.value = true;
    refreshError.value = '';

    try {
        const { data } = await axios.post(
            route('instances.branches.refresh', props.instance.id),
        );
        branches.value = data.branches ?? [];
        if (data.current) {
            selectedBranch.value = data.current;
        }
    } catch (error) {
        refreshError.value =
            error.response?.data?.message ?? 'Failed to refresh branches.';
    } finally {
        refreshing.value = false;
    }
};

const health = ref(null);
const healthChecking = ref(false);

const checkHealth = async () => {
    if (!props.instance.url || healthChecking.value) return;

    healthChecking.value = true;

    try {
        const { data } = await axios.get(
            route('instances.health', props.instance.id),
        );
        health.value = data;
    } catch (error) {
        health.value = {
            status: 'unreachable',
            message:
                error.response?.data?.message ?? 'Health check request failed.',
            code: null,
            duration_ms: null,
        };
    } finally {
        healthChecking.value = false;
    }
};

let pollInterval = null;

const startPolling = () => {
    if (pollInterval) return;

    pollInterval = setInterval(() => {
        router.reload({
            only: ['deployment', 'currentBranch', 'branches'],
            preserveScroll: true,
        });
    }, 3000);
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

watch(isRunning, (running) => (running ? startPolling() : stopPolling()), {
    immediate: true,
});

watch(
    () => props.deployment?.status,
    (status, previous) => {
        // Деплой только что закончился: рабочее дерево и история устарели, стенд перезапустился.
        if (
            status !== previous &&
            (previous === 'running' || previous === 'pending')
        ) {
            router.reload({ only: ['gitStatus', 'deployments'] });
            checkHealth();
        }
    },
);

const logDeployment = ref(null);
const logLoading = ref(false);
const logError = ref('');

const commits = ref(null);
const commitsLoading = ref(false);

// Коммиты тянем своим запросом: git-подпроцесс не должен задерживать лог.
const loadCommits = async (deployment) => {
    commits.value = null;
    commitsLoading.value = true;

    try {
        const { data } = await axios.get(
            route('instances.deployments.commits', [
                props.instance.id,
                deployment.id,
            ]),
        );
        commits.value = data;
    } catch (error) {
        commits.value = {
            status: 'git_error',
            message:
                error.response?.data?.message ?? 'Failed to load commits.',
            direction: null,
            truncated: false,
            commits: [],
        };
    } finally {
        commitsLoading.value = false;
    }
};

const openLog = async (deployment) => {
    logLoading.value = true;
    logError.value = '';
    logDeployment.value = { ...deployment, output: null };

    loadCommits(deployment);

    try {
        const { data } = await axios.get(
            route('instances.deployments.show', [
                props.instance.id,
                deployment.id,
            ]),
        );
        logDeployment.value = data;
    } catch (error) {
        logError.value =
            error.response?.data?.message ?? 'Failed to load the deployment.';
    } finally {
        logLoading.value = false;
    }
};

const closeLog = () => {
    logDeployment.value = null;
    logError.value = '';
    commits.value = null;
};

onMounted(() => {
    checkHealth();
});

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <Head :title="instance.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ instance.name }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ instance.path }}</p>
                </div>
                <div v-if="instance.url || instance.tunnel_url" class="flex items-center gap-3">
                    <template v-if="instance.url">
                        <HealthBadge :health="health" :checking="healthChecking" />
                        <SecondaryButton
                            type="button"
                            :disabled="healthChecking"
                            @click="checkHealth"
                        >
                            {{ healthChecking ? '…' : '↻' }}
                        </SecondaryButton>
                        <a
                            :href="instance.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            Open ↗
                        </a>
                    </template>
                    <a
                        v-if="instance.tunnel_url"
                        :href="instance.tunnel_url"
                        :title="`Open through a tunnel: ${instance.tunnel_url}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                        Tunnel ↗
                    </a>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                    <div class="mb-4 flex flex-wrap items-end gap-3">
                        <div class="min-w-[200px] flex-1">
                            <label
                                for="branch"
                                class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Branch
                            </label>
                            <div class="flex gap-2">
                                <select
                                    id="branch"
                                    v-model="selectedBranch"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300"
                                    :disabled="isRunning"
                                >
                                    <option value="" disabled>
                                        Select branch
                                    </option>
                                    <option
                                        v-for="branch in branches"
                                        :key="branch.name"
                                        :value="branch.name"
                                    >
                                        {{ branchLabel(branch) }}
                                    </option>
                                </select>
                                <SecondaryButton
                                    type="button"
                                    :disabled="refreshing || isRunning"
                                    @click="refreshBranches"
                                >
                                    {{ refreshing ? '…' : '↻' }}
                                </SecondaryButton>
                            </div>
                            <p
                                v-if="currentBranch"
                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                            >
                                Current: {{ currentBranch }}
                            </p>
                            <p
                                v-if="refreshError"
                                class="mt-1 whitespace-pre-line break-words text-xs text-red-600 dark:text-red-400"
                            >
                                {{ refreshError }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Deploy
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <PrimaryButton
                                    :disabled="isRunning || !selectedBranch"
                                    @click="deploy('full')"
                                >
                                    Full deploy
                                </PrimaryButton>
                                <SecondaryButton
                                    :disabled="isRunning || !selectedBranch"
                                    @click="deploy('branch')"
                                >
                                    Code only
                                </SecondaryButton>
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Individual steps
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <SecondaryButton
                                    v-if="instance.has_composer_command"
                                    :disabled="isRunning"
                                    @click="deploy('composer')"
                                >
                                    Composer install
                                </SecondaryButton>
                                <SecondaryButton
                                    v-if="instance.has_cache_command"
                                    :disabled="isRunning"
                                    @click="deploy('cache')"
                                >
                                    Clear caches
                                </SecondaryButton>
                                <SecondaryButton
                                    :disabled="isRunning"
                                    @click="deploy('migrate')"
                                >
                                    Run migrations
                                </SecondaryButton>
                                <SecondaryButton
                                    :disabled="isRunning"
                                    @click="deploy('frontend')"
                                >
                                    Build frontend
                                </SecondaryButton>
                            </div>
                        </div>

                        <div v-if="canRollback">
                            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Recovery
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <DangerButton
                                    :disabled="isRunning"
                                    @click="rollback"
                                >
                                    Rollback
                                </DangerButton>
                            </div>
                        </div>
                    </div>

                    <InputError class="mt-2" :message="form.errors.branch" />
                    <InputError class="mt-2" :message="form.errors.action" />
                    <InputError class="mt-2" :message="form.errors.deploy" />
                    <InputError class="mt-2" :message="deployError" />
                </div>

                <div
                    v-if="deployment"
                    class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-medium text-gray-900 dark:text-gray-100">Deployment</h3>
                        <div class="flex items-center gap-3">
                            <span
                                v-if="isStale"
                                class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                            >
                                Abandoned
                            </span>
                            <DeployStatusBadge :status="deployment.status" />
                            <DangerButton
                                v-if="canCancel"
                                type="button"
                                :disabled="cancelling"
                                @click="cancelDeployment"
                            >
                                {{ cancelling ? '…' : 'Cancel' }}
                            </DangerButton>
                        </div>
                    </div>

                    <p
                        v-if="deployment.queue_stuck"
                        class="mb-4 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
                    >
                        Queued for {{ deployment.queued_seconds }}s and still not
                        picked up — check that <code>php artisan queue:work</code>
                        is running.
                    </p>

                    <p
                        v-else-if="isStale"
                        class="mb-4 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
                    >
                        No sign of life from this deployment for a while — the
                        worker probably died. The instance is no longer locked, so
                        you can start a new deployment.
                    </p>

                    <DeployStepProgress
                        v-if="deployment.steps"
                        class="mb-4"
                        :steps="deployment.steps"
                        :current-step="deployment.current_step"
                    />

                    <DeployLog
                        :output="deployment.output"
                        :filename="`deploy-${deployment.id}.log`"
                    />
                </div>

                <Deferred data="gitStatus">
                    <template #fallback>
                        <div class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow-sm dark:bg-gray-800 dark:text-gray-400">
                            Reading the working tree…
                        </div>
                    </template>

                    <InstanceGitStatus :status="gitStatus" />
                </Deferred>

                <DeploymentHistory
                    :deployments="deployments"
                    :selected-id="logDeployment?.id ?? null"
                    @select="openLog"
                />
            </div>
        </div>

        <Modal :show="logDeployment !== null" max-width="2xl" @close="closeLog">
            <div v-if="logDeployment" class="p-6">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-gray-100">
                            {{ logDeployment.action }}
                            <span class="font-mono text-sm text-gray-500 dark:text-gray-400">
                                {{ logDeployment.branch ?? '—' }}
                            </span>
                        </h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            #{{ logDeployment.id }} ·
                            {{ logDeployment.user ?? '—' }} ·
                            {{
                                logDeployment.started_at
                                    ? new Date(
                                          logDeployment.started_at,
                                      ).toLocaleString()
                                    : '—'
                            }}
                            <template v-if="logDeployment.exit_code">
                                · exit {{ logDeployment.exit_code }}
                            </template>
                        </p>
                    </div>
                    <DeployStatusBadge :status="logDeployment.status" />
                </div>

                <DeployStepProgress
                    v-if="logDeployment.steps"
                    class="mb-4"
                    :steps="logDeployment.steps"
                    :current-step="logDeployment.current_step"
                />

                <DeployCommits
                    class="mb-4"
                    :result="commits"
                    :loading="commitsLoading"
                />

                <p v-if="logError" class="mb-2 text-sm text-red-600 dark:text-red-400">
                    {{ logError }}
                </p>

                <p v-if="logLoading" class="text-sm text-gray-500 dark:text-gray-400">
                    Loading the log…
                </p>

                <DeployLog
                    v-else-if="!logError"
                    :output="logDeployment.output"
                    :filename="`deploy-${logDeployment.id}.log`"
                />

                <div class="mt-4 flex justify-end">
                    <SecondaryButton type="button" @click="closeLog">
                        Close
                    </SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
