<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type SectionType = 'texte' | 'paragraphes' | 'individuel' | 'entrevue';

type SectionTypeOption = {
    value: SectionType;
    label: string;
    description: string;
};

defineProps<{
    options: SectionTypeOption[];
}>();

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ $t('types_projet.edit.modes_info_title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ $t('types_projet.edit.modes_info_subtitle') }}
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4 pt-2 text-sm">
                <div
                    v-for="mode in options"
                    :key="mode.value"
                    class="flex gap-3"
                >
                    <span
                        class="mt-0.5 shrink-0 rounded border px-2 py-0.5 text-xs font-medium text-muted-foreground"
                    >
                        {{ mode.label }}
                    </span>
                    <div>
                        <p class="font-medium">{{ mode.label }}</p>
                        <p class="text-muted-foreground">
                            {{ mode.description }}
                        </p>
                        <p
                            class="mt-0.5 text-xs text-muted-foreground/70 italic"
                        >
                            {{
                                $t(
                                    `types_projet.edit.modes_info_exemple_${mode.value}`,
                                )
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
