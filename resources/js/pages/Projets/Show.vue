<script setup lang="ts">
import { Head, Link, usePage, usePoll } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    ClipboardList,
    Cloud,
    Download,
    Eye,
    FileText,
    Loader2,
    Lock,
    MessageSquare,
    Plus,
    Send,
    Settings2,
    Square,
    Star,
    Trash2,
    Users,
    XCircle,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CommentaireEnseignant from '@/components/CommentaireEnseignant.vue';
import Heading from '@/components/Heading.vue';
import NotesGrillePersonnalisee from '@/components/NotesGrillePersonnalisee.vue';
import RichEditor from '@/components/RichEditor.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Auth } from '@/types/auth';

// ─── Types ────────────────────────────────────────────────────────────────────

type Etudiant = {
    id: number;
    prenom: string;
    nom: string;
};

type Enseignant = {
    id: number;
    prenom: string;
    nom: string;
};

type Thematique = {
    id: number;
    nom: string;
};

type Classe = {
    id: number;
    nom_cours: string;
    code: string;
    groupe: string;
};

type Groupe = {
    id: number;
    numero: number;
    classe_id: number;
    membres: Etudiant[];
    thematiques: Thematique[];
};

type Projet = {
    id: number;
    groupe_id: number;
    titre_projet: string | null;
    introduction_amener: string | null;
    introduction_poser: string | null;
    introduction_diviser: string | null;
};

type Developpement = {
    id: number;
    ordre: number;
    titre: string | null;
    contenu: string | null;
};

type ConclusionMembre = {
    etudiant: Etudiant;
    contenu: string | null;
};

type Commentaire = {
    id: number;
    contenu: string;
};

type Annotation = {
    id: number;
    commentaire_id: string;
    contenu: string;
    type: 'commentaire' | 'correction';
    user_id: number;
};

type VoteRemise = {
    user_id: number;
    vote: boolean;
};

type GrilleCriterePersonnalisee = { id: number; label: string; ponderation: number; ordre: number };
type GrilleMalusPersonnalisee = { id: number; label: string; deduction: number; description: string | null; ordre: number };
type GrillePersonnalisee = { id: number; nom: string; criteres: GrilleCriterePersonnalisee[]; malus: GrilleMalusPersonnalisee[] };

type SectionParagraphe = {
    id: number;
    ordre: number;
    titre: string | null;
    contenu: string | null;
};

type SectionConclusionMembre = {
    userId: number;
    contenu: string | null;
};

type Section = {
    id: number;
    label: string;
    description: string | null;
    ordre: number;
    type: 'texte' | 'paragraphes' | 'individuel';
    contenu: string | null;
    paragraphes: SectionParagraphe[] | null;
    conclusionsParMembre: SectionConclusionMembre[] | null;
};

type Props = {
    groupe: Groupe;
    classe: Classe;
    enseignant: Enseignant;
    membres: Etudiant[];
    projet: Projet;
    developpements: Developpement[];
    conclusions: ConclusionMembre[];
    peutEditer: boolean;
    estEnseignant: boolean;
    correctionVisible: boolean;
    verrouille: boolean;
    dateRemise: string | null;
    remisLe: string | null;
    remisesMultiples: boolean;
    retardPermis: boolean;
    peutRemettre: boolean;
    commentaires: Record<string, Commentaire>;
    votes: VoteRemise[];
    annotationsParChamp: Record<string, Annotation[]>;
    /** Sections dynamiques définies par le professeur (vide si aucun type ou type sans sections) */
    sections: Section[];
    // Grille personnalisée (rattachée automatiquement à la classe)
    grillePersonnalisee: GrillePersonnalisee | null;
    /** notesGrilleParEtudiant[userId][critereId] = note */
    notesGrilleParEtudiant: Record<number, Record<number, number>>;
    /** malusParEtudiant[userId][malusId] = applique */
    malusParEtudiant: Record<number, Record<number, boolean>>;
    /** noteFinaleGrilleParEtudiant[userId] = float | null */
    noteFinaleGrilleParEtudiant: Record<number, number | null>;
};

const props = defineProps<Props>();

const page = usePage();
const userId = computed(() => (page.props.auth as Auth).user.id);
const { t } = useI18n();

// ─── Contenu partagé ──────────────────────────────────────────────────────────

const form = reactive({
    titre_projet: props.projet.titre_projet ?? '',
    introduction_amener: props.projet.introduction_amener ?? '',
    introduction_poser: props.projet.introduction_poser ?? '',
    introduction_diviser: props.projet.introduction_diviser ?? '',
});

// ─── Sections dynamiques ───────────────────────────────────────────────────────

const sectionContenus = reactive<Record<number, string>>(
    Object.fromEntries(props.sections.map((s) => [s.id, s.contenu ?? ''])),
);

// Paragraphes pour les sections de type 'paragraphes' (clé : sectionId)
const sectionParagraphesLocaux = reactive<Record<number, SectionParagraphe[]>>(
    Object.fromEntries(
        props.sections
            .filter((s) => s.type === 'paragraphes')
            .map((s) => [s.id, (s.paragraphes ?? []).map((p) => ({ ...p }))]),
    ),
);

// Conclusions par section pour les sections de type 'individuel' (clé : sectionId → userId)
const sectionConclusionsLocales = reactive<Record<number, Record<number, string>>>(
    Object.fromEntries(
        props.sections
            .filter((s) => s.type === 'individuel')
            .map((s) => [
                s.id,
                Object.fromEntries(
                    (s.conclusionsParMembre ?? []).map((c) => [c.userId, c.contenu ?? '']),
                ),
            ]),
    ),
);

// ─── Paragraphes de développement ─────────────────────────────────────────────

const developpements = ref<Developpement[]>(
    props.developpements.map((d) => ({ ...d })),
);

// ─── Conclusions de tous les membres (éditables par n'importe quel membre) ────

const conclusionsLocales = reactive<Record<number, string>>(
    Object.fromEntries(
        props.conclusions.map((c) => [c.etudiant.id, c.contenu ?? '']),
    ),
);

// ─── Auto-save ────────────────────────────────────────────────────────────────

type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';
const saveStatus = ref<SaveStatus>('idle');

let debounceShared: ReturnType<typeof setTimeout> | null = null;
const debounceConclusions = new Map<number, ReturnType<typeof setTimeout>>();
const debounceDev = new Map<number, ReturnType<typeof setTimeout>>();

const baseUrl = computed(
    () =>
        `/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/projets`,
);

function scheduleSharedSave() {
    if (!props.peutEditer) {
        return;
    }

    saveStatus.value = 'saving';

    if (debounceShared) {
        clearTimeout(debounceShared);
    }

    debounceShared = setTimeout(() => saveShared(), 1500);
}

function scheduleConclusionSave(etudiantId: number) {
    if (!props.peutEditer) {
        return;
    }

    saveStatus.value = 'saving';

    const existing = debounceConclusions.get(etudiantId);

    if (existing) {
        clearTimeout(existing);
    }

    debounceConclusions.set(
        etudiantId,
        setTimeout(() => saveConclusion(etudiantId), 1500),
    );
}

async function saveShared() {
    if (!props.peutEditer) {
        return;
    }

    try {
        await axios.put(baseUrl.value, form);
        saveStatus.value = 'saved';
        setTimeout(() => {
            saveStatus.value = 'idle';
        }, 2000);
    } catch {
        saveStatus.value = 'error';
    }
}

async function saveConclusion(etudiantId: number) {
    if (!props.peutEditer) {
        return;
    }

    try {
        await axios.put(`${baseUrl.value}/conclusion`, {
            user_id: etudiantId,
            contenu: conclusionsLocales[etudiantId],
        });
        saveStatus.value = 'saved';
        setTimeout(() => {
            saveStatus.value = 'idle';
        }, 2000);
    } catch {
        saveStatus.value = 'error';
    }
}

const debounceSections = new Map<number, ReturnType<typeof setTimeout>>();

function scheduleSectionSave(sectionId: number) {
    if (!props.peutEditer) {
        return;
    }

    saveStatus.value = 'saving';
    const existing = debounceSections.get(sectionId);

    if (existing) {
        clearTimeout(existing);
    }

    debounceSections.set(sectionId, setTimeout(() => saveSection(sectionId), 1500));
}

async function saveSection(sectionId: number) {
    if (!props.peutEditer) {
        return;
    }

    try {
        await axios.put(`${baseUrl.value}/sections/${sectionId}`, {
            contenu: sectionContenus[sectionId],
        });
        saveStatus.value = 'saved';
        setTimeout(() => {
            saveStatus.value = 'idle';
        }, 2000);
    } catch {
        saveStatus.value = 'error';
    }
}

function scheduleDeveloppementSave(devId: number) {
    if (!props.peutEditer) {
return;
}

    saveStatus.value = 'saving';
    const existing = debounceDev.get(devId);

    if (existing) {
clearTimeout(existing);
}

    debounceDev.set(devId, setTimeout(() => saveDeveloppement(devId), 1500));
}

async function saveDeveloppement(devId: number) {
    if (!props.peutEditer) {
return;
}

    const dev = developpements.value.find((d) => d.id === devId);

    if (!dev) {
return;
}

    try {
        await axios.put(`${baseUrl.value}/developpements/${devId}`, {
            titre: dev.titre,
            contenu: dev.contenu,
        });
        saveStatus.value = 'saved';
        setTimeout(() => {
            saveStatus.value = 'idle';
        }, 2000);
    } catch {
        saveStatus.value = 'error';
    }
}

async function save() {
    if (!props.peutEditer) {
return;
}

    saveStatus.value = 'saving';
    await Promise.all([
        saveShared(),
        saveConclusion(),
        ...developpements.value.map((d) => saveDeveloppement(d.id)),
    ]);
}

watch(form, scheduleSharedSave, { deep: true });

// ─── Paragraphes de développement dynamiques ─────────────────────────────────

const devEnCours = ref(false);

async function ajouterDev() {
    if (devEnCours.value) {
return;
}

    devEnCours.value = true;

    try {
        const response = await axios.post(`${baseUrl.value}/developpements`);
        developpements.value.push(response.data.developpement);
    } finally {
        devEnCours.value = false;
    }
}

async function supprimerDev(devId: number) {
    if (developpements.value.length <= 1) {
return;
}

    if (devEnCours.value) {
return;
}

    devEnCours.value = true;

    try {
        await axios.delete(`${baseUrl.value}/developpements/${devId}`);
        developpements.value = developpements.value
            .filter((d) => d.id !== devId)
            .map((d, i) => ({ ...d, ordre: i + 1 }));
    } finally {
        devEnCours.value = false;
    }
}

// ─── Paragraphes de sections dynamiques (type 'paragraphes') ─────────────────

const debounceSectionParagraphes = new Map<number, ReturnType<typeof setTimeout>>();
const sectionParagrapheEnCours = reactive<Record<number, boolean>>({});

function scheduleSectionParagrapheSave(paragrapheId: number, sectionId: number) {
    if (!props.peutEditer) return;
    saveStatus.value = 'saving';
    const existing = debounceSectionParagraphes.get(paragrapheId);
    if (existing) clearTimeout(existing);
    debounceSectionParagraphes.set(paragrapheId, setTimeout(() => saveSectionParagraphe(paragrapheId, sectionId), 1500));
}

async function saveSectionParagraphe(paragrapheId: number, sectionId: number) {
    if (!props.peutEditer) return;
    const paragraphes = sectionParagraphesLocaux[sectionId];
    if (!paragraphes) return;
    const p = paragraphes.find((p) => p.id === paragrapheId);
    if (!p) return;
    try {
        await axios.patch(`${baseUrl.value}/sections/${sectionId}/paragraphes/${paragrapheId}`, {
            titre: p.titre,
            contenu: p.contenu,
        });
        saveStatus.value = 'saved';
        setTimeout(() => { saveStatus.value = 'idle'; }, 2000);
    } catch {
        saveStatus.value = 'error';
    }
}

async function ajouterSectionParagraphe(sectionId: number) {
    if (sectionParagrapheEnCours[sectionId]) return;
    sectionParagrapheEnCours[sectionId] = true;
    try {
        const response = await axios.post(`${baseUrl.value}/sections/${sectionId}/paragraphes`);
        if (!sectionParagraphesLocaux[sectionId]) sectionParagraphesLocaux[sectionId] = [];
        sectionParagraphesLocaux[sectionId].push(response.data.paragraphe);
    } finally {
        sectionParagrapheEnCours[sectionId] = false;
    }
}

async function supprimerSectionParagraphe(paragrapheId: number, sectionId: number) {
    const paragraphes = sectionParagraphesLocaux[sectionId];
    if (!paragraphes || paragraphes.length <= 1) return;
    if (sectionParagrapheEnCours[sectionId]) return;
    sectionParagrapheEnCours[sectionId] = true;
    try {
        await axios.delete(`${baseUrl.value}/sections/${sectionId}/paragraphes/${paragrapheId}`);
        sectionParagraphesLocaux[sectionId] = sectionParagraphesLocaux[sectionId]
            .filter((p) => p.id !== paragrapheId)
            .map((p, i) => ({ ...p, ordre: i + 1 }));
    } finally {
        sectionParagrapheEnCours[sectionId] = false;
    }
}

// ─── Conclusions de sections dynamiques (type 'individuel') ──────────────────

const debounceSectionConclusions = new Map<string, ReturnType<typeof setTimeout>>();

function scheduleSectionConclusionSave(sectionId: number, userId: number) {
    if (!props.peutEditer) return;
    saveStatus.value = 'saving';
    const key = `${sectionId}_${userId}`;
    const existing = debounceSectionConclusions.get(key);
    if (existing) clearTimeout(existing);
    debounceSectionConclusions.set(key, setTimeout(() => saveSectionConclusion(sectionId, userId), 1500));
}

async function saveSectionConclusion(sectionId: number, userId: number) {
    if (!props.peutEditer) return;
    try {
        await axios.put(`${baseUrl.value}/conclusion`, {
            user_id: userId,
            section_id: sectionId,
            contenu: sectionConclusionsLocales[sectionId]?.[userId] ?? '',
        });
        saveStatus.value = 'saved';
        setTimeout(() => { saveStatus.value = 'idle'; }, 2000);
    } catch {
        saveStatus.value = 'error';
    }
}

// ─── Collapse / expand des sections ──────────────────────────────────────────

const collapsed = reactive<Record<string, boolean>>({
    pageTitre: false,
    tdm: false,
    introduction: false,
});

const collapsedDev = reactive<Record<number, boolean>>({});
const collapsedConclusion = reactive<Record<number, boolean>>({});

function toggleSection(key: string) {
    collapsed[key] = !collapsed[key];
}

function toggleDev(n: number) {
    collapsedDev[n] = !collapsedDev[n];
}

function toggleConclusion(id: number) {
    collapsedConclusion[id] = !collapsedConclusion[id];
}

// ─── Onglet d'introduction actif ──────────────────────────────────────────────

type IntroTab = 'amener' | 'poser' | 'diviser';
const introTab = ref<IntroTab>('amener');

const introTabCritere: Record<IntroTab, string> = {
    amener: 'introduction_amener',
    poser: 'introduction_poser',
    diviser: 'introduction_diviser',
};

// ─── Données pour la page titre ──────────────────────────────────────────────

const dateAujourd = computed(() =>
    new Date().toLocaleDateString('fr-CA', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

const codeComplet = computed(
    () => `${props.classe.code} / Gr. ${props.classe.groupe}`,
);

// ─── Table des matières ───────────────────────────────────────────────────────

const tocEntrees = computed(() => {
    if (props.sections.length > 0) {
        return props.sections.map((s) => ({ label: s.label, numero: s.ordre }));
    }

    return [
        { label: t('projets.show.introduction'), numero: null },
        ...developpements.value.map((dev) => ({
            label: dev.titre || t('projets.show.dev_paragraph', { n: dev.ordre }),
            numero: dev.ordre,
        })),
        ...props.membres.map((m) => ({
            label: t('projets.show.conclusion_member', { prenom: m.prenom, nom: m.nom }),
            numero: null,
        })),
    ];
});

// ─── Commentaires de l'enseignant ─────────────────────────────────────────────

const commentaires = reactive<Record<string, Commentaire | null>>({
    ...Object.fromEntries(
        Object.entries(props.commentaires).map(([k, v]) => [k, v]),
    ),
});

const brouillonsCommentaires = reactive<Record<string, string>>({});

function getBrouillon(champ: string): string {
    if (brouillonsCommentaires[champ] === undefined) {
        brouillonsCommentaires[champ] = commentaires[champ]?.contenu ?? '';
    }

    return brouillonsCommentaires[champ];
}

function setBrouillon(champ: string, val: string) {
    brouillonsCommentaires[champ] = val;
}

const commentairesSaving = reactive<Record<string, boolean>>({});

// ─── Masquer/afficher les commentaires ───────────────────────────────────────

const commentairesReduits = reactive<Record<string, boolean>>({});

const champsVisibles = computed((): string[] => {
    const champs: string[] = ['normes_presentation'];

    if (props.sections.length > 0) {
        champs.push(...props.sections.map((s) => `section_${s.id}`));
    } else {
        champs.push('introduction_amener', 'introduction_poser', 'introduction_diviser');
        champs.push(...developpements.value.map((d) => `developpement_${d.id}`));
        props.membres.forEach((m) => champs.push(`conclusion_${m.id}`));
    }

    return champs.filter((c) => props.estEnseignant || commentaires[c]);
});

const tousCommentairesReduits = computed(
    () =>
        champsVisibles.value.length > 0 &&
        champsVisibles.value.every((c) => commentairesReduits[c]),
);

function toggleCommentaire(champ: string): void {
    commentairesReduits[champ] = !commentairesReduits[champ];
}

function toggleTousCommentaires(): void {
    if (tousCommentairesReduits.value) {
        champsVisibles.value.forEach((c) => {
            commentairesReduits[c] = false;
        });
    } else {
        champsVisibles.value.forEach((c) => {
            commentairesReduits[c] = true;
        });
    }
}

async function sauvegarderCommentaire(champ: string) {
    const contenu = brouillonsCommentaires[champ] ?? '';

    if (!contenu.trim()) {
return;
}

    commentairesSaving[champ] = true;

    try {
        const response = await axios.put(`${baseUrl.value}/commentaires`, {
            champ,
            contenu,
        });
        commentaires[champ] = {
            id: response.data.id,
            contenu: response.data.contenu,
        };
    } finally {
        commentairesSaving[champ] = false;
    }
}

async function supprimerCommentaire(champ: string) {
    const c = commentaires[champ];

    if (!c) {
return;
}

    await axios.delete(`${baseUrl.value}/commentaires/${c.id}`);
    commentaires[champ] = null;
    brouillonsCommentaires[champ] = '';
}

// ─── Annotations inline de l'enseignant ──────────────────────────────────────

const annotations = reactive<Record<string, Annotation[]>>({ ...props.annotationsParChamp });
const annotationDeleteError = ref<string | null>(null);

// ─── Toggles enseignant ────────────────────────────────────────────────────────

const correctionVisible = ref(props.correctionVisible);
const verrouille = ref(props.verrouille);

async function toggleCorrectionVisible(): Promise<void> {
    const response = await axios.patch(`${baseUrl.value}/correction-visible`);
    correctionVisible.value = response.data.correction_visible;
}

async function toggleVerrouille(): Promise<void> {
    const response = await axios.patch(`${baseUrl.value}/verrouille`);
    verrouille.value = response.data.verrouille;
}

// ─── Polling — synchronisation multi-sessions ─────────────────────────────────
//
// Rafraîchit les props "volatiles" toutes les 10 secondes pour couvrir :
//   - Scénario 4 : prof verrouille → éditeur étudiant passe en lecture seule
//   - Scénario 1 : prof annote    → nouvelles bulles visibles dans le panneau
//   - Scénario 6 : prof active les corrections → annotations de type "correction" apparaissent
//
// Le contenu du projet (form.*) n'est intentionnellement PAS inclus pour éviter
// d'écraser les modifications en cours de saisie de l'étudiant.

usePoll(10_000, {
    only: ['verrouille', 'correctionVisible', 'peutEditer', 'peutRemettre', 'annotationsParChamp', 'votes', 'retardPermis', 'remisLe'],
});

// Synchronise les refs locales depuis les props Inertia mises à jour par le polling.
// Ces refs existent parce que le prof peut les modifier directement (optimistic update)
// — le watcher garantit la cohérence si un autre onglet ou l'autre rôle change l'état.

watch(
    () => props.verrouille,
    (newVal) => {
        verrouille.value = newVal;
    },
);

watch(
    () => props.correctionVisible,
    (newVal) => {
        correctionVisible.value = newVal;
    },
);

watch(
    () => props.retardPermis,
    (newVal) => {
        retardPermis.value = newVal;
    },
);

watch(
    () => props.remisLe,
    (newVal) => {
        remisLe.value = newVal;
    },
);

watch(
    () => props.votes,
    (newVotes) => {
        votes.value = [...newVotes];
    },
    { deep: true },
);

// Remplace intégralement les annotations locales par la réponse du serveur.
// Sûr car : les étudiants ne peuvent pas modifier les annotations,
// et le prof reçoit ses propres annotations déjà persistées.
watch(
    () => props.annotationsParChamp,
    (newAnnotations) => {
        Object.keys(annotations).forEach((key) => delete annotations[key]);
        Object.assign(annotations, newAnnotations);
    },
    { deep: true },
);

// ─── Remise de travail ─────────────────────────────────────────────────────────

const remisLe = ref<string | null>(props.remisLe);
const dateRemise = ref<string | null>(props.dateRemise);
const remisesMultiples = ref(props.remisesMultiples);
const retardPermis = ref(props.retardPermis);
const votes = ref<VoteRemise[]>([...props.votes]);
const voteEnCours = ref(false);
const annulationEnCours = ref(false);
const parametresRemiseEnCours = ref(false);

const dateRemiseFormatee = computed(() => {
    if (!dateRemise.value) {
        return null;
    }

    return new Date(dateRemise.value).toLocaleDateString('fr-CA', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

const dateRemiseDepassee = computed(() => {
    if (!dateRemise.value) {
        return false;
    }

    return new Date(dateRemise.value) < new Date();
});

async function sauvegarderParametresRemise(): Promise<void> {
    if (parametresRemiseEnCours.value) {
        return;
    }

    parametresRemiseEnCours.value = true;

    try {
        const response = await axios.patch(`${baseUrl.value}/parametres-remise`, {
            date_remise: dateRemise.value,
            remises_multiples: remisesMultiples.value,
            retard_permis: retardPermis.value,
        });
        dateRemise.value = response.data.date_remise;
        remisesMultiples.value = response.data.remises_multiples;
        retardPermis.value = response.data.retard_permis;
    } finally {
        parametresRemiseEnCours.value = false;
    }
}

/**
 * Enregistre le vote "pour remettre" de l'étudiant connecté.
 * Si tous les membres ont voté, le backend déclenche la remise atomiquement.
 */
async function voterRemise(): Promise<void> {
    if (voteEnCours.value) {
        return;
    }

    voteEnCours.value = true;

    try {
        const response = await axios.post(`${baseUrl.value}/voter-remise`);

        // Mise à jour optimiste du vote local
        const idx = votes.value.findIndex((v) => v.user_id === userId.value);

        if (idx !== -1) {
            votes.value[idx].vote = true;
        } else {
            votes.value.push({ user_id: userId.value, vote: true });
        }

        // Si tous ont voté, le backend a automatiquement rempli remis_le
        if (response.data.remis_le) {
            remisLe.value = response.data.remis_le;
        }
    } finally {
        voteEnCours.value = false;
    }
}

/**
 * Annule la remise du travail (enseignant seulement).
 * Réinitialise remis_le et vide les votes pour un nouveau cycle.
 */
async function annulerRemise(): Promise<void> {
    if (annulationEnCours.value) {
        return;
    }

    annulationEnCours.value = true;

    try {
        await axios.delete(`${baseUrl.value}/annuler-remise`);
        remisLe.value = null;
        votes.value = [];
    } finally {
        annulationEnCours.value = false;
    }
}

async function sauvegarderAnnotation(
    champ: string,
    payload: { commentaire_id: string; contenu: string; html: string; type: string },
): Promise<void> {
    const response = await axios.put(`${baseUrl.value}/annotations`, { champ, ...payload });

    if (!annotations[champ]) {
        annotations[champ] = [];
    }

    // L'endpoint fait un upsert sur commentaire_id — on met à jour localement si déjà présent
    const existingIndex = annotations[champ].findIndex(
        (a) => a.commentaire_id === payload.commentaire_id,
    );

    if (existingIndex !== -1) {
        annotations[champ][existingIndex].contenu = response.data.contenu;
        annotations[champ][existingIndex].type = response.data.type;
    } else {
        annotations[champ].push({
            id: response.data.id,
            commentaire_id: response.data.commentaire_id,
            contenu: response.data.contenu,
            type: response.data.type,
            user_id: response.data.user_id,
        });
    }
}

async function supprimerAnnotation(
    champ: string,
    payload: { correction: Annotation; html: string; htmlOriginal: string },
): Promise<void> {
    annotationDeleteError.value = null;

    try {
        await axios.delete(`${baseUrl.value}/annotations/${payload.correction.id}`, {
            data: { champ, html: payload.html },
        });

        if (annotations[champ]) {
            annotations[champ] = annotations[champ].filter((a) => a.id !== payload.correction.id);
        }

        // Synchronise le modèle local avec le HTML sans marque retourné par deleteAnnotation.
        // Sans cela, le watcher watch(() => props.modelValue) pourrait réinsérer la marque via setContent.
        if (champ in form) {
            (form as Record<string, string>)[champ] = payload.html;
        } else if (champ.startsWith('developpement_')) {
            const devId = parseInt(champ.replace('developpement_', ''), 10);
            const dev = developpements.value.find((d) => d.id === devId);

            if (dev) {
                dev.contenu = payload.html;
            }
        } else if (champ.startsWith('section_paragraphe_')) {
            const paragId = parseInt(champ.replace('section_paragraphe_', ''), 10);
            for (const paragraphes of Object.values(sectionParagraphesLocaux)) {
                const p = (paragraphes as SectionParagraphe[]).find((p) => p.id === paragId);
                if (p) { p.contenu = payload.html; break; }
            }
        } else if (champ.startsWith('section_')) {
            const sectionId = parseInt(champ.replace('section_', ''), 10);
            sectionContenus[sectionId] = payload.html;
        }
    } catch {
        // Rollback : restaure la marque dans l'éditeur et la carte dans le panneau.
        if (champ in form) {
            (form as Record<string, string>)[champ] = payload.htmlOriginal;
        } else if (champ.startsWith('developpement_')) {
            const devId = parseInt(champ.replace('developpement_', ''), 10);
            const dev = developpements.value.find((d) => d.id === devId);

            if (dev) {
                dev.contenu = payload.htmlOriginal;
            }
        } else if (champ.startsWith('section_paragraphe_')) {
            const paragId = parseInt(champ.replace('section_paragraphe_', ''), 10);
            for (const paragraphes of Object.values(sectionParagraphesLocaux)) {
                const p = (paragraphes as SectionParagraphe[]).find((p) => p.id === paragId);
                if (p) { p.contenu = payload.htmlOriginal; break; }
            }
        } else if (champ.startsWith('section_')) {
            const sectionId = parseInt(champ.replace('section_', ''), 10);
            sectionContenus[sectionId] = payload.htmlOriginal;
        }

        if (annotations[champ]) {
            annotations[champ] = [...annotations[champ], payload.correction];
        } else {
            annotations[champ] = [payload.correction];
        }

        annotationDeleteError.value = "Impossible de supprimer l'annotation. Réessayez.";
        setTimeout(() => (annotationDeleteError.value = null), 5000);
    }
}

// ─── Grille de correction personnalisée ───────────────────────────────────────

const grillePersonnalisee = ref<GrillePersonnalisee | null>(props.grillePersonnalisee);
const grilleModalOuverte = ref(false);

// notesGrille[userId][critereId] = note | undefined
const notesGrille = reactive<Record<number, Record<number, number | undefined>>>(
    Object.fromEntries(
        props.membres.map((m) => [
            m.id,
            { ...(props.notesGrilleParEtudiant[m.id] ?? {}) },
        ]),
    ),
);

// malusGrille[userId][malusId] = applique
const malusGrille = reactive<Record<number, Record<number, boolean>>>(
    Object.fromEntries(
        props.membres.map((m) => [
            m.id,
            { ...(props.malusParEtudiant[m.id] ?? {}) },
        ]),
    ),
);

const noteFinaleGrille = reactive<Record<number, number | null>>({
    ...props.noteFinaleGrilleParEtudiant,
});

const notesSavingGrille = reactive<Record<string, boolean>>({});
const malusSaving = reactive<Record<string, boolean>>({});

async function sauvegarderNoteGrille(critereId: number, membreId: number, note: number): Promise<void> {
    const key = `grille_${critereId}_${membreId}`;
    notesSavingGrille[key] = true;

    if (!notesGrille[membreId]) {
        notesGrille[membreId] = {};
    }

    notesGrille[membreId][critereId] = note;

    try {
        const response = await axios.put(`${baseUrl.value}/grille/notes`, {
            critere_id: critereId,
            note,
            user_id: membreId,
        });
        const nouvelles = response.data.noteFinaleGrilleParEtudiant as Record<number, number | null>;
        Object.entries(nouvelles).forEach(([uid, val]) => {
            noteFinaleGrille[Number(uid)] = val;
        });
    } finally {
        notesSavingGrille[key] = false;
    }
}

async function sauvegarderNoteGrillePourTous(critereId: number, note: number): Promise<void> {
    const key = `grille_${critereId}_tous`;
    notesSavingGrille[key] = true;

    try {
        await Promise.all(props.membres.map((m) => sauvegarderNoteGrille(critereId, m.id, note)));
    } finally {
        notesSavingGrille[key] = false;
    }
}

async function toggleMalusGrille(malusId: number, membreId: number, applique: boolean): Promise<void> {
    const key = `malus_${malusId}_${membreId}`;
    malusSaving[key] = true;

    if (!malusGrille[membreId]) {
        malusGrille[membreId] = {};
    }

    malusGrille[membreId][malusId] = applique;

    try {
        const response = await axios.put(`${baseUrl.value}/grille/malus`, {
            malus_id: malusId,
            user_id: membreId,
            applique,
        });
        const nouvelles = response.data.noteFinaleGrilleParEtudiant as Record<number, number | null>;
        Object.entries(nouvelles).forEach(([uid, val]) => {
            noteFinaleGrille[Number(uid)] = val;
        });
    } finally {
        malusSaving[key] = false;
    }
}

// Onglet étudiant actif par section — 'tous' = appliquer à tous les étudiants
const ongletActif = reactive<Record<string, number | 'tous'>>({});

function getOngletActif(section: string, fallback: number): number | 'tous' {
    if (ongletActif[section] === undefined) {
        // L'enseignant voit le premier étudiant ; l'étudiant voit son propre onglet
        ongletActif[section] = props.estEnseignant
            ? (props.membres[0]?.id ?? fallback)
            : userId.value;
    }

    return ongletActif[section];
}

function setOngletActif(section: string, membreId: number | 'tous') {
    ongletActif[section] = membreId;
}
</script>

<template>
    <AppLayout>
        <Head :title="t('projets.show.page_head', { nom: t('classes.groupes.group_number', { n: groupe.numero }) })" />

        <div class="mx-auto flex max-w-6xl flex-col gap-3 p-3">
            <!-- En-tête navigation -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Button variant="ghost" size="sm" as-child>
                    <Link
                        :href="`/classes/${groupe.classe_id}/groupes/${groupe.id}/projets`"
                    >
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ t('projets.show.back') }}
                    </Link>
                </Button>

                <!-- Indicateur de sauvegarde -->
                <div
                    v-if="peutEditer"
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Loader2
                        v-if="saveStatus === 'saving'"
                        class="h-4 w-4 animate-spin"
                    />
                    <CheckCircle2
                        v-else-if="saveStatus === 'saved'"
                        class="h-4 w-4 text-green-500"
                    />
                    <Cloud v-else class="h-4 w-4" />
                    <span v-if="saveStatus === 'saving'">{{ t('projets.show.saving') }}</span>
                    <span
                        v-else-if="saveStatus === 'saved'"
                        class="text-green-600"
                        >{{ t('projets.show.saved') }}</span
                    >
                    <span
                        v-else-if="saveStatus === 'error'"
                        class="text-destructive"
                        >{{ t('projets.show.save_error') }}</span
                    >
                    <span
                        v-if="annotationDeleteError"
                        class="text-destructive"
                    >{{ annotationDeleteError }}</span>
                </div>
            </div>

            <Heading
                :title="t('classes.groupes.group_number', { n: groupe.numero })"
                :description="`${classe.code} — ${classe.nom_cours}`"
            />

            <!-- Bannière document verrouillé (étudiant uniquement) -->
            <div
                v-if="verrouille && !estEnseignant"
                class="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
            >
                <Lock class="h-4 w-4 shrink-0" />
                {{ t('projets.show.locked_message') }}
            </div>

            <!-- Boutons d'export + notes finales par étudiant — sticky pour garder le score visible au scroll -->
            <div class="sticky top-0 z-30 -mx-3 border-b bg-white px-3 py-2 shadow-sm dark:bg-zinc-950">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`${baseUrl}/apercu`">
                            <Eye class="mr-2 h-4 w-4" />
                            Aperçu
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="`${baseUrl}/pdf`" target="_blank">
                            <FileText class="mr-2 h-4 w-4" />
                            {{ t('projets.show.export_pdf') }}
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="`${baseUrl}/word`">
                            <Download class="mr-2 h-4 w-4" />
                            {{ t('projets.show.export_word') }}
                        </a>
                    </Button>
                    <Button v-if="estEnseignant" variant="outline" size="sm" as-child>
                        <a :href="`${baseUrl}/xml-notes`">
                            <Download class="mr-2 h-4 w-4" />
                            Exporter XML
                        </a>
                    </Button>
                    <Button v-if="estEnseignant" variant="ghost" size="sm" as-child>
                        <Link href="/types-projets">
                            <Settings2 class="mr-2 h-4 w-4" />
                            Configurer les sections
                        </Link>
                    </Button>
                    <Button
                        v-if="estEnseignant || (grillePersonnalisee && correctionVisible)"
                        variant="outline"
                        size="sm"
                        @click="grilleModalOuverte = true"
                    >
                        <ClipboardList class="mr-2 h-4 w-4" />
                        Grille de correction
                    </Button>
                    <Button
                        v-if="champsVisibles.length > 0"
                        variant="ghost"
                        size="sm"
                        @click="toggleTousCommentaires"
                    >
                        <MessageSquare class="mr-2 h-4 w-4" />
                        {{
                            tousCommentairesReduits
                                ? t('projets.show.show_comments')
                                : t('projets.show.hide_comments')
                        }}
                    </Button>
                    <!-- Toggles enseignant -->
                    <template v-if="estEnseignant">
                        <Button
                            :variant="correctionVisible ? 'default' : 'outline'"
                            size="sm"
                            @click="toggleCorrectionVisible"
                        >
                            <CheckCircle2 v-if="correctionVisible" class="mr-2 h-4 w-4" />
                            <Send v-else class="mr-2 h-4 w-4" />
                            {{ correctionVisible ? t('projets.show.corrections_published') : t('projets.show.publish_corrections') }}
                        </Button>
                        <Button
                            :variant="verrouille ? 'destructive' : 'outline'"
                            size="sm"
                            @click="toggleVerrouille"
                        >
                            <Lock class="mr-2 h-4 w-4" />
                            {{ verrouille ? t('projets.show.unlock') : t('projets.show.lock') }}
                        </Button>
                    </template>
                </div>

                <!-- Note finale grille personnalisée : enseignant voit tous ; étudiant voit uniquement la sienne -->
                <div class="flex flex-wrap gap-2">
                    <template v-if="estEnseignant && grillePersonnalisee">
                        <template v-for="membre in membres" :key="membre.id">
                            <button
                                v-if="
                                    noteFinaleGrille[membre.id] !== null &&
                                    noteFinaleGrille[membre.id] !== undefined
                                "
                                type="button"
                                class="flex items-center gap-1.5 rounded-lg border px-2 py-1 text-xs font-medium transition-opacity hover:opacity-80"
                                :class="
                                    (noteFinaleGrille[membre.id] ?? 0) >= 60
                                        ? 'border-green-300 bg-green-50 text-green-700'
                                        : 'border-red-300 bg-red-50 text-red-700'
                                "
                                :title="t('projets.show.view_grade_summary')"
                                @click="grilleModalOuverte = true"
                            >
                                <Star class="h-3.5 w-3.5" />
                                {{ membre.prenom }} :
                                {{ noteFinaleGrille[membre.id]?.toFixed(1) }} / 100
                            </button>
                        </template>
                    </template>
                    <button
                        v-else-if="
                            !estEnseignant &&
                            grillePersonnalisee &&
                            noteFinaleGrille[userId] !== null &&
                            noteFinaleGrille[userId] !== undefined
                        "
                        type="button"
                        class="flex items-center gap-2 rounded-lg border px-4 py-2 text-base font-semibold transition-opacity hover:opacity-80"
                        :class="
                            (noteFinaleGrille[userId] ?? 0) >= 60
                                ? 'border-green-300 bg-green-50 text-green-700'
                                : 'border-red-300 bg-red-50 text-red-700'
                        "
                        :title="t('projets.show.view_grade_summary')"
                        @click="grilleModalOuverte = true"
                    >
                        <Star class="h-4 w-4" />
                        {{ t('projets.show.my_grade', { grade: noteFinaleGrille[userId]?.toFixed(1) }) }}
                    </button>
                </div>
            </div>
            </div>

            <!-- ─── Page titre ─────────────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle
                        class="text-sm font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        {{ t('projets.show.page_title_card') }}
                    </CardTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleSection('pageTitre')"
                    >
                        <ChevronUp
                            v-if="!collapsed.pageTitre"
                            class="h-4 w-4"
                        />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsed.pageTitre">
                    <div v-if="peutEditer" class="mb-4">
                        <Label class="mb-1 block text-xs text-muted-foreground"
                            >{{ t('projets.show.project_title_label') }}</Label
                        >
                        <Input
                            v-model="form.titre_projet"
                            placeholder="Ex. : L'agriculture québécoise à travers les époques"
                            class="text-center font-semibold uppercase"
                        />
                    </div>

                    <div
                        class="space-y-1 rounded-lg border bg-white p-3 text-center font-serif text-sm dark:bg-zinc-900"
                    >
                        <p
                            v-for="membre in membres"
                            :key="membre.id"
                            class="text-muted-foreground"
                        >
                            {{ membre.prenom }} {{ membre.nom }}
                        </p>
                        <p class="mt-2 text-muted-foreground">
                            {{ classe.nom_cours }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ codeComplet }}
                        </p>
                        <div class="py-4">
                            <p
                                class="text-base font-semibold tracking-wide uppercase"
                            >
                                {{ form.titre_projet || t('projets.show.project_title_placeholder') }}
                            </p>
                            <p class="mt-1 text-muted-foreground">
                                RECHERCHE DOCUMENTAIRE
                            </p>
                        </div>
                        <p class="text-muted-foreground">
                            Travail présenté à<br />
                            <span class="font-medium"
                                >{{ enseignant.prenom }}
                                {{ enseignant.nom }}</span
                            >
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Département des sciences humaines
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Cégep de Drummondville
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Le {{ dateAujourd }}
                        </p>
                    </div>

                    <!-- Commentaire enseignant -->
                    <CommentaireEnseignant
                        :commentaire="commentaires['normes_presentation']"
                        :brouillon="getBrouillon('normes_presentation')"
                        :est-reduit="
                            !!commentairesReduits['normes_presentation']
                        "
                        :is-saving="!!commentairesSaving['normes_presentation']"
                        :est-enseignant="estEnseignant"
                        :placeholder="t('projets.show.comment_presentation')"
                        class="mt-4"
                        @toggle="toggleCommentaire('normes_presentation')"
                        @save="sauvegarderCommentaire('normes_presentation')"
                        @delete="supprimerCommentaire('normes_presentation')"
                        @update:brouillon="
                            (v) => setBrouillon('normes_presentation', v)
                        "
                    />

                </CardContent>
            </Card>

            <!-- ─── Table des matières ─────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle
                        class="text-sm font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        {{ t('projets.show.toc_card') }}
                    </CardTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleSection('tdm')"
                    >
                        <ChevronUp v-if="!collapsed.tdm" class="h-4 w-4" />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsed.tdm">
                    <div
                        class="space-y-1 rounded-lg border bg-white p-3 font-serif text-sm dark:bg-zinc-900"
                    >
                        <p class="mb-4 text-center font-bold">
                            TABLE DES MATIÈRES
                        </p>
                        <div
                            v-for="(entree, i) in tocEntrees"
                            :key="i"
                            class="flex items-baseline gap-1"
                        >
                            <span
                                v-if="entree.numero"
                                class="w-4 shrink-0 text-xs text-muted-foreground"
                                >{{ entree.numero }}.</span
                            >
                            <span v-else class="w-4 shrink-0" />
                            <span class="flex-1">{{ entree.label }}</span>
                            <span class="shrink-0 text-muted-foreground"
                                >…… p. X</span
                            >
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ─── Sections dynamiques (définies par le professeur) ─────── -->
            <template v-if="props.sections.length > 0">
                <template v-for="section in props.sections" :key="section.id">

                    <!-- ── Type texte : zone de rédaction unique ─────────────── -->
                    <Card v-if="section.type === 'texte'">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <CardTitle class="flex flex-col gap-0.5">
                                <span class="text-base font-semibold">{{ section.label }}</span>
                                <span v-if="section.description" class="text-xs font-normal text-muted-foreground">
                                    {{ section.description }}
                                </span>
                            </CardTitle>
                            <Button variant="ghost" size="icon" @click="toggleSection(`section_${section.id}`)">
                                <ChevronUp v-if="!collapsed[`section_${section.id}`]" class="h-4 w-4" />
                                <ChevronDown v-else class="h-4 w-4" />
                            </Button>
                        </CardHeader>
                        <CardContent v-show="!collapsed[`section_${section.id}`]" class="space-y-4">
                            <RichEditor
                                v-model="sectionContenus[section.id]"
                                :placeholder="`Rédigez la section « ${section.label} »…`"
                                :read-only="!peutEditer"
                                :est-enseignant="estEnseignant"
                                :corrections="annotations[`section_${section.id}`] ?? []"
                                @update:model-value="scheduleSectionSave(section.id)"
                                @save-annotation="(p) => sauvegarderAnnotation(`section_${section.id}`, p)"
                                @delete-annotation="(p) => supprimerAnnotation(`section_${section.id}`, p)"
                            />
                            <CommentaireEnseignant
                                :commentaire="commentaires[`section_${section.id}`]"
                                :brouillon="getBrouillon(`section_${section.id}`)"
                                :est-reduit="!!commentairesReduits[`section_${section.id}`]"
                                :is-saving="!!commentairesSaving[`section_${section.id}`]"
                                :est-enseignant="estEnseignant"
                                class="mt-3"
                                @toggle="toggleCommentaire(`section_${section.id}`)"
                                @save="sauvegarderCommentaire(`section_${section.id}`)"
                                @delete="supprimerCommentaire(`section_${section.id}`)"
                                @update:brouillon="(v) => setBrouillon(`section_${section.id}`, v)"
                            />
                        </CardContent>
                    </Card>

                    <!-- ── Type paragraphes : N sous-paragraphes ajoutables ───── -->
                    <template v-else-if="section.type === 'paragraphes'">
                        <Card
                            v-for="para in sectionParagraphesLocaux[section.id] ?? []"
                            :key="para.id"
                        >
                            <CardHeader class="flex flex-row items-center justify-between">
                                <CardTitle class="flex items-center gap-2">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                        {{ para.ordre }}
                                    </span>
                                    <span class="text-sm font-normal italic text-muted-foreground">
                                        {{ para.titre || `${section.label} — paragraphe ${para.ordre}` }}
                                    </span>
                                </CardTitle>
                                <div class="flex items-center gap-1">
                                    <Button
                                        v-if="peutEditer && (sectionParagraphesLocaux[section.id]?.length ?? 0) > 1"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-destructive"
                                        :disabled="!!sectionParagrapheEnCours[section.id]"
                                        @click="supprimerSectionParagraphe(para.id, section.id)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="toggleDev(para.id)"
                                    >
                                        <ChevronUp v-if="!collapsedDev[para.id]" class="h-4 w-4" />
                                        <ChevronDown v-else class="h-4 w-4" />
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent v-show="!collapsedDev[para.id]" class="space-y-2">
                                <div v-if="peutEditer" class="mb-1">
                                    <Label class="text-xs text-muted-foreground">Titre du paragraphe</Label>
                                    <Input
                                        :model-value="para.titre ?? ''"
                                        :placeholder="`Titre du paragraphe ${para.ordre}`"
                                        class="mt-1"
                                        @update:model-value="(val: string) => { para.titre = val; scheduleSectionParagrapheSave(para.id, section.id); }"
                                    />
                                </div>
                                <RichEditor
                                    :model-value="para.contenu ?? ''"
                                    :placeholder="`Rédigez le contenu du paragraphe ${para.ordre}…`"
                                    :read-only="!peutEditer"
                                    :est-enseignant="estEnseignant"
                                    :corrections="annotations[`section_paragraphe_${para.id}`] ?? []"
                                    @update:model-value="(val: string) => { para.contenu = val; scheduleSectionParagrapheSave(para.id, section.id); }"
                                    @save-annotation="(p) => sauvegarderAnnotation(`section_paragraphe_${para.id}`, p)"
                                    @delete-annotation="(p) => supprimerAnnotation(`section_paragraphe_${para.id}`, p)"
                                />
                                <CommentaireEnseignant
                                    :commentaire="commentaires[`section_paragraphe_${para.id}`]"
                                    :brouillon="getBrouillon(`section_paragraphe_${para.id}`)"
                                    :est-reduit="!!commentairesReduits[`section_paragraphe_${para.id}`]"
                                    :is-saving="!!commentairesSaving[`section_paragraphe_${para.id}`]"
                                    :est-enseignant="estEnseignant"
                                    class="mt-3"
                                    @toggle="toggleCommentaire(`section_paragraphe_${para.id}`)"
                                    @save="sauvegarderCommentaire(`section_paragraphe_${para.id}`)"
                                    @delete="supprimerCommentaire(`section_paragraphe_${para.id}`)"
                                    @update:brouillon="(v) => setBrouillon(`section_paragraphe_${para.id}`, v)"
                                />
                            </CardContent>
                        </Card>

                        <div v-if="peutEditer" class="flex justify-center">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!!sectionParagrapheEnCours[section.id]"
                                @click="ajouterSectionParagraphe(section.id)"
                            >
                                <Loader2 v-if="sectionParagrapheEnCours[section.id]" class="mr-2 h-4 w-4 animate-spin" />
                                <Plus v-else class="mr-2 h-4 w-4" />
                                Ajouter un paragraphe — {{ section.label }}
                            </Button>
                        </div>
                    </template>

                    <!-- ── Type individuel : 1 zone par membre du groupe ─────── -->
                    <template v-else-if="section.type === 'individuel'">
                        <Card v-for="membre in props.membres" :key="membre.id">
                            <CardHeader class="flex flex-row items-center justify-between">
                                <CardTitle class="flex items-center gap-2">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                                        {{ membre.prenom[0] }}{{ membre.nom[0] }}
                                    </span>
                                    {{ section.label }} — {{ membre.prenom }} {{ membre.nom }}
                                </CardTitle>
                                <Button variant="ghost" size="icon" @click="toggleConclusion(section.id * 10000 + membre.id)">
                                    <ChevronUp v-if="!collapsedConclusion[section.id * 10000 + membre.id]" class="h-4 w-4" />
                                    <ChevronDown v-else class="h-4 w-4" />
                                </Button>
                            </CardHeader>
                            <CardContent v-show="!collapsedConclusion[section.id * 10000 + membre.id]">
                                <template v-if="peutEditer">
                                    <p class="mb-2 text-xs text-muted-foreground">
                                        N'importe quel membre peut rédiger la partie d'un autre.
                                    </p>
                                    <RichEditor
                                        :model-value="sectionConclusionsLocales[section.id]?.[membre.id] ?? ''"
                                        placeholder="Rédigez votre partie…"
                                        :est-enseignant="estEnseignant"
                                        @update:model-value="(val: string) => {
                                            if (!sectionConclusionsLocales[section.id]) sectionConclusionsLocales[section.id] = {};
                                            sectionConclusionsLocales[section.id][membre.id] = val;
                                            scheduleSectionConclusionSave(section.id, membre.id);
                                        }"
                                    />
                                </template>
                                <template v-else>
                                    <RichEditor
                                        :model-value="sectionConclusionsLocales[section.id]?.[membre.id] ?? ''"
                                        :read-only="true"
                                        :est-enseignant="estEnseignant"
                                        placeholder="Non rédigé"
                                    />
                                </template>
                                <CommentaireEnseignant
                                    :commentaire="commentaires[`section_individuel_${section.id}_${membre.id}`]"
                                    :brouillon="getBrouillon(`section_individuel_${section.id}_${membre.id}`)"
                                    :est-reduit="!!commentairesReduits[`section_individuel_${section.id}_${membre.id}`]"
                                    :is-saving="!!commentairesSaving[`section_individuel_${section.id}_${membre.id}`]"
                                    :est-enseignant="estEnseignant"
                                    class="mt-3"
                                    @toggle="toggleCommentaire(`section_individuel_${section.id}_${membre.id}`)"
                                    @save="sauvegarderCommentaire(`section_individuel_${section.id}_${membre.id}`)"
                                    @delete="supprimerCommentaire(`section_individuel_${section.id}_${membre.id}`)"
                                    @update:brouillon="(v) => setBrouillon(`section_individuel_${section.id}_${membre.id}`, v)"
                                />
                            </CardContent>
                        </Card>
                    </template>

                </template>
            </template>

            <!-- ─── Introduction (fallback si aucune section définie) ──────── -->
            <template v-else>
            <!-- ─── Introduction ───────────────────────────────────────────── -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-base font-semibold">{{ t('projets.show.introduction') }}</CardTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleSection('introduction')"
                    >
                        <ChevronUp
                            v-if="!collapsed.introduction"
                            class="h-4 w-4"
                        />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsed.introduction" class="space-y-4">
                    <div class="flex border-b">
                        <button
                            v-for="tab in [
                                'amener',
                                'poser',
                                'diviser',
                            ] as const"
                            :key="tab"
                            type="button"
                            class="border-b-2 px-4 py-2 text-sm font-medium capitalize transition-colors"
                            :class="
                                introTab === tab
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            "
                            @click="introTab = tab"
                        >
                            {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
                        </button>
                    </div>

                    <!-- Amener -->
                    <div v-show="introTab === 'amener'">
                        <p class="mb-2 text-xs text-muted-foreground">
                            {{ t('projets.show.amener_hint') }}
                        </p>
                        <RichEditor
                            v-model="form.introduction_amener"
                            placeholder="Amener le sujet…"
                            :read-only="!peutEditer"
                            :est-enseignant="estEnseignant"
                            :corrections="annotations['introduction_amener'] ?? []"
                            @save-annotation="(p) => sauvegarderAnnotation('introduction_amener', p)"
                            @delete-annotation="(p) => supprimerAnnotation('introduction_amener', p)"
                        />
                        <CommentaireEnseignant
                            :commentaire="commentaires['introduction_amener']"
                            :brouillon="getBrouillon('introduction_amener')"
                            :est-reduit="
                                !!commentairesReduits['introduction_amener']
                            "
                            :is-saving="
                                !!commentairesSaving['introduction_amener']
                            "
                            :est-enseignant="estEnseignant"
                            class="mt-3"
                            @toggle="toggleCommentaire('introduction_amener')"
                            @save="
                                sauvegarderCommentaire('introduction_amener')
                            "
                            @delete="
                                supprimerCommentaire('introduction_amener')
                            "
                            @update:brouillon="
                                (v) => setBrouillon('introduction_amener', v)
                            "
                        />
                    </div>

                    <!-- Poser -->
                    <div v-show="introTab === 'poser'">
                        <p class="mb-2 text-xs text-muted-foreground">
                            {{ t('projets.show.poser_hint') }}
                        </p>
                        <RichEditor
                            v-model="form.introduction_poser"
                            placeholder="Poser le sujet…"
                            :read-only="!peutEditer"
                            :est-enseignant="estEnseignant"
                            :corrections="annotations['introduction_poser'] ?? []"
                            @save-annotation="(p) => sauvegarderAnnotation('introduction_poser', p)"
                            @delete-annotation="(p) => supprimerAnnotation('introduction_poser', p)"
                        />
                        <CommentaireEnseignant
                            :commentaire="commentaires['introduction_poser']"
                            :brouillon="getBrouillon('introduction_poser')"
                            :est-reduit="
                                !!commentairesReduits['introduction_poser']
                            "
                            :is-saving="
                                !!commentairesSaving['introduction_poser']
                            "
                            :est-enseignant="estEnseignant"
                            class="mt-3"
                            @toggle="toggleCommentaire('introduction_poser')"
                            @save="sauvegarderCommentaire('introduction_poser')"
                            @delete="supprimerCommentaire('introduction_poser')"
                            @update:brouillon="
                                (v) => setBrouillon('introduction_poser', v)
                            "
                        />
                    </div>

                    <!-- Diviser -->
                    <div v-show="introTab === 'diviser'">
                        <p class="mb-2 text-xs text-muted-foreground">
                            {{ t('projets.show.diviser_hint') }}
                        </p>
                        <RichEditor
                            v-model="form.introduction_diviser"
                            placeholder="Diviser le sujet…"
                            :read-only="!peutEditer"
                            :est-enseignant="estEnseignant"
                            :corrections="annotations['introduction_diviser'] ?? []"
                            @save-annotation="(p) => sauvegarderAnnotation('introduction_diviser', p)"
                            @delete-annotation="(p) => supprimerAnnotation('introduction_diviser', p)"
                        />
                        <CommentaireEnseignant
                            :commentaire="commentaires['introduction_diviser']"
                            :brouillon="getBrouillon('introduction_diviser')"
                            :est-reduit="
                                !!commentairesReduits['introduction_diviser']
                            "
                            :is-saving="
                                !!commentairesSaving['introduction_diviser']
                            "
                            :est-enseignant="estEnseignant"
                            class="mt-3"
                            @toggle="toggleCommentaire('introduction_diviser')"
                            @save="
                                sauvegarderCommentaire('introduction_diviser')
                            "
                            @delete="
                                supprimerCommentaire('introduction_diviser')
                            "
                            @update:brouillon="
                                (v) => setBrouillon('introduction_diviser', v)
                            "
                        />
                    </div>

                </CardContent>
            </Card>
            </template><!-- /v-else intro fallback -->

            <!-- ─── Paragraphes de développement (legacy — masqué si sections dynamiques) ── -->
            <template v-if="sections.length === 0">
            <Card v-for="dev in developpements" :key="dev.id">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <span
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary"
                            >{{ dev.ordre }}</span
                        >
                        <span
                            class="text-sm font-normal text-muted-foreground italic"
                        >
                            {{ dev.titre || t('projets.show.dev_paragraph', { n: dev.ordre }) }}
                        </span>
                    </CardTitle>
                    <div class="flex items-center gap-1">
                        <Button
                            v-if="peutEditer && developpements.length > 1"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-destructive"
                            :title="t('projets.show.delete_paragraph')"
                            :disabled="devEnCours"
                            @click="supprimerDev(dev.id)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="toggleDev(dev.id)"
                        >
                            <ChevronUp
                                v-if="!collapsedDev[dev.id]"
                                class="h-4 w-4"
                            />
                            <ChevronDown v-else class="h-4 w-4" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent v-show="!collapsedDev[dev.id]" class="space-y-2">
                    <div v-if="peutEditer" class="mb-1">
                        <Label class="text-xs text-muted-foreground"
                            >{{ t('projets.show.paragraph_title_label') }}</Label
                        >
                        <Input
                            :model-value="dev.titre ?? ''"
                            :placeholder="`Titre du paragraphe ${dev.ordre}`"
                            class="mt-1"
                            @update:model-value="
                                (val: string) => {
                                    dev.titre = val;
                                    scheduleDeveloppementSave(dev.id);
                                }
                            "
                        />
                    </div>
                    <RichEditor
                        :model-value="dev.contenu ?? ''"
                        :placeholder="`Rédigez le contenu du paragraphe ${dev.ordre}…`"
                        :read-only="!peutEditer"
                        :est-enseignant="estEnseignant"
                        :corrections="annotations[`developpement_${dev.id}`] ?? []"
                        @update:model-value="(val: string) => { dev.contenu = val; scheduleDeveloppementSave(dev.id); }"
                        @save-annotation="(p) => sauvegarderAnnotation(`developpement_${dev.id}`, p)"
                        @delete-annotation="(p) => supprimerAnnotation(`developpement_${dev.id}`, p)"
                    />

                    <!-- Commentaire -->
                    <CommentaireEnseignant
                        :commentaire="commentaires[`developpement_${dev.id}`]"
                        :brouillon="getBrouillon(`developpement_${dev.id}`)"
                        :est-reduit="!!commentairesReduits[`developpement_${dev.id}`]"
                        :is-saving="!!commentairesSaving[`developpement_${dev.id}`]"
                        :est-enseignant="estEnseignant"
                        class="mt-3"
                        @toggle="toggleCommentaire(`developpement_${dev.id}`)"
                        @save="sauvegarderCommentaire(`developpement_${dev.id}`)"
                        @delete="supprimerCommentaire(`developpement_${dev.id}`)"
                        @update:brouillon="
                            (v) => setBrouillon(`developpement_${dev.id}`, v)
                        "
                    />
                </CardContent>
            </Card>

            <!-- Bouton ajouter un paragraphe (pas de limite) -->
            <div v-if="peutEditer" class="flex justify-center">
                <Button variant="outline" size="sm" :disabled="devEnCours" @click="ajouterDev">
                    <Loader2 v-if="devEnCours" class="mr-2 h-4 w-4 animate-spin" />
                    <Plus v-else class="mr-2 h-4 w-4" />
                    {{ t('projets.show.add_paragraph') }}
                </Button>
            </div>
            </template><!-- /legacy développements -->

            <!-- ─── Conclusions individuelles (legacy — masqué si sections dynamiques) ── -->
            <template v-if="sections.length === 0">
            <Card v-for="item in conclusions" :key="item.etudiant.id">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary"
                        >
                            {{ item.etudiant.prenom[0]
                            }}{{ item.etudiant.nom[0] }}
                        </span>
                        {{ t('projets.show.conclusion_member', { prenom: item.etudiant.prenom, nom: item.etudiant.nom }) }}
                    </CardTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleConclusion(item.etudiant.id)"
                    >
                        <ChevronUp
                            v-if="!collapsedConclusion[item.etudiant.id]"
                            class="h-4 w-4"
                        />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent v-show="!collapsedConclusion[item.etudiant.id]">
                    <template v-if="peutEditer">
                        <p class="mb-2 text-xs text-muted-foreground">
                            {{ t('projets.show.conclusion_hint') }}
                        </p>
                        <RichEditor
                            :model-value="conclusionsLocales[item.etudiant.id]"
                            placeholder="Rédigez votre conclusion…"
                            :est-enseignant="estEnseignant"
                            @update:model-value="(val: string) => { conclusionsLocales[item.etudiant.id] = val; scheduleConclusionSave(item.etudiant.id); }"
                        />
                    </template>
                    <template v-else>
                        <RichEditor
                            :model-value="conclusionsLocales[item.etudiant.id] ?? ''"
                            :read-only="true"
                            :est-enseignant="estEnseignant"
                            :placeholder="t('projets.show.section_not_written')"
                        />
                    </template>

                    <!-- Commentaire -->
                    <CommentaireEnseignant
                        :commentaire="
                            commentaires[`conclusion_${item.etudiant.id}`]
                        "
                        :brouillon="
                            getBrouillon(`conclusion_${item.etudiant.id}`)
                        "
                        :est-reduit="
                            !!commentairesReduits[
                                `conclusion_${item.etudiant.id}`
                            ]
                        "
                        :is-saving="
                            !!commentairesSaving[
                                `conclusion_${item.etudiant.id}`
                            ]
                        "
                        :est-enseignant="estEnseignant"
                        class="mt-3"
                        @toggle="
                            toggleCommentaire(`conclusion_${item.etudiant.id}`)
                        "
                        @save="
                            sauvegarderCommentaire(
                                `conclusion_${item.etudiant.id}`,
                            )
                        "
                        @delete="
                            supprimerCommentaire(
                                `conclusion_${item.etudiant.id}`,
                            )
                        "
                        @update:brouillon="
                            (v) =>
                                setBrouillon(
                                    `conclusion_${item.etudiant.id}`,
                                    v,
                                )
                        "
                    />

                </CardContent>
            </Card>
            </template><!-- /legacy conclusions -->

            <!-- ─── Remise de travail ──────────────────────────────────────────── -->

            <!-- Panneau de configuration de la remise (enseignant) -->
            <Card v-if="estEnseignant">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <CalendarDays class="h-4 w-4 text-primary" />
                        {{ t('projets.show.submission_settings') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="flex-1">
                            <Label class="mb-1 block text-xs text-muted-foreground">
                                {{ t('projets.show.submission_deadline') }}
                            </Label>
                            <input
                                v-model="dateRemise"
                                type="datetime-local"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                id="remises-multiples"
                                v-model="remisesMultiples"
                                type="checkbox"
                                class="h-4 w-4 rounded border-input"
                            />
                            <Label for="remises-multiples" class="cursor-pointer text-sm">
                                {{ t('projets.show.allow_multiple') }}
                            </Label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                id="retard-permis"
                                v-model="retardPermis"
                                type="checkbox"
                                class="h-4 w-4 rounded border-input"
                            />
                            <Label for="retard-permis" class="cursor-pointer text-sm">
                                {{ t('projets.show.late_allowed') }}
                            </Label>
                        </div>
                        <Button
                            size="sm"
                            :disabled="parametresRemiseEnCours"
                            @click="sauvegarderParametresRemise"
                        >
                            <Loader2 v-if="parametresRemiseEnCours" class="mr-2 h-4 w-4 animate-spin" />
                            {{ t('common.save') }}
                        </Button>
                    </div>
                    <div v-if="remisLe" class="flex items-center justify-between gap-3">
                        <div class="text-sm text-muted-foreground">
                            <CheckCircle2 class="mr-1 inline-block h-4 w-4 text-green-500" />
                            {{ t('projets.show.submitted_on') }}
                            {{ new Date(remisLe).toLocaleDateString('fr-CA', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                        </div>
                        <Button
                            variant="destructive"
                            size="sm"
                            :disabled="annulationEnCours"
                            @click="annulerRemise"
                        >
                            <Loader2 v-if="annulationEnCours" class="mr-2 h-4 w-4 animate-spin" />
                            <XCircle v-else class="mr-2 h-4 w-4" />
                            {{ t('projets.show.cancel_submission') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Panneau de remise (étudiant) — vote d'équipe -->
            <Card v-if="!estEnseignant && (peutRemettre || remisLe)">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Users class="h-4 w-4 text-primary" />
                        {{ t('projets.show.team_vote') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Confirmation de remise -->
                    <div v-if="remisLe" class="flex items-center gap-2 text-sm text-green-700 dark:text-green-400">
                        <CheckCircle2 class="h-5 w-5 shrink-0" />
                        <span>
                            {{ t('projets.show.submitted_on') }}
                            {{ new Date(remisLe).toLocaleDateString('fr-CA', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                        </span>
                    </div>

                    <!-- Date limite -->
                    <div v-if="dateRemise" class="text-sm" :class="dateRemiseDepassee ? 'font-semibold text-destructive' : 'text-muted-foreground'">
                        <CalendarDays class="mr-1 inline-block h-4 w-4" />
                        {{ t('projets.show.deadline') }} {{ dateRemiseFormatee }}
                        <span v-if="dateRemiseDepassee"> — {{ t('projets.show.deadline_passed') }}</span>
                    </div>

                    <!-- Liste des votes par membre -->
                    <ul class="space-y-1">
                        <li
                            v-for="membre in membres"
                            :key="membre.id"
                            class="flex items-center gap-2 text-sm"
                        >
                            <CheckCircle2
                                v-if="votes.find(v => v.user_id === membre.id)?.vote"
                                class="h-4 w-4 shrink-0 text-green-500"
                            />
                            <Square
                                v-else
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                            />
                            <span>{{ membre.prenom }} {{ membre.nom }}</span>
                            <span class="text-xs text-muted-foreground">
                                — {{ votes.find(v => v.user_id === membre.id)?.vote ? t('projets.show.voted') : t('projets.show.waiting_vote') }}
                            </span>
                        </li>
                    </ul>

                    <!-- Bouton voter (si pas encore voté et peut encore remettre) -->
                    <div v-if="peutRemettre && !votes.find(v => v.user_id === userId)?.vote" class="flex justify-end">
                        <Button
                            :disabled="voteEnCours"
                            @click="voterRemise"
                        >
                            <Loader2 v-if="voteEnCours" class="mr-2 h-4 w-4 animate-spin" />
                            <Send v-else class="mr-2 h-4 w-4" />
                            {{ t('projets.show.vote_to_submit') }}
                        </Button>
                    </div>

                    <!-- Confirmation que le vote a été enregistré (voté mais pas encore tous) -->
                    <div
                        v-else-if="peutRemettre && votes.find(v => v.user_id === userId)?.vote && !remisLe"
                        class="text-sm text-muted-foreground"
                    >
                        <CheckCircle2 class="mr-1 inline-block h-4 w-4 text-green-500" />
                        {{ t('projets.show.my_vote_registered') }}
                    </div>
                </CardContent>
            </Card>

            <!-- Bouton sauvegarder manuel -->
            <div v-if="peutEditer" class="flex justify-end gap-3 pb-4">
                <Button :disabled="saveStatus === 'saving'" @click="save">
                    <Loader2
                        v-if="saveStatus === 'saving'"
                        class="mr-2 h-4 w-4 animate-spin"
                    />
                    <CheckCircle2
                        v-else-if="saveStatus === 'saved'"
                        class="mr-2 h-4 w-4"
                    />
                    {{ t('common.save') }}
                </Button>
            </div>
        </div>

        <!-- ─── Modal grille de correction personnalisée ──────────────────── -->
        <Dialog v-model:open="grilleModalOuverte">
            <DialogScrollContent class="max-w-3xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <ClipboardList class="h-4 w-4" />
                        {{ grillePersonnalisee?.nom ?? 'Grille de correction' }}
                    </DialogTitle>
                </DialogHeader>

                <div v-if="grillePersonnalisee">
                    <!-- Notes finales par étudiant -->
                    <div v-if="estEnseignant" class="mb-3 flex flex-wrap gap-1.5">
                        <template v-for="membre in membres" :key="membre.id">
                            <span
                                v-if="noteFinaleGrille[membre.id] !== null && noteFinaleGrille[membre.id] !== undefined"
                                class="rounded px-2 py-0.5 text-xs font-medium"
                                :class="(noteFinaleGrille[membre.id] ?? 0) >= 60 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                            >
                                {{ membre.prenom }} : {{ noteFinaleGrille[membre.id]?.toFixed(1) }}/100
                            </span>
                        </template>
                    </div>
                    <NotesGrillePersonnalisee
                        :criteres="grillePersonnalisee.criteres"
                        :malus="grillePersonnalisee.malus"
                        :membres="membres"
                        :notes="notesGrille"
                        :malus-appliques="malusGrille"
                        :notes-saving="notesSavingGrille"
                        :malus-saving="malusSaving"
                        :est-enseignant="estEnseignant"
                        :user-id="userId"
                        :onglet-actif="getOngletActif('grille_personnalisee', membres[0]?.id ?? 0)"
                        @set-onglet="setOngletActif('grille_personnalisee', $event)"
                        @save-note="(critereId, membreId, valeur) => sauvegarderNoteGrille(critereId, membreId, valeur)"
                        @save-note-pour-tous="(critereId, valeur) => sauvegarderNoteGrillePourTous(critereId, valeur)"
                        @toggle-malus="(malusId, membreId, applique) => toggleMalusGrille(malusId, membreId, applique)"
                    />
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Aucune grille personnalisée définie pour cette classe.
                </p>
            </DialogScrollContent>
        </Dialog>

    </AppLayout>
</template>
