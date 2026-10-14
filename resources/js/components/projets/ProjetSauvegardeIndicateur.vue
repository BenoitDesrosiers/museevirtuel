<script setup lang="ts">
import { CheckCircle2, Loader2 } from 'lucide-vue-next';

defineProps<{
    visible: boolean;
    status: 'idle' | 'saving' | 'saved' | 'error';
    annotationDeleteError: string | null;
}>();
</script>

<template>
    <div
        v-if="visible"
        class="flex items-center gap-2 text-sm text-muted-foreground"
    >
        <Loader2
            v-if="status === 'saving'"
            class="h-4 w-4 animate-spin"
        />
        <CheckCircle2
            v-else-if="status === 'saved'"
            class="h-4 w-4 text-green-500"
        />

        <span v-if="status === 'saving'">{{ $t('projets.show.saving') }}</span>
        <span
            v-else-if="status === 'saved'"
            class="text-green-600"
            >{{ $t('projets.show.saved') }}</span
        >
        <span
            v-else-if="status === 'error'"
            class="text-destructive"
            >{{ $t('projets.show.save_error') }}</span
        >
        <span v-if="annotationDeleteError" class="text-destructive">
            {{ annotationDeleteError }}
        </span>
    </div>
</template>
