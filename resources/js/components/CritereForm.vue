<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import critereRoutes from '@/actions/App/Http/Controllers/TypeProjetCritereController';
import EchelleBuilder, {
    type EchelleNiveau,
} from '@/components/EchelleBuilder.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type Critere = {
    id: number;
    type: 'positif' | 'negatif';
    contenu_type: 'texte' | 'echelle';
    pointage: number;
    contenu: string | null;
    echelle: EchelleNiveau[] | null;
    visible: boolean;
    ordre: number;
};

const props = defineProps<{
    coursId: number;
    typeProjetId: number;
    /** null = critère global (sans section) */
    sectionId: number | null;
    /** Si fourni, on édite ce critère ; sinon on en crée un nouveau. */
    critere?: Critere;
}>();

const emit = defineEmits<{
    /** Émis après une sauvegarde réussie (Inertia a rechargé la page). */
    saved: [];
    /** Émis quand l'utilisateur annule. */
    cancelled: [];
}>();

const { t } = useI18n();

/**
 * Première erreur trouvée parmi les champs de l'échelle (echelle.*.label, etc.).
 * Retourne null s'il n'y a pas d'erreur d'échelle.
 */
const echelleErreur = computed<string | null>(() => {
    const entry = Object.entries(form.errors).find(([key]) =>
        key.startsWith('echelle.'),
    );
    return entry ? entry[1] : null;
});

const defaultEchelle: EchelleNiveau[] = [
    { label: '', points: 0, description: null },
    { label: '', points: 0, description: null },
];

const form = useForm({
    section_id: props.sectionId,
    type: (props.critere?.type ?? 'positif') as 'positif' | 'negatif',
    contenu_type: (props.critere?.contenu_type ?? 'texte') as
        | 'texte'
        | 'echelle',
    pointage: props.critere?.pointage ?? 1,
    contenu: props.critere?.contenu ?? '',
    echelle: (props.critere?.echelle ?? defaultEchelle) as EchelleNiveau[],
    visible: props.critere?.visible ?? true,
});

const estPositif = computed(() => form.type === 'positif');
const montrerEchelle = computed(
    () => form.contenu_type === 'echelle' && estPositif.value,
);

/**
 * Bascule le type entre positif et négatif.
 * Les négatifs ne supportent pas l'échelle — on repasse en texte.
 */
function setType(type: 'positif' | 'negatif') {
    form.type = type;

    if (type === 'negatif') {
        form.contenu_type = 'texte';
    }
}

/**
 * Soumet le formulaire via Inertia (POST = création, PUT = mise à jour).
 */
function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    };

    const transformed = form.transform((data) => ({
        ...data,
        echelle: data.contenu_type === 'echelle' ? data.echelle : null,
    }));

    if (props.critere) {
        transformed.put(
            critereRoutes.update.url({
                cours: props.coursId,
                typeProjet: props.typeProjetId,
                critere: props.critere.id,
            }),
            opts,
        );
    } else {
        transformed.post(
            critereRoutes.store.url({
                cours: props.coursId,
                typeProjet: props.typeProjetId,
            }),
            opts,
        );
    }
}
</script>

<template>
    <div class="space-y-3 rounded-md border bg-muted/30 p-3">
        <!-- ─── Toggle positif / négatif ────────────────────────────────── -->
        <div class="flex gap-1.5">
            <button
                type="button"
                :class="[
                    'rounded-md border px-3 py-1 text-xs font-medium transition-colors',
                    estPositif
                        ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                        : 'border-border text-muted-foreground hover:border-muted-foreground/40',
                ]"
                @click="setType('positif')"
            >
                {{ t('criteres.type_positif') }}
            </button>
            <button
                type="button"
                :class="[
                    'rounded-md border px-3 py-1 text-xs font-medium transition-colors',
                    !estPositif
                        ? 'border-rose-500 bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                        : 'border-border text-muted-foreground hover:border-muted-foreground/40',
                ]"
                @click="setType('negatif')"
            >
                {{ t('criteres.type_negatif') }}
            </button>
        </div>

        <!-- ─── Pointage + mode de saisie ────────────────────────────────── -->
        <div class="flex flex-wrap items-end gap-3">
            <!-- Pointage -->
            <div class="grid gap-1">
                <Label class="text-xs">{{
                    t('criteres.label_pointage')
                }}</Label>
                <Input
                    v-model.number="form.pointage"
                    type="number"
                    min="0"
                    step="0.25"
                    class="w-24 text-sm"
                />
                <InputError :message="form.errors.pointage" />
            </div>

            <!-- Toggle texte / échelle (positif uniquement) -->
            <div v-if="estPositif" class="flex gap-1.5">
                <button
                    type="button"
                    :class="[
                        'rounded-md border px-3 py-1.5 text-xs transition-colors',
                        form.contenu_type === 'texte'
                            ? 'border-primary bg-primary/5 text-primary'
                            : 'border-border text-muted-foreground hover:border-muted-foreground/40',
                    ]"
                    @click="form.contenu_type = 'texte'"
                >
                    {{ t('criteres.contenu_type_texte') }}
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-md border px-3 py-1.5 text-xs transition-colors',
                        form.contenu_type === 'echelle'
                            ? 'border-primary bg-primary/5 text-primary'
                            : 'border-border text-muted-foreground hover:border-muted-foreground/40',
                    ]"
                    @click="form.contenu_type = 'echelle'"
                >
                    {{ t('criteres.contenu_type_echelle') }}
                </button>
            </div>
        </div>

        <!-- ─── Contenu texte ────────────────────────────────────────────── -->
        <div v-if="!montrerEchelle" class="grid gap-1">
            <Label class="text-xs">{{ t('criteres.label_contenu') }}</Label>
            <Textarea v-model="form.contenu" rows="2" class="text-sm" />
            <InputError :message="form.errors.contenu" />
        </div>

        <!-- ─── Constructeur d'échelle ───────────────────────────────────── -->
        <div v-if="montrerEchelle" class="grid gap-1">
            <EchelleBuilder
                v-model="form.echelle"
                :pointage-total="form.pointage"
            />
            <InputError :message="echelleErreur ?? undefined" />
        </div>

        <!-- ─── Visible ──────────────────────────────────────────────────── -->
        <div class="flex items-center gap-2">
            <Checkbox id="critere-visible" v-model:checked="form.visible" />
            <Label for="critere-visible" class="cursor-pointer text-xs">
                {{ t('criteres.label_visible') }}
            </Label>
        </div>

        <!-- ─── Actions ──────────────────────────────────────────────────── -->
        <div class="flex gap-2 pt-1">
            <Button size="sm" :disabled="form.processing" @click="submit">
                {{ t('criteres.btn_enregistrer') }}
            </Button>
            <Button
                size="sm"
                variant="ghost"
                type="button"
                @click="emit('cancelled')"
            >
                {{ t('criteres.btn_annuler') }}
            </Button>
        </div>
    </div>
</template>
