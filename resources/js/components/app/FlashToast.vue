<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CheckCircle, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const page = usePage();

const flash = computed(
    () => page.props.flash as { success?: string; error?: string },
);

const visible = ref(false);
const current = ref<{ type: 'success' | 'error'; message: string } | null>(
    null,
);
let timer: ReturnType<typeof setTimeout> | null = null;

watch(
    flash,
    (val) => {
        if (val.success) {
            show('success', val.success);
        } else if (val.error) {
            show('error', val.error);
        }
    },
    { immediate: true },
);

function show(type: 'success' | 'error', message: string) {
    if (timer) {
        clearTimeout(timer);
    }

    current.value = { type, message };
    visible.value = true;
    timer = setTimeout(() => {
        visible.value = false;
    }, 4000);
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0"
    >
        <div
            v-if="visible && current"
            class="fixed right-6 bottom-6 z-50 flex items-center gap-3 rounded-lg px-4 py-3 shadow-lg"
            :class="{
                'bg-green-600 text-white': current.type === 'success',
                'bg-destructive text-destructive-foreground':
                    current.type === 'error',
            }"
        >
            <CheckCircle
                v-if="current.type === 'success'"
                class="h-5 w-5 shrink-0"
            />
            <XCircle v-else class="h-5 w-5 shrink-0" />
            <span class="text-sm font-medium">{{ current.message }}</span>
        </div>
    </Transition>
</template>
