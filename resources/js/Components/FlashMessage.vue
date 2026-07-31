<script setup>
import { usePage } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';

const VISIBLE_MS = 4000;

const page = usePage();
const message = ref('');
let timer = null;

const hide = () => {
    message.value = '';
    clearTimeout(timer);
};

// Следим за самим объектом flash, а не за строкой внутри: одно и то же сообщение подряд
// (два деплоя, два сохранения) иначе не показалось бы второй раз. На частичной перезагрузке
// Inertia оставляет прежний объект, поэтому лишних срабатываний нет.
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash?.success) return;

        message.value = flash.success;
        clearTimeout(timer);
        timer = setTimeout(hide, VISIBLE_MS);
    },
    { immediate: true },
);

onUnmounted(() => clearTimeout(timer));
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition ease-in duration-150"
        leave-to-class="translate-y-2 opacity-0"
    >
        <div
            v-if="message"
            class="fixed inset-x-0 bottom-6 z-50 flex justify-center px-4"
            role="status"
            aria-live="polite"
        >
            <div
                class="flex max-w-xl items-start gap-3 rounded-lg bg-gray-900 px-4 py-3 text-sm text-white shadow-lg"
            >
                <span class="break-words">{{ message }}</span>
                <button
                    type="button"
                    class="-me-1 shrink-0 text-gray-400 hover:text-white"
                    aria-label="Dismiss"
                    @click="hide"
                >
                    ✕
                </button>
            </div>
        </div>
    </Transition>
</template>
