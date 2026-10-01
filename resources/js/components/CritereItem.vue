<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import type { Critere } from '@/components/CritereForm.vue';

defineProps<{
    critere: Critere;
}>();

const emit = defineEmits<{
    edit: [id: number];
    delete: [id: number];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="flex items-start gap-2 rounded-md border px-3 py-2 text-sm">
        <span
            :class="[
                'mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-xs font-medium',
                critere.type === 'positif'
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                    : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
            ]"
        >
            {{ critere.type === 'positif' ? '+' + critere.pointage : '-' + critere.pointage }}
        </span>
        <span class="min-w-0 flex-1 text-xs">
            {{ critere.contenu }}
            <span
                v-if="critere.contenu_type === 'echelle'"
                class="ml-1 text-muted-foreground"
                >(échelle)</span
            >
        </span>
        <span
            v-if="!critere.visible"
            class="shrink-0 text-xs text-muted-foreground"
        >
            masqué
        </span>
        <div class="flex shrink-0 gap-1">
            <button
                type="button"
                class="text-muted-foreground hover:text-foreground"
                @click="emit('edit', critere.id)"
            >
                <span class="text-xs">{{ t('criteres.btn_modifier') }}</span>
            </button>
            <button
                type="button"
                class="text-muted-foreground hover:text-destructive"
                @click="emit('delete', critere.id)"
            >
                <Trash2 class="h-3.5 w-3.5" />
            </button>
        </div>
    </div>
</template>
