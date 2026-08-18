<script setup>
import { ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import NavLink from '@/Components/NavLink.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            <nav
                class="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- App name -->
                            <div class="flex shrink-0 items-center">
                                <Link
                                    :href="route('instances.index')"
                                    class="text-lg font-semibold text-gray-800 dark:text-gray-200"
                                >
                                    {{ $page.props.appName }}
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <NavLink
                                    :href="route('instances.index')"
                                    :active="route().current('instances.*')"
                                >
                                    Instances
                                </NavLink>
                                <NavLink
                                    :href="route('activity.index')"
                                    :active="route().current('activity.*')"
                                >
                                    Activity
                                </NavLink>
                                <NavLink
                                    :href="route('changelog.index')"
                                    :active="route().current('changelog.*')"
                                    class="relative"
                                >
                                    Changelog
                                    <span
                                        v-if="$page.props.hasUnseenChangelog"
                                        class="absolute -end-2 -top-0.5 h-2 w-2 rounded-full bg-red-600"
                                    ></span>
                                </NavLink>

                                <!-- Админские экраны собраны в один пункт, чтобы не растить бар -->
                                <Dropdown
                                    v-if="$page.props.auth.user?.is_admin"
                                    align="left"
                                    width="48"
                                >
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="inline-flex h-[66px] items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none"
                                            :class="
                                                route().current('admin.*')
                                                    ? 'border-indigo-400 text-gray-900 dark:border-indigo-500 dark:text-gray-100'
                                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300'
                                            "
                                        >
                                            Admin
                                            <svg
                                                class="-me-0.5 ms-1 h-4 w-4"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('admin.instances.index')">
                                            Instances
                                        </DropdownLink>
                                        <DropdownLink :href="route('admin.users.index')">
                                            Users
                                        </DropdownLink>
                                        <DropdownLink :href="route('admin.queues.index')">
                                            Queues
                                        </DropdownLink>
                                        <DropdownLink :href="route('admin.screens.index')">
                                            Screens
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <ThemeToggle />
                            <NotificationBell />

                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center gap-1 sm:hidden">
                            <ThemeToggle />
                            <NotificationBell />
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:text-gray-500 dark:hover:bg-gray-900 dark:hover:text-gray-400 dark:focus:bg-gray-900 dark:focus:text-gray-400"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('instances.index')"
                            :active="route().current('instances.*')"
                        >
                            Instances
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('activity.index')"
                            :active="route().current('activity.*')"
                        >
                            Activity
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('changelog.index')"
                            :active="route().current('changelog.*')"
                        >
                            <span class="inline-flex items-center gap-2">
                                Changelog
                                <span
                                    v-if="$page.props.hasUnseenChangelog"
                                    class="h-2 w-2 rounded-full bg-red-600"
                                ></span>
                            </span>
                        </ResponsiveNavLink>
                        <template v-if="$page.props.auth.user?.is_admin">
                            <div class="mt-2 border-t border-gray-200 pt-2 dark:border-gray-600">
                                <div
                                    class="px-4 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                                >
                                    Admin
                                </div>
                                <ResponsiveNavLink
                                    :href="route('admin.instances.index')"
                                    :active="route().current('admin.instances.*')"
                                >
                                    Instances
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    :href="route('admin.users.index')"
                                    :active="route().current('admin.users.*')"
                                >
                                    Users
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    :href="route('admin.queues.index')"
                                    :active="route().current('admin.queues.*')"
                                >
                                    Queues
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    :href="route('admin.screens.index')"
                                    :active="route().current('admin.screens.*')"
                                >
                                    Screens
                                </ResponsiveNavLink>
                            </div>
                        </template>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-1 pt-4 dark:border-gray-600"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800 dark:text-gray-200"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="bg-white shadow dark:bg-gray-800"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>

            <FlashMessage />
        </div>
    </div>
</template>
