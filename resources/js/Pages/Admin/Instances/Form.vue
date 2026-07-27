<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    instance: {
        type: Object,
        default: null,
    },
    testers: {
        type: Array,
        default: () => [],
    },
});

const isEdit = !!props.instance;

const form = useForm({
    name: props.instance?.name ?? '',
    path: props.instance?.path ?? '',
    url: props.instance?.url ?? '',
    platform: props.instance?.platform ?? 'linux',
    git_remote: props.instance?.git_remote ?? 'origin',
    default_branch: props.instance?.default_branch ?? 'main',
    composer_command: props.instance?.composer_command ?? 'composer install --no-dev --no-interaction',
    migrate_command: props.instance?.migrate_command ?? 'php artisan migrate --force',
    frontend_command: props.instance?.frontend_command ?? 'npm ci && npm run build',
    allowed_path_prefix: props.instance?.allowed_path_prefix ?? '',
    is_active: props.instance?.is_active ?? true,
    tester_ids: props.instance?.tester_ids ?? [],
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
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ isEdit ? 'Edit Instance' : 'Create Instance' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form
                    class="space-y-6 rounded-lg bg-white p-6 shadow-sm"
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
                        <p class="mt-1 text-xs text-gray-500">
                            Where the deployed instance is reachable. Shown as a link and pinged for health.
                        </p>
                        <InputError class="mt-2" :message="form.errors.url" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="platform" value="Platform" />
                            <select
                                id="platform"
                                v-model="form.platform"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
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
                                class="flex items-center gap-2 text-sm text-gray-700"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.tester_ids.includes(tester.id)"
                                    @change="toggleTester(tester.id)"
                                />
                                {{ tester.name }} ({{ tester.email }})
                            </label>
                        </div>
                    </div>

                    <PrimaryButton :disabled="form.processing">
                        {{ isEdit ? 'Save' : 'Create' }}
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
