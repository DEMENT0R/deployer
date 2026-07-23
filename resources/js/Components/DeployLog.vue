<script setup>
import { ref, watch, nextTick } from 'vue';

const props = defineProps({
    output: {
        type: String,
        default: '',
    },
});

const logRef = ref(null);

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
</script>

<template>
    <pre
        ref="logRef"
        class="max-h-96 overflow-auto rounded-md bg-gray-900 p-4 text-xs leading-relaxed text-gray-100"
    >{{ output || 'No output yet.' }}</pre>
</template>
