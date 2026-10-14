<script setup lang="ts">
import { ArrowLeft, Palette } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/shared/Heading.vue';
import typesProjets from '@/routes/types-projets';
import museeTemplate from '@/routes/types-projets/musee-template';

type Cours = {
    id: number;
};

type TypeProjet = {
    id: number;
    type: 'standard' | 'musee';
};

defineProps<{
    cours: Cours;
    typeProjet: TypeProjet;
}>();

const { t } = useI18n();
</script>

<template>
    <div>
        <Link
            :href="typesProjets.index.url(cours.id)"
            class="mb-3 flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="h-3.5 w-3.5" />
            {{ t('types_projet.edit.back') }}
        </Link>
        <div class="flex items-start justify-between gap-4">
            <Heading :title="t('types_projet.edit.heading_title')" />
            <Link
                v-if="typeProjet.type === 'musee'"
                :href="
                    museeTemplate.edit.url({
                        cours: cours.id,
                        typeProjet: typeProjet.id,
                    })
                "
                class="flex shrink-0 items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:border-muted-foreground/40 hover:text-foreground"
            >
                <Palette class="h-3.5 w-3.5" />
                Template visuel
            </Link>
        </div>
    </div>
</template>
