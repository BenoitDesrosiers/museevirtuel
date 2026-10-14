<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, Eye, Palette, Type } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/shared/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import museeTemplate from '@/routes/types-projets/musee-template';
import typesProjets from '@/routes/types-projets';
import MuseeCanevasEditor, { type Canevas } from './MuseeCanevasEditor.vue';

// ─── Types ────────────────────────────────────────────────────────────────────

type Cours = {
    id: number;
    nom_cours: string;
    code: string;
};

type TypeProjet = {
    id: number;
    nom: string;
};

type Template = {
    id: number;
    font_titre_page: string;
    font_sous_titre: string;
    font_titre_section: string;
    font_corps: string;
    font_legende: string;
    couleur_fond: string;
    couleur_titre: string;
    couleur_corps: string;
    couleur_accent: string;
    couleur_lien_externe: string;
    palette: string | null;
    theme: 'clair' | 'sombre';
};

type Section = {
    id: number;
    label: string;
    ordre: number;
    musee_canevas: Canevas | null;
    est_obligatoire: boolean;
    est_reutilisable: boolean;
    min_occurrences: number;
    max_occurrences: number | null;
};

type Props = {
    cours: Cours;
    typeProjet: TypeProjet;
    template: Template;
    sections: Section[];
};

// ─── Constantes ───────────────────────────────────────────────────────────────

type PaletteId = 'classique' | 'contemporain' | 'nature' | 'nuit' | 'pastel';

type PaletteDefinition = {
    id: PaletteId;
    nom: string;
    theme: 'clair' | 'sombre';
    couleur_fond: string;
    couleur_titre: string;
    couleur_corps: string;
    couleur_accent: string;
    couleur_lien_externe: string;
};

const PALETTES: PaletteDefinition[] = [
    {
        id: 'classique',
        nom: 'Musée classique',
        theme: 'clair',
        couleur_fond: '#F5EFE0',
        couleur_titre: '#1A2744',
        couleur_corps: '#2C2C2C',
        couleur_accent: '#C9A040',
        couleur_lien_externe: '#E07010',
    },
    {
        id: 'contemporain',
        nom: 'Contemporain',
        theme: 'clair',
        couleur_fond: '#FFFFFF',
        couleur_titre: '#111111',
        couleur_corps: '#333333',
        couleur_accent: '#C0392B',
        couleur_lien_externe: '#E74C3C',
    },
    {
        id: 'nature',
        nom: 'Nature & Terroir',
        theme: 'clair',
        couleur_fond: '#F0F4EE',
        couleur_titre: '#1E3A2F',
        couleur_corps: '#2C3E2D',
        couleur_accent: '#C0672A',
        couleur_lien_externe: '#D4813A',
    },
    {
        id: 'nuit',
        nom: 'Nuit de musée',
        theme: 'sombre',
        couleur_fond: '#1A1A2E',
        couleur_titre: '#E8D5A3',
        couleur_corps: '#C8C8D8',
        couleur_accent: '#C9A040',
        couleur_lien_externe: '#F0B040',
    },
    {
        id: 'pastel',
        nom: 'Pastel doux',
        theme: 'clair',
        couleur_fond: '#F4F0F8',
        couleur_titre: '#3D3560',
        couleur_corps: '#3A3A4A',
        couleur_accent: '#9B59B6',
        couleur_lien_externe: '#8E44AD',
    },
];

const FONTS_TITRES = [
    { value: 'Georgia', label: 'Georgia' },
    { value: 'Playfair Display', label: 'Playfair Display' },
    { value: 'Lora', label: 'Lora' },
    { value: 'Cormorant Garamond', label: 'Cormorant Garamond' },
    { value: 'Libre Baskerville', label: 'Libre Baskerville' },
];

const FONTS_CORPS = [
    { value: 'Arial', label: 'Arial' },
    { value: 'Source Sans 3', label: 'Source Sans 3' },
    { value: 'Open Sans', label: 'Open Sans' },
    { value: 'Merriweather', label: 'Merriweather' },
    { value: 'Crimson Text', label: 'Crimson Text' },
];

// ─── État ─────────────────────────────────────────────────────────────────────

const props = defineProps<Props>();

const form = useForm({
    font_titre_page: props.template.font_titre_page,
    font_sous_titre: props.template.font_sous_titre,
    font_titre_section: props.template.font_titre_section,
    font_corps: props.template.font_corps,
    font_legende: props.template.font_legende,
    couleur_fond: props.template.couleur_fond,
    couleur_titre: props.template.couleur_titre,
    couleur_corps: props.template.couleur_corps,
    couleur_accent: props.template.couleur_accent,
    couleur_lien_externe: props.template.couleur_lien_externe,
    palette: props.template.palette as string | null,
    theme: props.template.theme,
});

/** Palette actuellement sélectionnée (null = personnalisée). */
const paletteActive = ref<PaletteId | null>(
    (props.template.palette as PaletteId) ?? null,
);

/** URL Google Fonts pour charger les polices sélectionnées. */
const googleFontsUrl = computed(() => {
    const families = new Set<string>();
    [
        form.font_titre_page,
        form.font_sous_titre,
        form.font_titre_section,
        form.font_corps,
        form.font_legende,
    ].forEach((f) => {
        if (f !== 'Arial' && f !== 'Georgia') {
            families.add(f.replace(/ /g, '+'));
        }
    });

    if (families.size === 0) return null;

    return `https://fonts.googleapis.com/css2?${[...families].map((f) => `family=${f}:wght@400;600;700`).join('&')}&display=swap`;
});

/** Variables CSS générées dynamiquement depuis le formulaire, pour le preview. */
const previewStyle = computed(() => ({
    '--musee-font-titre-page': form.font_titre_page,
    '--musee-font-sous-titre': form.font_sous_titre,
    '--musee-font-titre-section': form.font_titre_section,
    '--musee-font-corps': form.font_corps,
    '--musee-font-legende': form.font_legende,
    '--musee-couleur-fond': form.couleur_fond,
    '--musee-couleur-titre': form.couleur_titre,
    '--musee-couleur-corps': form.couleur_corps,
    '--musee-couleur-accent': form.couleur_accent,
    '--musee-couleur-lien-externe': form.couleur_lien_externe,
}));

// ─── Méthodes ─────────────────────────────────────────────────────────────────

/**
 * Applique une palette prédéfinie : remplace toutes les couleurs et le thème.
 */
function appliquerPalette(palette: PaletteDefinition): void {
    paletteActive.value = palette.id;
    form.palette = palette.id;
    form.theme = palette.theme;
    form.couleur_fond = palette.couleur_fond;
    form.couleur_titre = palette.couleur_titre;
    form.couleur_corps = palette.couleur_corps;
    form.couleur_accent = palette.couleur_accent;
    form.couleur_lien_externe = palette.couleur_lien_externe;
}

/**
 * Quand l'utilisateur modifie une couleur manuellement, déselectionner la palette.
 */
function onCouleurChange(): void {
    paletteActive.value = null;
    form.palette = null;
}

/**
 * Soumet le formulaire de mise à jour du template.
 */
function sauvegarder(): void {
    form.put(
        museeTemplate.update.url({
            cours: props.cours.id,
            typeProjet: props.typeProjet.id,
        }),
    );
}
</script>

<template>
    <Head :title="`Template — ${typeProjet.nom}`" />

    <!-- Charger les Google Fonts sélectionnées -->
    <link
        v-if="googleFontsUrl"
        rel="stylesheet"
        :href="googleFontsUrl"
    />

    <AppLayout>
        <div class="mx-auto flex max-w-5xl flex-col gap-6 p-6">
            <!-- En-tête -->
            <div>
                <Link
                    :href="typesProjets.edit.url({ cours: cours.id, typeProjet: typeProjet.id })"
                    class="mb-3 flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    Retour à la configuration
                </Link>
                <Heading
                    :title="`Template visuel — ${typeProjet.nom}`"
                    description="Personnalisez les polices et couleurs du musée de vos étudiants."
                />
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- ── Panneau gauche : paramètres ─────────────────────────── -->
                <div class="flex flex-col gap-6">
                    <!-- Palettes prédéfinies -->
                    <Card>
                        <CardContent class="pt-6">
                            <div class="mb-4 flex items-center gap-2">
                                <Palette class="h-4 w-4 text-muted-foreground" />
                                <h2 class="text-sm font-semibold">
                                    Palette de couleurs
                                </h2>
                            </div>

                            <div class="grid grid-cols-1 gap-2">
                                <button
                                    v-for="palette in PALETTES"
                                    :key="palette.id"
                                    type="button"
                                    :class="[
                                        'flex items-center justify-between rounded-md border px-3 py-2.5 text-left text-sm transition-colors',
                                        paletteActive === palette.id
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border hover:border-muted-foreground/40',
                                    ]"
                                    @click="appliquerPalette(palette)"
                                >
                                    <div class="flex items-center gap-3">
                                        <!-- Aperçu des couleurs -->
                                        <div class="flex gap-1">
                                            <div
                                                class="h-5 w-5 rounded-full border border-border"
                                                :style="{ background: palette.couleur_fond }"
                                            />
                                            <div
                                                class="h-5 w-5 rounded-full border border-border"
                                                :style="{ background: palette.couleur_titre }"
                                            />
                                            <div
                                                class="h-5 w-5 rounded-full border border-border"
                                                :style="{ background: palette.couleur_accent }"
                                            />
                                        </div>
                                        <span class="font-medium">{{ palette.nom }}</span>
                                        <span
                                            v-if="palette.theme === 'sombre'"
                                            class="rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-200"
                                        >
                                            sombre
                                        </span>
                                    </div>
                                    <Check
                                        v-if="paletteActive === palette.id"
                                        class="h-4 w-4 shrink-0 text-primary"
                                    />
                                </button>
                            </div>

                            <!-- Couleurs personnalisées -->
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Fond</Label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="form.couleur_fond"
                                            type="color"
                                            class="h-8 w-8 cursor-pointer rounded border border-border"
                                            @input="onCouleurChange"
                                        />
                                        <span class="font-mono text-xs text-muted-foreground">
                                            {{ form.couleur_fond }}
                                        </span>
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Titres</Label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="form.couleur_titre"
                                            type="color"
                                            class="h-8 w-8 cursor-pointer rounded border border-border"
                                            @input="onCouleurChange"
                                        />
                                        <span class="font-mono text-xs text-muted-foreground">
                                            {{ form.couleur_titre }}
                                        </span>
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Corps du texte</Label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="form.couleur_corps"
                                            type="color"
                                            class="h-8 w-8 cursor-pointer rounded border border-border"
                                            @input="onCouleurChange"
                                        />
                                        <span class="font-mono text-xs text-muted-foreground">
                                            {{ form.couleur_corps }}
                                        </span>
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Accent</Label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="form.couleur_accent"
                                            type="color"
                                            class="h-8 w-8 cursor-pointer rounded border border-border"
                                            @input="onCouleurChange"
                                        />
                                        <span class="font-mono text-xs text-muted-foreground">
                                            {{ form.couleur_accent }}
                                        </span>
                                    </div>
                                </div>

                                <div class="col-span-2 grid gap-1.5">
                                    <Label class="text-xs">Liens externes</Label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="form.couleur_lien_externe"
                                            type="color"
                                            class="h-8 w-8 cursor-pointer rounded border border-border"
                                            @input="onCouleurChange"
                                        />
                                        <span class="font-mono text-xs text-muted-foreground">
                                            {{ form.couleur_lien_externe }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Polices -->
                    <Card>
                        <CardContent class="pt-6">
                            <div class="mb-4 flex items-center gap-2">
                                <Type class="h-4 w-4 text-muted-foreground" />
                                <h2 class="text-sm font-semibold">Polices</h2>
                            </div>

                            <div class="grid gap-4">
                                <!-- Titre de page -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Titre de page</Label>
                                    <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                                        <button
                                            v-for="font in FONTS_TITRES"
                                            :key="font.value"
                                            type="button"
                                            :class="[
                                                'rounded border px-2 py-1.5 text-left text-xs transition-colors',
                                                form.font_titre_page === font.value
                                                    ? 'border-primary bg-primary/5 text-primary'
                                                    : 'border-border hover:border-muted-foreground/40',
                                            ]"
                                            :style="{ fontFamily: font.value }"
                                            @click="form.font_titre_page = font.value"
                                        >
                                            {{ font.label }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Titre de section -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Titre de section</Label>
                                    <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                                        <button
                                            v-for="font in FONTS_TITRES"
                                            :key="font.value"
                                            type="button"
                                            :class="[
                                                'rounded border px-2 py-1.5 text-left text-xs transition-colors',
                                                form.font_titre_section === font.value
                                                    ? 'border-primary bg-primary/5 text-primary'
                                                    : 'border-border hover:border-muted-foreground/40',
                                            ]"
                                            :style="{ fontFamily: font.value }"
                                            @click="form.font_titre_section = font.value"
                                        >
                                            {{ font.label }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Corps du texte -->
                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Corps du texte</Label>
                                    <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                                        <button
                                            v-for="font in FONTS_CORPS"
                                            :key="font.value"
                                            type="button"
                                            :class="[
                                                'rounded border px-2 py-1.5 text-left text-xs transition-colors',
                                                form.font_corps === font.value
                                                    ? 'border-primary bg-primary/5 text-primary'
                                                    : 'border-border hover:border-muted-foreground/40',
                                            ]"
                                            :style="{ fontFamily: font.value }"
                                            @click="form.font_corps = font.value"
                                        >
                                            {{ font.label }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Bouton sauvegarder -->
                    <div class="flex justify-end">
                        <Button :disabled="form.processing" @click="sauvegarder">
                            {{ form.processing ? 'Enregistrement…' : 'Sauvegarder le template' }}
                        </Button>
                    </div>

                    <!-- ── Canevas de zones par section ──────────────── -->
                    <Card v-if="sections.length > 0">
                        <CardContent class="pt-6">
                            <div class="mb-4 flex items-center gap-2">
                                <Eye class="h-4 w-4 text-muted-foreground" />
                                <h2 class="text-sm font-semibold">Canevas de zones par section</h2>
                            </div>
                            <p class="mb-4 text-xs text-muted-foreground">
                                Définissez la disposition des zones que les étudiants devront remplir.
                                Choisissez un preset ou construisez votre mise en page manuellement.
                            </p>

                            <div class="flex flex-col gap-4">
                                <MuseeCanevasEditor
                                    v-for="section in sections"
                                    :key="section.id"
                                    :section-id="section.id"
                                    :section-label="section.label"
                                    :cours-id="cours.id"
                                    :type-projet-id="typeProjet.id"
                                    :initial-canevas="section.musee_canevas"
                                    :est-obligatoire="section.est_obligatoire"
                                    :est-reutilisable="section.est_reutilisable"
                                    :min-occurrences="section.min_occurrences"
                                    :max-occurrences="section.max_occurrences"
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- ── Panneau droit : preview ──────────────────────────────── -->
                <div class="sticky top-6 self-start">
                    <Card>
                        <CardContent class="p-0 overflow-hidden rounded-xl">
                            <div
                                class="flex items-center gap-1.5 border-b px-4 py-2.5 text-xs text-muted-foreground"
                            >
                                <Eye class="h-3.5 w-3.5" />
                                Aperçu en direct
                            </div>

                            <!-- Rendu preview avec les CSS variables injectées -->
                            <div
                                class="musee-preview min-h-[420px] p-6"
                                :style="previewStyle"
                            >
                                <!-- En-tête fictif -->
                                <div
                                    class="mb-6 border-b-2 pb-4"
                                    :style="{ borderColor: 'var(--musee-couleur-accent)' }"
                                >
                                    <h1
                                        class="mb-1 text-2xl font-bold leading-tight"
                                        :style="{
                                            fontFamily: 'var(--musee-font-titre-page)',
                                            color: 'var(--musee-couleur-titre)',
                                        }"
                                    >
                                        La Révolution industrielle
                                    </h1>
                                    <p
                                        class="text-sm"
                                        :style="{
                                            fontFamily: 'var(--musee-font-sous-titre)',
                                            color: 'var(--musee-couleur-corps)',
                                            opacity: '0.75',
                                        }"
                                    >
                                        Musée virtuel · Groupe 3
                                    </p>
                                </div>

                                <!-- Section fictive -->
                                <h2
                                    class="mb-2 text-base font-semibold"
                                    :style="{
                                        fontFamily: 'var(--musee-font-titre-section)',
                                        color: 'var(--musee-couleur-titre)',
                                    }"
                                >
                                    Les machines à vapeur
                                </h2>

                                <!-- Paragraphe fictif -->
                                <p
                                    class="mb-3 text-sm leading-relaxed"
                                    :style="{
                                        fontFamily: 'var(--musee-font-corps)',
                                        color: 'var(--musee-couleur-corps)',
                                    }"
                                >
                                    Au cours du XIX&nbsp;e siècle, l'invention de la machine
                                    à vapeur par
                                    <a
                                        href="#"
                                        :style="{ color: 'var(--musee-couleur-lien-externe)' }"
                                        @click.prevent
                                    >James Watt</a>
                                    transforma profondément les modes de production.
                                </p>

                                <!-- Légende fictive -->
                                <p
                                    class="text-xs italic"
                                    :style="{
                                        fontFamily: 'var(--musee-font-legende)',
                                        color: 'var(--musee-couleur-corps)',
                                        opacity: '0.65',
                                    }"
                                >
                                    Fig. 1 — Machine à vapeur double effet, 1782.
                                </p>

                                <!-- Bouton accent fictif -->
                                <div class="mt-4">
                                    <span
                                        class="inline-block rounded px-3 py-1.5 text-xs font-semibold text-white"
                                        :style="{ background: 'var(--musee-couleur-accent)' }"
                                    >
                                        Voir la galerie
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Fond du preview -->
                    <div
                        class="mt-0.5 rounded-b-xl px-4 py-2 text-xs text-muted-foreground"
                        :style="{ background: form.couleur_fond }"
                    >
                        Couleur de fond : {{ form.couleur_fond }}
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.musee-preview {
    background-color: v-bind('form.couleur_fond');
    transition: background-color 0.2s ease;
}
</style>
