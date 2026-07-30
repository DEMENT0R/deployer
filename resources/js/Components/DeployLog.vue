<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    output: {
        type: String,
        default: '',
    },
    filename: {
        type: String,
        default: 'deploy.log',
    },
});

const logRef = ref(null);
const copied = ref(false);

watch(
    () => props.output,
    async () => {
        await nextTick();
        if (logRef.value) {
            logRef.value.scrollTop = logRef.value.scrollHeight;
        }
    },
    { immediate: true },
);

const copy = async () => {
    try {
        await navigator.clipboard.writeText(props.output ?? '');
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch {
        // Клипборд недоступен (не https, отказ в разрешении) — кнопка просто ничего не делает.
    }
};

const download = () => {
    const url = URL.createObjectURL(
        new Blob([props.output ?? ''], { type: 'text/plain' }),
    );
    const link = document.createElement('a');
    link.href = url;
    link.download = props.filename;
    link.click();
    URL.revokeObjectURL(url);
};
</script>

<template>
    <div>
        <div class="mb-1 flex justify-end gap-3 text-xs">
            <button
                type="button"
                class="text-gray-500 hover:text-gray-800 disabled:opacity-50"
                :disabled="!output"
                @click="copy"
            >
                {{ copied ? 'Copied' : 'Copy' }}
            </button>
            <button
                type="button"
                class="text-gray-500 hover:text-gray-800 disabled:opacity-50"
                :disabled="!output"
                @click="download"
            >
                Download
            </button>
        </div>

        <pre
            ref="logRef"
            class="max-h-96 overflow-auto rounded-md bg-gray-900 p-4 text-xs leading-relaxed text-gray-100"
        >{{ output || 'No output yet.' }}</pre>
    </div>
</template>
