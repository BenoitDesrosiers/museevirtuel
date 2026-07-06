<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import CritereForm from '@/components/CritereForm.vue';
import type { Critere } from '@/components/CritereForm.vue';

/**
 * Affiche une liste de critères de correction en mode tableau.
 *
 * Gère l'affichage inline du formulaire d'édition lorsqu'un critère
 * est sélectionné, et délègue les actions edit/delete au parent.
 *
 * @example Critères globaux
 * <CritereTable
 *     :criteres="criteresGlobaux"
 *     :cours-id="cours.id"
 *     :type-projet-id="typeProjet.id"
 *     :section-id="null"
 *     :critere-en-edition="critereEnEdition"
 *     @edit="ouvrirFormEdition"
 *     @delete="supprimerCritere"
 *     @close="fermerForms"
 * />
 */
defineProps<{
    criteres: Critere[];
    coursId: number;
    typeProjetId: number;
    /** null = critères globaux (sans section). */
    sectionId: number | null;
    /** ID du critère dont le formulaire d'édition est ouvert (null = aucun). */
    critereEnEdition: number | null;
}>();

const emit = defineEmits<{
    /** Demande l'ouverture du formulaire d'édition pour ce critère. */
    edit: [id: number];
    /** Demande la suppression de ce critère. */
    delete: [id: number];
    /** Demande la fermeture du formulaire ouvert (après sauvegarde ou annulation). */
    close: [];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b text-muted-foreground">
                    <th class="pb-1.5 pr-3 text-left font-medium">{{ t('criteres.col_type') }}</th>
                    <th class="pb-1.5 pr-3 text-left font-medium">{{ t('criteres.col_description') }}</th>
                    <th class="pb-1.5 pr-3 text-left font-medium">{{ t('criteres.col_pts') }}</th>
                    <th class="pb-1.5 pr-3 text-left font-medium">{{ t('criteres.col_mode') }}</th>
                    <th class="pb-1.5 pr-3 text-left font-medium">{{ t('criteres.col_visible') }}</th>
                    <th class="pb-1.5 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <template v-for="critere in criteres" :key="critere.id">
                    <tr class="align-top">
                        <td class="py-1.5 pr-3">
                            <span
                                :class="[
                                    'rounded px-1.5 py-0.5 font-medium',
                                    critere.type === 'positif'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                        : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
                                ]"
                            >
                                {{ critere.type === 'positif' ? t('criteres.type_positif') : t('criteres.type_negatif') }}
                            </span>
                        </td>
                        <td class="max-w-[220px] py-1.5 pr-3">
                            <span class="line-clamp-2">{{ critere.contenu || '—' }}</span>
                        </td>
                        <td class="py-1.5 pr-3 tabular-nums">
                            {{ critere.type === 'positif' ? '+' : '-' }}{{ critere.pointage }}
                        </td>
                        <td class="py-1.5 pr-3 capitalize">{{ critere.contenu_type }}</td>
                        <td class="py-1.5 pr-3">{{ critere.visible ? '✓' : '—' }}</td>
                        <td class="py-1.5 text-right">
                            <div class="flex justify-end gap-1">
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground"
                                    @click="emit('edit', critere.id)"
                                >
                                    {{ t('criteres.btn_modifier') }}
                                </button>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-destructive"
                                    @click="emit('delete', critere.id)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="critereEnEdition === critere.id">
                        <td colspan="6" class="py-2">
                            <CritereForm
                                :cours-id="coursId"
                                :type-projet-id="typeProjetId"
                                :section-id="sectionId"
                                :critere="critere"
                                @saved="emit('close')"
                                @cancelled="emit('close')"
                            />
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</template>
