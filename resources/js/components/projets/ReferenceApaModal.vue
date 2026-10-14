<script setup lang="ts">
import { BookOpen, Copy } from 'lucide-vue-next';
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

type MesReference = {
    id: number;
    titre: string;
    auteurs: { prenom: string; nom: string }[] | null;
    annee: number | null;
    type_source: string | null;
    url: string | null;
    doi: string | null;
    publication: string | null;
};

// ─── Props / Emits ────────────────────────────────────────────────────────────

const props = defineProps<{
    open: boolean;
    /** Renvoi existant à modifier — absent en mode création */
    renvoi?: {
        id: number;
        type_reference: string;
        champs_reference: Record<string, string>;
    } | null;
    /** Références personnelles de l'étudiant — alimentent l'onglet Ma bibliothèque */
    mesReferences?: MesReference[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    inserer: [
        contenu: string,
        typeReference: string,
        champsReference: Record<string, string>,
    ];
}>();

// ─── Configuration des types de références ────────────────────────────────────

const TYPES_CONFIG: Record<TypeRef, { label: string; champs: ChampConfig[] }> =
    {
        livre: {
            label: 'Livre',
            champs: [
                {
                    key: 'auteurs',
                    label: 'Auteur(s)',
                    placeholder: 'Nom, P.  ou  Nom, P., & Nom, P.',
                },
                {
                    key: 'annee',
                    label: 'Année de publication',
                    placeholder: '2023',
                },
                {
                    key: 'titre',
                    label: 'Titre du livre',
                    placeholder: 'Histoire du Québec moderne',
                },
                {
                    key: 'edition',
                    label: 'Édition',
                    placeholder: '2e éd.',
                    optional: true,
                },
                {
                    key: 'editeur',
                    label: 'Éditeur',
                    placeholder: "Les Presses de l'Université Laval",
                },
            ],
        },
        article_periodique: {
            label: 'Article de périodique',
            champs: [
                {
                    key: 'auteurs',
                    label: 'Auteur(s)',
                    placeholder: 'Nom, P.  ou  Nom, P., & Nom, P.',
                },
                { key: 'annee', label: 'Année', placeholder: '2023' },
                {
                    key: 'titre_article',
                    label: "Titre de l'article",
                    placeholder: 'La Révolution tranquille revisitée',
                },
                {
                    key: 'titre_revue',
                    label: 'Titre de la revue',
                    placeholder: "Revue d'histoire de l'Amérique française",
                },
                { key: 'volume', label: 'Volume', placeholder: '76' },
                { key: 'numero', label: 'Numéro', placeholder: '2' },
                { key: 'pages', label: 'Pages', placeholder: '45–78' },
                {
                    key: 'doi',
                    label: 'DOI ou URL',
                    placeholder: 'https://doi.org/…',
                    optional: true,
                },
            ],
        },
        article_journal: {
            label: 'Article de journal',
            champs: [
                { key: 'auteurs', label: 'Auteur(s)', placeholder: 'Nom, P.' },
                { key: 'date', label: 'Date', placeholder: '2023, 15 mars' },
                {
                    key: 'titre_article',
                    label: "Titre de l'article",
                    placeholder: 'La crise du logement à Montréal',
                },
                {
                    key: 'nom_journal',
                    label: 'Nom du journal',
                    placeholder: 'Le Devoir',
                },
                {
                    key: 'url',
                    label: 'URL',
                    placeholder: 'https://…',
                    optional: true,
                },
            ],
        },
        site_internet: {
            label: 'Site Internet',
            champs: [
                {
                    key: 'auteur_organisme',
                    label: 'Auteur ou organisme',
                    placeholder: 'Gouvernement du Québec',
                },
                { key: 'annee', label: 'Année', placeholder: '2023  ou  s.d.' },
                {
                    key: 'titre_page',
                    label: 'Titre de la page',
                    placeholder: 'Histoire de la Révolution tranquille',
                },
                {
                    key: 'nom_site',
                    label: 'Nom du site',
                    placeholder: 'Québec.ca',
                },
                { key: 'url', label: 'URL', placeholder: 'https://…' },
            ],
        },
        document_audiovisuel: {
            label: 'Document audiovisuel',
            champs: [
                {
                    key: 'auteur',
                    label: 'Auteur ou réalisateur',
                    placeholder: 'Nom, P. (Réalisateur)',
                },
                { key: 'annee', label: 'Année', placeholder: '2023' },
                {
                    key: 'titre',
                    label: 'Titre',
                    placeholder: 'La Révolution tranquille',
                },
                {
                    key: 'type_doc',
                    label: 'Type',
                    placeholder: 'Documentaire, Vidéo, Film…',
                },
                {
                    key: 'producteur',
                    label: 'Producteur / Diffuseur',
                    placeholder: 'Office national du film',
                },
                {
                    key: 'url',
                    label: 'URL',
                    placeholder: 'https://…',
                    optional: true,
                },
            ],
        },
        memoire_these: {
            label: 'Mémoire ou thèse',
            champs: [
                { key: 'auteur', label: 'Auteur', placeholder: 'Nom, P.' },
                { key: 'annee', label: 'Année', placeholder: '2023' },
                {
                    key: 'titre',
                    label: 'Titre',
                    placeholder: 'Titre du mémoire ou de la thèse',
                },
                {
                    key: 'type_doc',
                    label: 'Type',
                    placeholder: 'Mémoire de maîtrise  ou  Thèse de doctorat',
                },
                {
                    key: 'universite',
                    label: 'Université',
                    placeholder: 'Université du Québec à Montréal',
                },
                {
                    key: 'url',
                    label: 'URL ou base de données',
                    placeholder: 'https://…',
                    optional: true,
                },
            ],
        },
        ouvrage_reference: {
            label: 'Ouvrage de référence',
            champs: [
                {
                    key: 'auteur_editeur',
                    label: 'Auteur ou directeur',
                    placeholder: 'Nom, P.  ou  Nom, P. (Dir.)',
                },
                { key: 'annee', label: 'Année', placeholder: '2023' },
                {
                    key: 'titre_entree',
                    label: "Titre de l'entrée",
                    placeholder: 'Révolution tranquille',
                    optional: true,
                },
                {
                    key: 'titre_ouvrage',
                    label: "Titre de l'ouvrage",
                    placeholder: 'Dictionnaire historique du Québec',
                },
                { key: 'editeur', label: 'Éditeur', placeholder: 'Fides' },
                {
                    key: 'url',
                    label: 'URL',
                    placeholder: 'https://…',
                    optional: true,
                },
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
const onglet = ref<'nouveau' | 'bibliotheque'>('nouveau');
const filtreRecherche = ref('');
const referenceSelectionnee = ref<MesReference | null>(null);
const copieApa = ref(false);

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
 * Remet aussi l'onglet, le filtre et la sélection à zéro.
 */
watch(
    () => props.open,
    (ouvert) => {
        if (!ouvert) {
            return;
        }

        onglet.value = 'nouveau';
        filtreRecherche.value = '';
        referenceSelectionnee.value = null;

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

// Désélectionner quand le filtre change — la référence cherchée peut ne plus être visible.
watch(filtreRecherche, () => {
    referenceSelectionnee.value = null;
});

// ─── Bibliothèque personnelle ──────────────────────────────────────────────────

/**
 * Vrai si l'étudiant a au moins une référence personnelle et que le modal est en mode création.
 * L'onglet bibliothèque n'a pas de sens en mode édition d'un renvoi existant.
 */
const afficherOngletBibliotheque = computed(
    () => !props.renvoi && (props.mesReferences?.length ?? 0) > 0,
);

const referencesFiltrees = computed(() => {
    if (!props.mesReferences) {
        return [];
    }

    const filtre = filtreRecherche.value.toLowerCase().trim();

    if (!filtre) {
        return props.mesReferences;
    }

    return props.mesReferences.filter((r) =>
        r.titre.toLowerCase().includes(filtre),
    );
});

/**
 * Formate un tableau d'auteurs au format APA : « Nom, P. » ou « Nom, P., & Nom2, Q. ».
 */
function formatAuteursApa(
    auteurs: { prenom: string; nom: string }[] | null,
): string {
    if (!auteurs || auteurs.length === 0) {
        return '';
    }

    const formattes = auteurs.map((a) => {
        const initiale = a.prenom ? `${a.prenom.charAt(0).toUpperCase()}.` : '';

        return initiale ? `${a.nom}, ${initiale}` : a.nom;
    });

    if (formattes.length === 1) {
        return formattes[0];
    }

    const dernier = formattes.pop()!;

    return `${formattes.join(', ')}, & ${dernier}`;
}

/**
 * Résumé court (premier auteur + année) pour l'affichage dans la liste bibliothèque.
 */
function auteursResume(ref: MesReference): string {
    const premierNom = ref.auteurs?.[0]?.nom ?? '';
    const suffix = (ref.auteurs?.length ?? 0) > 1 ? ' et al.' : '';
    const annee = ref.annee ? ` (${ref.annee})` : '';

    return `${premierNom}${suffix}${annee}`;
}

/**
 * Mappe un type_source Zotero vers un TypeRef APA.
 */
function typeZoteroVersApa(typeSource: string | null): TypeRef {
    switch (typeSource) {
        case 'journalArticle':
            return 'article_periodique';
        case 'book':
            return 'livre';
        case 'webpage':
            return 'site_internet';
        case 'thesis':
            return 'memoire_these';
        case 'videoRecording':
        case 'film':
            return 'document_audiovisuel';
        case 'newspaperArticle':
            return 'article_journal';
        default:
            return 'livre';
    }
}

/**
 * Génère un aperçu APA en meilleur effort à partir des données Zotero disponibles.
 *
 * Certains champs (volume, numéro, pages) ne sont pas stockés en base et seront absents —
 * l'étudiant devra les compléter via "Compléter et insérer" si nécessaire.
 */
function apercuApaDepuisZotero(ref: MesReference): string {
    const auteursStr = formatAuteursApa(ref.auteurs);
    const annee = ref.annee ? String(ref.annee) : 's.d.';
    const type = typeZoteroVersApa(ref.type_source);

    switch (type) {
        case 'article_periodique': {
            const revue = ref.publication
                ? ` <em>${ref.publication}</em>.`
                : '';
            const lien = ref.doi ? ` ${ref.doi}` : ref.url ? ` ${ref.url}` : '';

            return `${auteursStr}. (${annee}). ${ref.titre}.${revue}${lien}`;
        }
        case 'livre': {
            const editeur = ref.publication ? ` ${ref.publication}.` : '';

            return `${auteursStr}. (${annee}). <em>${ref.titre}</em>.${editeur}`;
        }
        case 'site_internet': {
            const site = ref.publication ? ` <em>${ref.publication}</em>.` : '';
            const url = ref.url ? ` ${ref.url}` : '';

            return `${auteursStr}. (${annee}). ${ref.titre}.${site}${url}`;
        }
        case 'memoire_these': {
            const url = ref.url ? ` ${ref.url}` : '';

            return `${auteursStr}. (${annee}). <em>${ref.titre}</em>.${url}`;
        }
        case 'document_audiovisuel': {
            const url = ref.url ? ` ${ref.url}` : '';

            return `${auteursStr}. (${annee}). <em>${ref.titre}</em>.${url}`;
        }
        case 'article_journal': {
            const journal = ref.publication
                ? ` <em>${ref.publication}</em>.`
                : '';
            const url = ref.url ? ` ${ref.url}` : '';

            return `${auteursStr}. ${ref.titre}.${journal}${url}`;
        }
        default:
            return `${auteursStr}. (${annee}). <em>${ref.titre}</em>.`;
    }
}

const apercuApaZotero = computed(() =>
    referenceSelectionnee.value
        ? apercuApaDepuisZotero(referenceSelectionnee.value)
        : '',
);

/**
 * Copie l'aperçu APA dans le presse-papiers (balises HTML retirées pour texte brut).
 */
async function copierApa(): Promise<void> {
    const texte = apercuApaZotero.value.replace(/<[^>]+>/g, '');

    try {
        await navigator.clipboard.writeText(texte);
    } catch {
        // Clipboard API indisponible (contexte non sécurisé) — échec silencieux
    }

    copieApa.value = true;
    setTimeout(() => (copieApa.value = false), 2000);
}

/**
 * Pré-remplit le formulaire APA depuis une référence de la bibliothèque personnelle,
 * puis bascule sur l'onglet de saisie pour que l'étudiant complète les champs manquants.
 *
 * Le watch(typeRef, { flush: 'sync' }) vide champs de façon synchrone quand typeRef change,
 * donc les affectations qui suivent injectent bien les valeurs issues de Zotero.
 */
function preRemplirDepuisZotero(ref: MesReference): void {
    const type = typeZoteroVersApa(ref.type_source);
    typeRef.value = type;

    const auteursStr = formatAuteursApa(ref.auteurs);
    const anneeStr = ref.annee ? String(ref.annee) : '';

    switch (type) {
        case 'article_periodique':
            if (auteursStr) {
                champs.auteurs = auteursStr;
            }

            if (anneeStr) {
                champs.annee = anneeStr;
            }

            if (ref.titre) {
                champs.titre_article = ref.titre;
            }

            if (ref.publication) {
                champs.titre_revue = ref.publication;
            }

            if (ref.doi) {
                champs.doi = ref.doi;
            } else if (ref.url) {
                champs.doi = ref.url;
            }

            break;
        case 'livre':
            if (auteursStr) {
                champs.auteurs = auteursStr;
            }

            if (anneeStr) {
                champs.annee = anneeStr;
            }

            if (ref.titre) {
                champs.titre = ref.titre;
            }

            if (ref.publication) {
                champs.editeur = ref.publication;
            }

            break;
        case 'site_internet':
            if (auteursStr) {
                champs.auteur_organisme = auteursStr;
            }

            if (anneeStr) {
                champs.annee = anneeStr;
            }

            if (ref.titre) {
                champs.titre_page = ref.titre;
            }

            if (ref.publication) {
                champs.nom_site = ref.publication;
            }

            if (ref.url) {
                champs.url = ref.url;
            }

            break;
        case 'memoire_these':
            if (auteursStr) {
                champs.auteur = auteursStr;
            }

            if (anneeStr) {
                champs.annee = anneeStr;
            }

            if (ref.titre) {
                champs.titre = ref.titre;
            }

            if (ref.url) {
                champs.url = ref.url;
            }

            break;
        case 'document_audiovisuel':
            if (auteursStr) {
                champs.auteur = auteursStr;
            }

            if (anneeStr) {
                champs.annee = anneeStr;
            }

            if (ref.titre) {
                champs.titre = ref.titre;
            }

            if (ref.url) {
                champs.url = ref.url;
            }

            break;
        case 'article_journal':
            if (auteursStr) {
                champs.auteurs = auteursStr;
            }

            if (ref.titre) {
                champs.titre_article = ref.titre;
            }

            if (ref.publication) {
                champs.nom_journal = ref.publication;
            }

            if (ref.url) {
                champs.url = ref.url;
            }

            break;
    }

    onglet.value = 'nouveau';
}

// ─── Formatage APA (onglet Nouveau) ───────────────────────────────────────────

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
            const volNum = get('numero')
                ? `${get('volume')}(${get('numero')})`
                : get('volume');
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
 */
function inserer(): void {
    if (!formulaireValide.value) {
        return;
    }

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
                    {{
                        renvoi
                            ? 'Modifier la référence'
                            : 'Ajouter une référence'
                    }}
                </DialogTitle>
            </DialogHeader>

            <!-- Onglets Nouveau / Ma bibliothèque -->
            <div
                v-if="afficherOngletBibliotheque"
                class="flex overflow-hidden rounded-md border text-sm"
            >
                <button
                    type="button"
                    class="flex-1 py-1.5 text-center transition-colors"
                    :class="
                        onglet === 'nouveau'
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-muted'
                    "
                    @click="onglet = 'nouveau'"
                >
                    Nouveau
                </button>
                <button
                    type="button"
                    class="flex-1 border-l py-1.5 text-center transition-colors"
                    :class="
                        onglet === 'bibliotheque'
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-muted'
                    "
                    @click="onglet = 'bibliotheque'"
                >
                    Ma bibliothèque
                </button>
            </div>

            <!-- ─── Onglet Ma bibliothèque ────────────────────────────────────── -->
            <template v-if="onglet === 'bibliotheque'">
                <Input
                    v-model="filtreRecherche"
                    placeholder="Filtrer par titre…"
                />

                <!-- Liste des références -->
                <div
                    class="max-h-52 divide-y overflow-y-auto rounded-md border"
                >
                    <p
                        v-if="referencesFiltrees.length === 0"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Aucune référence trouvée.
                    </p>
                    <button
                        v-for="ref in referencesFiltrees"
                        :key="ref.id"
                        type="button"
                        class="w-full px-3 py-2.5 text-left transition-colors hover:bg-muted/50"
                        :class="{
                            'bg-muted': referenceSelectionnee?.id === ref.id,
                        }"
                        @click="referenceSelectionnee = ref"
                    >
                        <p class="truncate text-sm font-medium">
                            {{ ref.titre }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ auteursResume(ref) }}
                        </p>
                    </button>
                </div>

                <!-- Aperçu APA de la référence sélectionnée -->
                <div
                    v-if="referenceSelectionnee"
                    class="rounded-md border bg-muted/40 px-4 py-3"
                >
                    <p class="mb-2 text-xs font-medium text-muted-foreground">
                        Aperçu APA
                    </p>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <p
                        class="text-sm leading-relaxed"
                        v-html="apercuApaZotero"
                    />
                    <p class="mt-2 text-xs text-muted-foreground/70">
                        Certains champs (volume, pages…) peuvent manquer selon
                        les données Zotero.
                    </p>
                </div>
            </template>

            <!-- ─── Onglet Nouveau (formulaire APA) ──────────────────────────── -->
            <div v-else class="grid gap-5">
                <!-- Sélection du type -->
                <div class="grid gap-2">
                    <Label>Type de source</Label>
                    <Select v-model="typeRef">
                        <SelectTrigger>
                            <SelectValue
                                placeholder="Choisir un type de source…"
                            />
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
                            <span
                                v-if="champ.optional"
                                class="font-normal text-muted-foreground"
                            >
                                (optionnel)</span
                            >
                        </Label>
                        <Input
                            :id="`ref-${champ.key}`"
                            v-model="champs[champ.key]"
                            :placeholder="champ.placeholder"
                        />
                    </div>
                </div>

                <!-- Prévisualisation -->
                <div
                    v-if="formulaireValide"
                    class="rounded-md border bg-muted/40 px-4 py-3"
                >
                    <p class="mb-1 text-xs font-medium text-muted-foreground">
                        Aperçu APA
                    </p>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <p
                        class="text-sm leading-relaxed"
                        v-html="referenceFormatee"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" type="button" @click="fermer"
                    >Annuler</Button
                >

                <!-- Boutons de l'onglet Ma bibliothèque -->
                <template
                    v-if="onglet === 'bibliotheque' && referenceSelectionnee"
                >
                    <Button variant="outline" type="button" @click="copierApa">
                        <Copy class="mr-1.5 h-4 w-4" />
                        {{ copieApa ? 'Copié !' : 'Copier' }}
                    </Button>
                    <Button
                        type="button"
                        @click="preRemplirDepuisZotero(referenceSelectionnee)"
                    >
                        Compléter et insérer
                    </Button>
                </template>

                <!-- Bouton de l'onglet Nouveau -->
                <Button
                    v-else-if="onglet === 'nouveau'"
                    :disabled="!formulaireValide"
                    @click="inserer"
                >
                    {{
                        renvoi
                            ? 'Mettre à jour la référence'
                            : 'Insérer dans le texte'
                    }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
