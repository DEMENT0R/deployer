<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    instance: {
        type: Object,
        required: true,
    },
    env: {
        type: Object,
        required: true,
    },
});

const statusLabels = {
    missing: '.env not found',
    unreadable: '.env is not readable',
    too_large: '.env is too large to parse',
    path_error: 'Instance path problem',
};

const problem = computed(() =>
    props.env.status === 'ok' ? null : statusLabels[props.env.status] ?? 'Unknown problem'
);

const formatSize = (bytes) => `${bytes} B`;

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString() : '—');

// Замаскированное значение до браузера не доезжает, поэтому поле секрета всегда пустое:
// пустым и отправляем — сервер такой ключ не трогает.
const seed = () =>
    Object.fromEntries(
        (props.env.variables ?? []).map((variable) => [
            variable.key,
            variable.masked ? '' : (variable.value ?? ''),
        ]),
    );

const form = useForm({ values: seed() });

watch(() => props.env, () => form.defaults({ values: seed() }).reset());

const placeholder = (variable) => {
    if (variable.masked) return variable.value ? `${variable.value} (unchanged)` : 'unchanged';

    return variable.present ? '' : 'not set';
};

const page = usePage();
const creating = ref(false);
const createError = computed(() => page.props.errors?.env);
const cacheError = computed(() => page.props.errors?.cache);

const clearing = ref(false);

const clearCaches = () => {
    clearing.value = true;

    router.post(
        route('admin.instances.caches.clear', props.instance.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (clearing.value = false),
        },
    );
};

const createEnv = (source) => {
    creating.value = true;

    router.post(
        route('admin.instances.env.store', props.instance.id),
        { source },
        {
            preserveScroll: true,
            onFinish: () => (creating.value = false),
        },
    );
};

// Пустое поле секрета сервер трактует как «не трогать» — фильтровать на клиенте нечего.
const submit = () => {
    form.put(route('admin.instances.env.update', props.instance.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${instance.name} · .env`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ instance.name }} · .env
                </h2>
                <Link
                    :href="route('admin.instances.index')"
                    class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    Back to instances
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
                    <dl class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">File</dt>
                            <dd class="mt-1 break-all font-mono text-sm text-gray-900 dark:text-gray-100">
                                {{ env.file?.path ?? `${instance.path}/.env` }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Size</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {{ env.file ? formatSize(env.file.size) : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Modified</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {{ formatDate(env.file?.modified_at) }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    v-if="problem"
                    class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-900/20"
                >
                    <div class="font-medium text-amber-900 dark:text-amber-200">{{ problem }}</div>
                    <p class="mt-1 break-all font-mono text-sm text-amber-800 dark:text-amber-300">{{ env.message }}</p>

                    <div v-if="env.status === 'missing'" class="mt-4 flex flex-wrap items-center gap-2">
                        <PrimaryButton
                            v-if="env.example_available"
                            :disabled="creating"
                            @click="createEnv('example')"
                        >
                            Create from .env.example
                        </PrimaryButton>
                        <SecondaryButton :disabled="creating" @click="createEnv('blank')">
                            Create empty .env
                        </SecondaryButton>
                        <span v-if="!env.example_available" class="text-xs text-amber-800 dark:text-amber-300">
                            No .env.example in this directory.
                        </span>
                    </div>

                    <InputError class="mt-2" :message="createError" />
                </div>

                <form v-else class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800" @submit.prevent="submit">
                    <div class="border-b border-gray-200 px-4 py-3 font-medium text-gray-900 dark:border-gray-700 dark:text-gray-100">
                        Environment
                    </div>

                    <div v-if="form.errors.env" class="border-b border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/50 dark:bg-red-900/20">
                        <p class="text-sm text-red-800 dark:text-red-300">{{ form.errors.env }}</p>
                    </div>
                    <div v-if="form.errors.values" class="border-b border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/50 dark:bg-red-900/20">
                        <p class="text-sm text-red-800 dark:text-red-300">{{ form.errors.values }}</p>
                    </div>

                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Key</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="variable in env.variables" :key="variable.key">
                                <td class="w-64 px-4 py-2 align-top font-mono text-sm text-gray-900 dark:text-gray-100">
                                    {{ variable.key }}
                                    <span
                                        v-if="variable.masked"
                                        class="ms-2 rounded bg-gray-100 px-1.5 py-0.5 font-sans text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-400"
                                    >
                                        masked
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    <TextInput
                                        v-model="form.values[variable.key]"
                                        class="block w-full font-mono text-sm"
                                        :placeholder="placeholder(variable)"
                                    />
                                    <InputError class="mt-1" :message="form.errors[`values.${variable.key}`]" />
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div class="flex flex-wrap items-center gap-2">
                            <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
                            <SecondaryButton
                                v-if="instance.has_cache_command"
                                type="button"
                                :disabled="clearing"
                                @click="clearCaches"
                            >
                                {{ clearing ? '…' : 'Clear caches' }}
                            </SecondaryButton>
                        </div>
                        <InputError class="mt-2" :message="cacheError" />
                    </div>

                    <div class="border-t border-gray-200 px-4 py-3 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        Only keys from <span class="font-mono">deployer.env_visible_keys</span> are shown and
                        editable; secrets are masked before leaving the server, so leave a masked field empty
                        to keep its current value. The previous file is kept as
                        <span class="font-mono">.env.backup</span> next to it. Saving also runs the instance's
                        cache command in the target project, so a cached config does not keep serving the old
                        values; the button next to Save does the same on its own, for edits made on the host.
                        <template v-if="env.hidden_count">
                            {{ env.hidden_count }} other variable(s) in this file are not displayed and are
                            left untouched.
                        </template>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
