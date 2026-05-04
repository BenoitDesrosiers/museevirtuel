<script setup lang="ts">
import axios from 'axios';
import { ImagePlus, Plus, Trash2, GripVertical } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

// ─── Types ────────────────────────────────────────────────────────────────────

type CarteSchema = {
    id: string;
    texte: string;
    image: string | null;
};

type ZonesSchema = {
    causes: CarteSchema[];
    activites: CarteSchema[];
    consequences: CarteSchema[];
};

type ContenuSchema = {
    image_centrale: string | null;
    zones: ZonesSchema;
};

type RouteParams = {
    cours: number;
    classe: number;
    groupe: number;
    typeProjet: number;
    section: number;
};

// ─── Props ────────────────────────────────────────────────────────────────────

const props = defineProps<{
    params: RouteParams;
    schemaVisuel: ContenuSchema | null;
    readOnly: boolean;
}>();

// ─── État local ───────────────────────────────────────────────────────────────

function contenuVide(): ContenuSchema {
    return {
        image_centrale: null,
        zones: { causes: [], activites: [], consequences: [] },
    };
}

const etat = reactive<ContenuSchema>(
    props.schemaVisuel
        ? JSON.parse(JSON.stringify(props.schemaVisuel))
        : contenuVide(),
);

const enregistrement = ref(false);
const erreurSauvegarde = ref<string | null>(null);
const uploadEnCours = reactive<Record<string, boolean>>({});

// ─── URL base de la route schema ─────────────────────────────────────────────

const urlSchema = computed(() => {
    const p = props.params;
    return `/cours/${p.cours}/classes/${p.classe}/groupes/${p.groupe}/projets/${p.typeProjet}/sections/${p.section}/schema`;
});

// ─── Sauvegarde debounce ──────────────────────────────────────────────────────

let saveTimer: ReturnType<typeof setTimeout> | null = null;

function planifierSauvegarde(): void {
    if (props.readOnly) return;
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(() => sauvegarder(), 800);
}

async function sauvegarder(): Promise<void> {
    if (props.readOnly) return;
    enregistrement.value = true;
    erreurSauvegarde.value = null;
    try {
        await axios.put(
            urlSchema.value,
            { contenu: JSON.parse(JSON.stringify(etat)) },
            {
                headers: {
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
            },
        );
    } catch {
        erreurSauvegarde.value = 'Erreur lors de la sauvegarde.';
    } finally {
        enregistrement.value = false;
    }
}

// Déclencher la sauvegarde sur tout changement profond
watch(etat, () => planifierSauvegarde(), { deep: true });

// ─── Gestion cartes ───────────────────────────────────────────────────────────

function nouvelleId(): string {
    return crypto.randomUUID();
}

function ajouterCarte(zone: keyof ZonesSchema): void {
    etat.zones[zone].push({ id: nouvelleId(), texte: '', image: null });
}

function supprimerCarte(zone: keyof ZonesSchema, id: string): void {
    etat.zones[zone] = etat.zones[zone].filter((c) => c.id !== id);
}

// ─── Upload image (centrale ou carte) ────────────────────────────────────────

async function uploadImage(
    fichier: File,
    callback: (url: string) => void,
): Promise<void> {
    const formData = new FormData();
    formData.append('image', fichier);

    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';

    const resp = await axios.post<{ url: string }>(
        `${urlSchema.value}/images`,
        formData,
        { headers: { 'X-CSRF-TOKEN': csrfToken } },
    );
    callback(resp.data.url);
}

async function onImageCentrale(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const fichier = input.files?.[0];
    if (!fichier) return;
    const cle = 'centrale';
    uploadEnCours[cle] = true;
    try {
        await uploadImage(fichier, (url) => {
            etat.image_centrale = url;
        });
    } finally {
        uploadEnCours[cle] = false;
        input.value = '';
    }
}

async function onImageCarte(
    zone: keyof ZonesSchema,
    id: string,
    event: Event,
): Promise<void> {
    const input = event.target as HTMLInputElement;
    const fichier = input.files?.[0];
    if (!fichier) return;
    const cle = `${zone}-${id}`;
    uploadEnCours[cle] = true;
    try {
        await uploadImage(fichier, (url) => {
            const carte = etat.zones[zone].find((c) => c.id === id);
            if (carte) carte.image = url;
        });
    } finally {
        uploadEnCours[cle] = false;
        input.value = '';
    }
}

// ─── Labels des zones ─────────────────────────────────────────────────────────

const zonesConfig: { key: keyof ZonesSchema; label: string; couleur: string }[] =
    [
        { key: 'causes', label: 'Causes', couleur: 'bg-red-50 border-red-200' },
        {
            key: 'activites',
            label: 'Activités',
            couleur: 'bg-blue-50 border-blue-200',
        },
        {
            key: 'consequences',
            label: 'Conséquences',
            couleur: 'bg-green-50 border-green-200',
        },
    ];
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Indicateur de sauvegarde -->
        <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <span v-if="enregistrement">Sauvegarde en cours…</span>
            <span v-else-if="erreurSauvegarde" class="text-destructive">
                {{ erreurSauvegarde }}
            </span>
            <span v-else>Les modifications sont sauvegardées automatiquement.</span>
        </div>

        <!-- Image centrale du sujet ─────────────────────────────────────────── -->
        <div class="flex flex-col items-center gap-3">
            <p class="text-sm font-medium">Image du sujet (optionnel)</p>

            <div
                v-if="etat.image_centrale"
                class="relative w-full max-w-sm overflow-hidden rounded-lg border shadow-sm"
            >
                <img
                    :src="etat.image_centrale"
                    alt="Image centrale du schéma"
                    class="w-full object-cover"
                />
                <button
                    v-if="!readOnly"
                    type="button"
                    class="absolute right-2 top-2 rounded-full bg-destructive/90 p-1 text-white hover:bg-destructive"
                    @click="etat.image_centrale = null"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                </button>
            </div>

            <label
                v-if="!readOnly && !uploadEnCours['centrale']"
                class="cursor-pointer"
            >
                <Button type="button" variant="outline" size="sm" as="span">
                    <ImagePlus class="mr-1.5 h-4 w-4" />
                    {{ etat.image_centrale ? 'Remplacer l\'image' : 'Ajouter une image' }}
                </Button>
                <input
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="onImageCentrale"
                />
            </label>
            <span v-if="uploadEnCours['centrale']" class="text-xs text-muted-foreground">
                Téléversement en cours…
            </span>
        </div>

        <!-- Grille 3 zones ───────────────────────────────────────────────────── -->
        <div class="grid gap-4 md:grid-cols-3">
            <div
                v-for="zone in zonesConfig"
                :key="zone.key"
                :class="['rounded-lg border-2 p-3', zone.couleur]"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-semibold">{{ zone.label }}</h3>
                    <Button
                        v-if="!readOnly"
                        type="button"
                        size="sm"
                        variant="ghost"
                        class="h-7 px-2"
                        @click="ajouterCarte(zone.key)"
                    >
                        <Plus class="h-3.5 w-3.5" />
                    </Button>
                </div>

                <!-- Liste de cartes — drag-and-drop entre zones -->
                <VueDraggable
                    v-model="etat.zones[zone.key]"
                    :disabled="readOnly"
                    :animation="150"
                    group="schema-zones"
                    handle=".drag-handle"
                    class="flex min-h-12 flex-col gap-2"
                    item-key="id"
                >
                    <div
                        v-for="carte in etat.zones[zone.key]"
                        :key="carte.id"
                        class="rounded-md border bg-white p-2 shadow-sm"
                    >
                        <!-- Poignée de drag -->
                        <div class="mb-1 flex items-center gap-1">
                            <GripVertical
                                v-if="!readOnly"
                                class="drag-handle h-4 w-4 shrink-0 cursor-grab text-muted-foreground"
                            />
                            <span v-if="readOnly" class="flex-1 text-sm">
                                {{ carte.texte || '—' }}
                            </span>
                            <Input
                                v-else
                                v-model="carte.texte"
                                class="h-7 flex-1 text-sm"
                                placeholder="Texte de la carte"
                            />
                            <Button
                                v-if="!readOnly"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 w-7 shrink-0 p-0 text-destructive hover:text-destructive"
                                @click="supprimerCarte(zone.key, carte.id)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>

                        <!-- Image de la carte -->
                        <div class="mt-1">
                            <img
                                v-if="carte.image"
                                :src="carte.image"
                                :alt="`Image — ${carte.texte}`"
                                class="w-full rounded object-cover"
                                style="max-height: 120px"
                            />
                            <div v-if="!readOnly" class="mt-1 flex items-center gap-1">
                                <label class="cursor-pointer">
                                    <span
                                        class="text-xs text-muted-foreground underline-offset-2 hover:underline"
                                    >
                                        {{
                                            uploadEnCours[`${zone.key}-${carte.id}`]
                                                ? 'Téléversement…'
                                                : carte.image
                                                  ? 'Changer l\'image'
                                                  : '+ image'
                                        }}
                                    </span>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="(e) => onImageCarte(zone.key, carte.id, e)"
                                    />
                                </label>
                                <button
                                    v-if="carte.image"
                                    type="button"
                                    class="text-xs text-destructive hover:underline"
                                    @click="carte.image = null"
                                >
                                    retirer
                                </button>
                            </div>
                        </div>
                    </div>
                </VueDraggable>

                <p
                    v-if="etat.zones[zone.key].length === 0 && readOnly"
                    class="text-center text-xs text-muted-foreground"
                >
                    Aucune carte.
                </p>
            </div>
        </div>
    </div>
</template>
