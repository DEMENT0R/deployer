<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
});

const isEdit = !!props.user;

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    role: props.user?.role ?? 'tester',
});

const submit = () => {
    if (isEdit) {
        form.put(route('admin.users.update', props.user.id));
    } else {
        form.post(route('admin.users.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit User' : 'Create User'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ isEdit ? 'Edit User' : 'Create User' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-xl sm:px-6 lg:px-8">
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
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="password" :value="isEdit ? 'Password (leave blank to keep)' : 'Password'" />
                        <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" :required="!isEdit" />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="role" value="Role" />
                        <select
                            id="role"
                            v-model="form.role"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <option value="admin">Admin</option>
                            <option value="tester">Tester</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.role" />
                    </div>

                    <InputError class="mt-2" :message="form.errors.user" />

                    <PrimaryButton :disabled="form.processing">
                        {{ isEdit ? 'Save' : 'Create' }}
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
