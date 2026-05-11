<script setup lang="ts">
import { BookOpen } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

// ─── Types ────────────────────────────────────────────────────────────────────

type TypeRef =
    | 'livre'
    | 'article_periodique'
    | 'article_journal'
    | 'site_internet'
    | 'document_audiovisuel'
    | 'memoire_these'
    | 'ouvrage_reference';

type ChampConfig = {
    key: string;
    label: string;
    placeholder: string;
    optional?: boolean;
};

// ─── Props / Emits ────────────────────────────────────────────────────────────

const props = defineProps<{
    open: boolean;
    /** Renvoi existant à modifier — absent en mode création */
    renvoi?: { id: number; type_reference: string; champs_reference: Record<string, string> } | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    inserer: [contenu: string, typeReference: string, champsReference: Record<string, string>];
}>();

// ─── Configuration des types de références ────────────────────────────────────

const TYPES_CONFIG: Record<TypeRef, { label: string; champs: ChampConfig[] }> = {
    livre: {
        label: 'Livre',
        champs: [
            { key: 'auteurs', label: 'Auteur(s)', placeholder: 'Nom, P.  ou  Nom, P., & Nom, P.' },
            { key: 'annee', label: 'Année de publication', placeholder: '2023' },
            { key: 'titre', label: 'Titre du livre', placeholder: 'Histoire du Québec moderne' },
            { key: 'edition', label: 'Édition', placeholder: '2e éd.', optional: true },
            { key: 'editeur', label: 'Éditeur', placeholder: 'Les Presses de l\'Université Laval' },
        ],
    },
    article_periodique: {
        label: 'Article de périodique',
        champs: [
            { key: 'auteurs', label: 'Auteur(s)', placeholder: 'Nom, P.  ou  Nom, P., & Nom, P.' },
            { key: 'annee', label: 'Année', placeholder: '2023' },
            { key: 'titre_article', label: 'Titre de l\'article', placeholder: 'La Révolution tranquille revisitée' },
            { key: 'titre_revue', label: 'Titre de la revue', placeholder: 'Revue d\'histoire de l\'Amérique française' },
            { key: 'volume', label: 'Volume', placeholder: '76' },
            { key: 'numero', label: 'Numéro', placeholder: '2' },
            { key: 'pages', label: 'Pages', placeholder: '45–78' },
            { key: 'doi', label: 'DOI ou URL', placeholder: 'https://doi.org/…', optional: true },
        ],
    },
    article_journal: {
        label: 'Article de journal',
        champs: [
            { key: 'auteurs', label: 'Auteur(s)', placeholder: 'Nom, P.' },
            { key: 'date', label: 'Date', placeholder: '2023, 15 mars' },
            { key: 'titre_article', label: 'Titre de l\'article', placeholder: 'La crise du logement à Montréal' },
            { key: 'nom_journal', label: 'Nom du journal', placeholder: 'Le Devoir' },
            { key: 'url', label: 'URL', placeholder: 'https://…', optional: true },
        ],
    },
    site_internet: {
        label: 'Site Internet',
        champs: [
            { key: 'auteur_organisme', label: 'Auteur ou organisme', placeholder: 'Gouvernement du Québec' },
            { key: 'annee', label: 'Année', placeholder: '2023  ou  s.d.' },
            { key: 'titre_page', label: 'Titre de la page', placeholder: 'Histoire de la Révolution tranquille' },
            { key: 'nom_site', label: 'Nom du site', placeholder: 'Québec.ca' },
            { key: 'url', label: 'URL', placeholder: 'https://…' },
        ],
    },
    document_audiovisuel: {
        label: 'Document audiovisuel',
        champs: [
            { key: 'auteur', label: 'Auteur ou réalisateur', placeholder: 'Nom, P. (Réalisateur)' },
            { key: 'annee', label: 'Année', placeholder: '2023' },
            { key: 'titre', label: 'Titre', placeholder: 'La Révolution tranquille' },
            { key: 'type_doc', label: 'Type', placeholder: 'Documentaire, Vidéo, Film…' },
            { key: 'producteur', label: 'Producteur / Diffuseur', placeholder: 'Office national du film' },
            { key: 'url', label: 'URL', placeholder: 'https://…', optional: true },
        ],
    },
    memoire_these: {
        label: 'Mémoire ou thèse',
        champs: [
            { key: 'auteur', label: 'Auteur', placeholder: 'Nom, P.' },
            { key: 'annee', label: 'Année', placeholder: '2023' },
            { key: 'titre', label: 'Titre', placeholder: 'Titre du mémoire ou de la thèse' },
            { key: 'type_doc', label: 'Type', placeholder: 'Mémoire de maîtrise  ou  Thèse de doctorat' },
            { key: 'universite', label: 'Université', placeholder: 'Université du Québec à Montréal' },
            { key: 'url', label: 'URL ou base de données', placeholder: 'https://…', optional: true },
        ],
    },
    ouvrage_reference: {
        label: 'Ouvrage de référence',
        champs: [
            { key: 'auteur_editeur', label: 'Auteur ou directeur', placeholder: 'Nom, P.  ou  Nom, P. (Dir.)' },
            { key: 'annee', label: 'Année', placeholder: '2023' },
            { key: 'titre_entree', label: 'Titre de l\'entrée', placeholder: 'Révolution tranquille', optional: true },
            { key: 'titre_ouvrage', label: 'Titre de l\'ouvrage', placeholder: 'Dictionnaire historique du Québec' },
            { key: 'editeur', label: 'Éditeur', placeholder: 'Fides' },
            { key: 'url', label: 'URL', placeholder: 'https://…', optional: true },
        ],
    },
};

const ORDRE_TYPES: TypeRef[] = [
    'livre',
    'article_periodique',
    'article_journal',
    'site_internet',
    'document_audiovisuel',
    'memoire_these',
    'ouvrage_reference',
];

// ─── État ─────────────────────────────────────────────────────────────────────

const typeRef = ref<TypeRef>('livre');
const champs = reactive<Record<string, string>>({});

const champsActuels = computed(() => TYPES_CONFIG[typeRef.value].champs);

/**
 * Réinitialise le formulaire quand le type change ou le modal se ferme.
 */
function reinitialiserChamps(): void {
    Object.keys(champs).forEach((k) => delete champs[k]);
}

// flush:'sync' pour que reinitialiserChamps s'exécute immédiatement à la mutation de typeRef,
// avant que le watch sur props.open ne poursuive avec Object.assign.
watch(typeRef, reinitialiserChamps, { flush: 'sync' });

/**
 * Pré-remplit le formulaire à l'ouverture en mode édition, ou réinitialise en mode création.
 * Le watch(typeRef, { flush: 'sync' }) vide champs de façon synchrone quand typeRef change,
 * donc le Object.assign qui suit injecte bien les valeurs persistées.
 */
watch(
    () => props.open,
    (ouvert) => {
        if (!ouvert) return;
        if (props.renvoi?.type_reference) {
            typeRef.value = props.renvoi.type_reference as TypeRef;
            // reinitialiserChamps a déjà été appelé de façon synchrone par le watch(typeRef)
            Object.assign(champs, props.renvoi.champs_reference ?? {});
        } else {
            typeRef.value = 'livre';
            reinitialiserChamps();
        }
    },
);

// ─── Formatage APA ────────────────────────────────────────────────────────────

/**
 * Retourne la valeur d'un champ en la nettoyant, ou une chaîne vide.
 */
function get(key: string): string {
    return champs[key]?.trim() ?? '';
}

/**
 * Génère la référence formatée selon les normes APA simplifiées.
 *
 * Les titres de livres, revues, journaux et sites sont en italique (<em>).
 * Les champs optionnels vides sont omis sans laisser de ponctuation orpheline.
 */
const referenceFormatee = computed<string>(() => {
    switch (typeRef.value) {
        case 'livre': {
            const editionPart = get('edition') ? ` (${get('edition')})` : '';
            return `${get('auteurs')}. (${get('annee')}). <em>${get('titre')}</em>${editionPart}. ${get('editeur')}.`;
        }
        case 'article_periodique': {
            const volNum = get('numero') ? `${get('volume')}(${get('numero')})` : get('volume');
            const doiPart = get('doi') ? ` ${get('doi')}` : '';
            return `${get('auteurs')}. (${get('annee')}). ${get('titre_article')}. <em>${get('titre_revue')}</em>, ${volNum}, ${get('pages')}.${doiPart}`;
        }
        case 'article_journal': {
            const urlPart = get('url') ? ` ${get('url')}` : '';
            return `${get('auteurs')}. (${get('date')}). ${get('titre_article')}. <em>${get('nom_journal')}</em>.${urlPart}`;
        }
        case 'site_internet': {
            return `${get('auteur_organisme')}. (${get('annee')}). ${get('titre_page')}. <em>${get('nom_site')}</em>. ${get('url')}`;
        }
        case 'document_audiovisuel': {
            const urlPart = get('url') ? ` ${get('url')}` : '';
            return `${get('auteur')}. (${get('annee')}). <em>${get('titre')}</em> [${get('type_doc')}]. ${get('producteur')}.${urlPart}`;
        }
        case 'memoire_these': {
            const urlPart = get('url') ? ` ${get('url')}` : '';
            return `${get('auteur')}. (${get('annee')}). <em>${get('titre')}</em> [${get('type_doc')}, ${get('universite')}].${urlPart}`;
        }
        case 'ouvrage_reference': {
            const titreEntree = get('titre_entree');
            const urlPart = get('url') ? ` ${get('url')}` : '';
            const corpsPart = titreEntree
                ? `${titreEntree}. Dans ${get('auteur_editeur')}, <em>${get('titre_ouvrage')}</em>`
                : `<em>${get('titre_ouvrage')}</em>`;
            return `${get('auteur_editeur')}. (${get('annee')}). ${corpsPart}. ${get('editeur')}.${urlPart}`;
        }
        default:
            return '';
    }
});

/**
 * Indique si les champs obligatoires du type courant sont tous remplis.
 */
const formulaireValide = computed<boolean>(() =>
    champsActuels.value
        .filter((c) => !c.optional)
        .every((c) => (champs[c.key] ?? '').trim() !== ''),
);

// ─── Actions ──────────────────────────────────────────────────────────────────

/**
 * Émet le contenu APA formaté avec le type et les champs bruts,
 * réinitialise le formulaire et ferme le modal.
 *
 * La réinitialisation se fait ici (après insertion) plutôt qu'à la fermeture,
 * pour que l'étudiant retrouve ses champs s'il ferme et rouvre accidentellement.
 */
function inserer(): void {
    if (!formulaireValide.value) return;
    emit('inserer', referenceFormatee.value, typeRef.value, { ...champs });
    typeRef.value = 'livre';
    reinitialiserChamps();
    emit('update:open', false);
}

function fermer(): void {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogScrollContent class="max-w-xl" @interact-outside.prevent>
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <BookOpen class="h-4 w-4" />
                    {{ renvoi ? 'Modifier la référence' : 'Ajouter une référence' }}
                </DialogTitle>
            </DialogHeader>

            <div class="grid gap-5">
                <!-- Sélection du type -->
                <div class="grid gap-2">
                    <Label>Type de source</Label>
                    <Select v-model="typeRef">
                        <SelectTrigger>
                            <SelectValue placeholder="Choisir un type de source…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="type in ORDRE_TYPES"
                                :key="type"
                                :value="type"
                            >
                                {{ TYPES_CONFIG[type].label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Champs dynamiques -->
                <div class="grid gap-3">
                    <div
                        v-for="champ in champsActuels"
                        :key="champ.key"
                        class="grid gap-1.5"
                    >
                        <Label :for="`ref-${champ.key}`">
                            {{ champ.label }}
                            <span v-if="champ.optional" class="text-muted-foreground font-normal"> (optionnel)</span>
                        </Label>
                        <Input
                            :id="`ref-${champ.key}`"
                            v-model="champs[champ.key]"
                            :placeholder="champ.placeholder"
                        />
                    </div>
                </div>

                <!-- Prévisualisation -->
                <div v-if="formulaireValide" class="rounded-md border bg-muted/40 px-4 py-3">
                    <p class="mb-1 text-xs font-medium text-muted-foreground">Aperçu APA</p>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <p class="text-sm leading-relaxed" v-html="referenceFormatee" />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" type="button" @click="fermer">Annuler</Button>
                <Button :disabled="!formulaireValide" @click="inserer">
                    {{ renvoi ? 'Mettre à jour la référence' : 'Insérer dans le texte' }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
