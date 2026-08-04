<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    instance: {
        type: Object,
        default: null,
    },
    // Значения для формы создания копии: та же форма, но post на store.
    prefill: {
        type: Object,
        default: null,
    },
    testers: {
        type: Array,
        default: () => [],
    },
});

const isEdit = !!props.instance;
const source = props.instance ?? props.prefill;

const form = useForm({
    name: source?.name ?? '',
    path: source?.path ?? '',
    url: source?.url ?? '',
    repository_url: source?.repository_url ?? '',
    platform: source?.platform ?? 'linux',
    git_remote: source?.git_remote ?? 'origin',
    default_branch: source?.default_branch ?? 'main',
    composer_command: source?.composer_command ?? 'composer install --no-dev --no-interaction',
    cache_command:
        source?.cache_command ??
        'php artisan config:clear && php artisan view:clear && php artisan route:clear',
    migrate_command: source?.migrate_command ?? 'php artisan migrate --force',
    frontend_command: source?.frontend_command ?? 'npm ci && npm run build',
    allowed_path_prefix: source?.allowed_path_prefix ?? '',
    screen_session: source?.screen_session ?? '',
    serve_port: source?.serve_port ?? '',
    is_active: source?.is_active ?? true,
    tester_ids: source?.tester_ids ?? [],
    source_instance_id: props.prefill?.source_instance_id ?? null,
    copy_files: false,
});

const sourcePath = props.prefill?.source_path ?? null;
// Копируем rsync-ом, поэтому предложить дубль файлов есть смысл только для Linux-инстанса.
const canCopyFiles = computed(() => !isEdit && !!sourcePath && form.platform === 'linux');

watch(canCopyFiles, (allowed) => {
    if (!allowed) {
        form.copy_files = false;
    }
});

const submit = () => {
    if (isEdit) {
        form.put(route('admin.instances.update', props.instance.id));
    } else {
        form.post(route('admin.instances.store'));
    }
};

const toggleTester = (id) => {
    const index = form.tester_ids.indexOf(id);
    if (index === -1) {
        form.tester_ids.push(id);
    } else {
        form.tester_ids.splice(index, 1);
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Instance' : 'Create Instance'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ isEdit ? 'Edit Instance' : 'Create Instance' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form
                    class="space-y-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel for="name" value="Name" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="path" value="Path" />
                        <TextInput id="path" v-model="form.path" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.path" />
                    </div>

                    <div>
                        <InputLabel for="url" value="URL" />
                        <TextInput
                            id="url"
                            v-model="form.url"
                            type="url"
                            class="mt-1 block w-full"
                            placeholder="https://stage.example.com"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Where the deployed instance is reachable. Shown as a link and pinged for health.
                        </p>
                        <InputError class="mt-2" :message="form.errors.url" />
                    </div>

                    <div>
                        <InputLabel for="repository_url" value="Repository URL" />
                        <TextInput
                            id="repository_url"
                            v-model="form.repository_url"
                            class="mt-1 block w-full"
                            placeholder="git@github.com:org/repo.git"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Optional. Used by the "Clone repo" action to bootstrap the working copy at Path.
                        </p>
                        <InputError class="mt-2" :message="form.errors.repository_url" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="platform" value="Platform" />
                            <select
                                id="platform"
                                v-model="form.platform"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300"
                            >
                                <option value="linux">Linux</option>
                                <option value="windows">Windows</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.platform" />
                        </div>
                        <div>
                            <InputLabel for="git_remote" value="Git remote" />
                            <TextInput id="git_remote" v-model="form.git_remote" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.git_remote" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="default_branch" value="Default branch" />
                        <TextInput id="default_branch" v-model="form.default_branch" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.default_branch" />
                    </div>

                    <div>
                        <InputLabel for="composer_command" value="Composer command" />
                        <TextInput id="composer_command" v-model="form.composer_command" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.composer_command" />
                    </div>

                    <div>
                        <InputLabel for="cache_command" value="Cache command" />
                        <TextInput id="cache_command" v-model="form.cache_command" class="mt-1 block w-full" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Runs after composer and before migrations, and after every
                            <span class="font-mono">.env</span> edit made from the panel. Without it a
                            project with a cached config keeps the old database no matter what
                            <span class="font-mono">.env</span> says. Leave empty to skip the step —
                            the button and the automatic run disappear with it. Add
                            <span class="font-mono">php artisan cache:clear</span> if you need the
                            application cache too; it needs a reachable database on the
                            <span class="font-mono">database</span> driver.
                        </p>
                        <InputError class="mt-2" :message="form.errors.cache_command" />
                    </div>

                    <div>
                        <InputLabel for="migrate_command" value="Migrate command" />
                        <TextInput id="migrate_command" v-model="form.migrate_command" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.migrate_command" />
                    </div>

                    <div>
                        <InputLabel for="frontend_command" value="Frontend command" />
                        <TextInput id="frontend_command" v-model="form.frontend_command" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.frontend_command" />
                    </div>

                    <div>
                        <InputLabel for="allowed_path_prefix" value="Allowed path prefix" />
                        <TextInput id="allowed_path_prefix" v-model="form.allowed_path_prefix" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.allowed_path_prefix" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="screen_session" value="Screen session" />
                            <TextInput
                                id="screen_session"
                                v-model="form.screen_session"
                                class="mt-1 block w-full"
                                placeholder="instance-1"
                            />
                            <InputError class="mt-2" :message="form.errors.screen_session" />
                        </div>
                        <div>
                            <InputLabel for="serve_port" value="Serve port" />
                            <TextInput
                                id="serve_port"
                                v-model="form.serve_port"
                                type="number"
                                min="1"
                                max="65535"
                                class="mt-1 block w-full"
                                placeholder="8080"
                            />
                            <InputError class="mt-2" :message="form.errors.serve_port" />
                        </div>
                    </div>
                    <p class="-mt-4 text-xs text-gray-500 dark:text-gray-400">
                        Name of the screen session running this instance's dev server, and the port it
                        listens on. Set both to start and stop the instance from
                        <span class="font-mono">Admin → Screens</span>; the session is also matched to
                        this instance by name in the session list. Leave empty if the instance is not
                        served from a screen session.
                    </p>

                    <div class="flex items-center gap-2">
                        <Checkbox id="is_active" v-model:checked="form.is_active" />
                        <InputLabel for="is_active" value="Active" />
                    </div>

                    <div v-if="testers.length">
                        <InputLabel value="Tester access" />
                        <div class="mt-2 space-y-2">
                            <label
                                v-for="tester in testers"
                                :key="tester.id"
                                class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800"
                                    :checked="form.tester_ids.includes(tester.id)"
                                    @change="toggleTester(tester.id)"
                                />
                                {{ tester.name }} ({{ tester.email }})
                            </label>
                        </div>
                    </div>

                    <div v-if="sourcePath && !isEdit" class="rounded-md bg-gray-50 p-4 dark:bg-gray-900/50">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="copy_files"
                                v-model:checked="form.copy_files"
                                :disabled="!canCopyFiles"
                            />
                            <InputLabel for="copy_files" value="Copy files from the original" />
                        </div>
                        <p v-if="canCopyFiles" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Copies <span class="font-mono">{{ sourcePath }}</span> into Path, then reinstalls
                            dependencies and rebuilds the frontend. Path must be empty or not exist yet.
                            The <span class="font-mono">.env</span> is copied with
                            <span class="font-mono">DB_DATABASE</span> and
                            <span class="font-mono">APP_URL</span> left empty — fill them in before deploying.
                        </p>
                        <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Available for Linux instances only.
                        </p>
                        <InputError class="mt-2" :message="form.errors.copy_files" />
                    </div>

                    <PrimaryButton :disabled="form.processing">
                        {{ isEdit ? 'Save' : 'Create' }}
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
