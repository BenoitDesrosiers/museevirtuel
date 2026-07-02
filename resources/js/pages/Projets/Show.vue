<script setup lang="ts">
import { Head, Link, usePage, usePoll } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    BookmarkPlus,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Download,
    Eye,
    FileBarChart,
    FileText,
    Loader2,
    Lock,
    Maximize2,
    MessageSquare,
    Pencil,
    Plus,
    Send,
    Settings2,
    SpellCheck,
    Square,
    Trash2,
    Users,
    XCircle,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onMounted,
    provide,
    reactive,
    ref,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import AntidoteGlobalModal from '@/components/AntidoteGlobalModal.vue';
import type { GlobalSection } from '@/components/AntidoteGlobalModal.vue';
import CommentaireEnseignant from '@/components/CommentaireEnseignant.vue';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import ConsentementVideo from '@/components/ConsentementVideo.vue';
import CritereCorrection from '@/components/CritereCorrection.vue';
import type {
    Critere as TypeProjetCritere,
    CorrectionLocale,
} from '@/components/CritereCorrection.vue';
import CritereEtudiant from '@/components/CritereEtudiant.vue';
import Heading from '@/components/Heading.vue';
import ReferenceApaModal from '@/components/ReferenceApaModal.vue';
import RichEditor from '@/components/RichEditor.vue';
import SectionAudio from '@/components/SectionAudio.vue';
import SectionChoixQuestions from '@/components/SectionChoixQuestions.vue';
import SectionEntrevueCC from '@/components/SectionEntrevueCC.vue';
import SectionSchemaVisuel from '@/components/SectionSchemaVisuel.vue';
import SectionTache from '@/components/SectionTache.vue';
import SectionVideo from '@/components/SectionVideo.vue';
import BoutonTooltip from '@/components/ui/BoutonTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit as editTypeProjet } from '@/routes/types-projets';
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
    code: string;
    cours_id: number;
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
    annotation_type: 'commentaire' | 'correction';
    points_malus: number | null;
    /** null = tous les étudiants ; un id = étudiant spécifique */
    cible_user_id: number | null;
    user_id: number;
};

type VoteRemise = {
    user_id: number;
    vote: boolean;
};

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

type SectionMedia = {
    id: number;
    source_type: 'upload' | 'url';
    url: string | null;
    nom_original: string | null;
    url_publique: string | null;
};

type EntrevueLigne = {
    id: number;
    ordre: number;
    dimension: string | null;
    indicateur: string | null;
    questions: string[];
};

type EntrevueConcept = {
    id: number;
    label: string;
    ordre: number;
    lignes: EntrevueLigne[];
};

type TypeProjetInfo = {
    id: number;
    nom: string;
};

type RenvoiCommentaire = {
    id: number;
    contenu: string;
    user_id: number;
};

type Renvoi = {
    id: number;
    numero: number;
    contenu: string | null;
    type_reference: string | null;
    champs_reference: Record<string, string> | null;
    commentaires: RenvoiCommentaire[];
};

type SectionTacheItem = {
    id: number;
    titre: string;
    description: string | null;
    ordre: number;
    assigne_a: { id: number; prenom: string; nom: string } | null;
    completed_at: string | null;
};

type CarteSchema = {
    id: string;
    texte: string;
    image: string | null;
};

type ContenuSchema = {
    image_centrale: string | null;
    zones: {
        causes: CarteSchema[];
        activites: CarteSchema[];
        consequences: CarteSchema[];
    };
};

type Section = {
    id: number;
    label: string;
    description: string | null;
    ordre: number;
    type:
        | 'texte'
        | 'paragraphes'
        | 'individuel'
        | 'entrevue'
        | 'video'
        | 'audio'
        | 'choix_questions'
        | 'tache'
        | 'schema_visuel';
    contenu: string | null;
    paragraphes: SectionParagraphe[] | null;
    conclusionsParMembre: SectionConclusionMembre[] | null;
    concepts: EntrevueConcept[] | null;
    medias: SectionMedia[] | null;
    questions: { id: number; contenu: string; ordre: number }[] | null;
    questionsChoisies: number[] | null;
    taches: SectionTacheItem[] | null;
    schemaVisuel: ContenuSchema | null;
    /** Critères de correction de cette section (triés par ordre). Absent sur certains code-paths. */
    criteres?: TypeProjetCritere[];
};

type ConsentementExistant = {
    accepte: boolean;
    signed_at: string | null;
} | null;

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

type Props = {
    groupe: Groupe;
    classe: Classe;
    cours: {
        id: number;
        nom_cours: string;
        code: string;
        groupe: string;
        type_cours: string;
    };
    enseignant: Enseignant;
    membres: Etudiant[];
    projet: Projet;
    developpements: Developpement[];
    conclusions: ConclusionMembre[];
    peutEditer: boolean;
    estEnseignant: boolean;
    correctionVisible: boolean;
    verrouille: boolean;
    modeEditionEnseignant: boolean;
    dateRemise: string | null;
    remisLe: string | null;
    remisesMultiples: boolean;
    retardPermis: boolean;
    peutRemettre: boolean;
    commentaires: Record<string, Commentaire>;
    votes: VoteRemise[];
    annotationsParChamp: Record<string, Annotation[]>;
    /** Type de projet affiché (transmis depuis le controller) */
    typeProjet: TypeProjetInfo;
    /** Sections dynamiques définies par le professeur (vide si aucun type ou type sans sections) */
    sections: Section[];
    /** Notes de renvoi (endnotes) triées par numéro */
    renvois: Renvoi[];
    /** Consentement vidéo de l'utilisateur connecté pour ce projet (null si non renseigné) */
    consentement: ConsentementExistant;
    /** true = page titre auto-générée à l'export, false = étudiant la rédige manuellement */
    genererPageTitre: boolean;
    /** true = table des matières auto-générée à l'export, false = étudiant la rédige manuellement */
    genererTableMatieres: boolean;
    /** true = le modal d'aide APA s'ouvre lors de l'insertion d'un renvoi */
    aideReference: boolean;
    /** Contenu manuel de la page titre (utilisé quand genererPageTitre est false) */
    pageTitreContenu: string | null;
    /** Contenu manuel de la table des matières (utilisé quand genererTableMatieres est false) */
    tableMatieresContenu: string | null;
    /** Références personnelles de l'étudiant (vide pour l'enseignant) */
    mesReferences: MesReference[];
    /** Critères globaux du type de projet (sans section). */
    criteresGlobaux: TypeProjetCritere[];
    /** Corrections indexées par critere_id. */
    correctionsParCritere: Record<number, CorrectionLocale[]>;
    /** IDs des critères cochés personnellement par l'étudiant courant. */
    cochesUtilisateur: number[];
};

const props = defineProps<Props>();

const page = usePage();
const userId = computed(() => (page.props.auth as Auth).user.id);
const { t } = useI18n();

/** Vrai si au moins une section de type vidéo ou audio est présente. */
const hasVideoOrAudioSection = computed(() =>
    props.sections.some((s) => s.type === 'video' || s.type === 'audio'),
);

// ─── Contenu partagé ──────────────────────────────────────────────────────────

const form = reactive({
    titre_projet: props.projet.titre_projet ?? '',
    introduction_amener: props.projet.introduction_amener ?? '',
    introduction_poser: props.projet.introduction_poser ?? '',
    introduction_diviser: props.projet.introduction_diviser ?? '',
    page_titre_contenu: props.pageTitreContenu ?? '',
    table_matieres_contenu: props.tableMatieresContenu ?? '',
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

// Concepts d'entrevue pour les sections de type 'entrevue' (clé : sectionId)
const sectionConceptsLocaux = reactive<Record<number, EntrevueConcept[]>>(
    Object.fromEntries(
        props.sections
            .filter((s) => s.type === 'entrevue')
            .map((s) => [
                s.id,
                (s.concepts ?? []).map((c) => ({
                    ...c,
                    lignes: c.lignes.map((l) => ({
                        ...l,
                        questions: [...(l.questions ?? [])],
                    })),
                })),
            ]),
    ),
);

// Conclusions par section pour les sections de type 'individuel' (clé : sectionId → userId)
const sectionConclusionsLocales = reactive<
    Record<number, Record<number, string>>
>(
    Object.fromEntries(
        props.sections
            .filter((s) => s.type === 'individuel')
            .map((s) => [
                s.id,
                Object.fromEntries(
                    (s.conclusionsParMembre ?? []).map((c) => [
                        c.userId,
                        c.contenu ?? '',
                    ]),
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

// ─── Critères de correction ───────────────────────────────────────────────────

/**
 * Copie locale des corrections par critère — mise à jour optimiste après chaque
 * action de l'enseignant (CritereCorrection émet @updated).
 */
const correctionsLocales = reactive<Record<number, CorrectionLocale[]>>({
    ...props.correctionsParCritere,
});

/** IDs des critères cochés personnellement par l'étudiant courant. */
const cochesLocales = ref<Set<number>>(new Set(props.cochesUtilisateur));

/** Vrai si l'utilisateur courant est un membre du groupe (peut cocher des critères). */
const estMembre = computed(
    () =>
        !props.estEnseignant &&
        props.membres.some((m) => m.id === userId.value),
);

/** Arguments de route communs pour CritereCorrection et CritereEtudiant. */
const routeArgsCritere = computed(() => ({
    cours: props.classe.cours_id,
    classe: props.classe.id,
    groupe: props.groupe.id,
    typeProjet: props.typeProjet.id,
}));

/**
 * Met à jour la liste locale des corrections pour un critère donné.
 * Appelé via @updated depuis CritereCorrection.
 */
function onCorrectionsUpdated(
    critereId: number,
    nouvelles: CorrectionLocale[],
) {
    correctionsLocales[critereId] = nouvelles;
}

/**
 * Met à jour la coche locale d'un critère pour l'étudiant courant.
 * Appelé via @updated-coche depuis CritereEtudiant.
 */
function onCocheUpdated(critereId: number, cochee: boolean) {
    if (cochee) {
        cochesLocales.value.add(critereId);
    } else {
        cochesLocales.value.delete(critereId);
    }
}

/**
 * Résout la correction effective pour l'étudiant courant :
 * override individuel (user_id = userId) > correction de groupe (user_id = null).
 */
function correctionEffective(critereId: number): CorrectionLocale | null {
    const corrs = correctionsLocales[critereId] ?? [];
    const individuelle = corrs.find((c) => c.user_id === userId.value);

    if (individuelle) {
        return individuelle;
    }

    return corrs.find((c) => c.user_id === null) ?? null;
}

// ─── Calcul des notes en temps réel ───────────────────────────────────────────

/**
 * Agrège tous les critères positifs et négatifs du type de projet
 * (globaux + par section) en une liste plate.
 */
const tousLesCriteres = computed<TypeProjetCritere[]>(() => {
    const result: TypeProjetCritere[] = [...props.criteresGlobaux];

    for (const section of props.sections) {
        if (section.criteres?.length) {
            result.push(...section.criteres);
        }
    }

    return result;
});

/** Points maximum possible (somme des critères positifs). */
const maxPoints = computed<number>(() =>
    tousLesCriteres.value
        .filter((c) => c.type === 'positif')
        .reduce((sum, c) => sum + Number(c.pointage), 0),
);

/**
 * Calcule les points obtenus par chaque membre en temps réel.
 * Priorité : correction individuelle (user_id = membreId) > groupe (user_id = null).
 * Réactif : se recalcule à chaque modification de correctionsLocales.
 */
const notesParMembre = computed<Record<number, number>>(() => {
    const toutesAnnotations = Object.values(annotations).flat();

    const result: Record<number, number> = {};

    for (const membre of props.membres) {
        let obtenu = 0;

        // Critères de correction
        for (const critere of tousLesCriteres.value) {
            const corrections = correctionsLocales[critere.id] ?? [];
            const corr =
                corrections.find((c) => c.user_id === membre.id) ??
                corrections.find((c) => c.user_id === null) ??
                null;
            const pts = Number(corr?.points ?? 0);

            if (critere.type === 'positif') {
                obtenu += pts;
            } else {
                obtenu -= pts;
            }
        }

        // Malus annotations : s'applique si cible_user_id = null (tous) OU = cet étudiant
        const malusMembre = toutesAnnotations.reduce((sum, a) => {
            if (!a.points_malus) {
                return sum;
            }

            if (a.cible_user_id === null || a.cible_user_id === membre.id) {
                return sum + Number(a.points_malus);
            }

            return sum;
        }, 0);

        result[membre.id] = Math.round((obtenu - malusMembre) * 100) / 100;
    }

    return result;
});

// ─── Auto-save ────────────────────────────────────────────────────────────────

type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';
const saveStatus = ref<SaveStatus>('idle');

let debounceShared: ReturnType<typeof setTimeout> | null = null;
const debounceConclusions = new Map<number, ReturnType<typeof setTimeout>>();
const debounceDev = new Map<number, ReturnType<typeof setTimeout>>();

const baseUrl = computed(
    () =>
        `/cours/${props.classe.cours_id}/classes/${props.groupe.classe_id}/groupes/${props.groupe.id}/projets/${props.typeProjet.id}`,
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

    debounceSections.set(
        sectionId,
        setTimeout(() => saveSection(sectionId), 1500),
    );
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

    debounceDev.set(
        devId,
        setTimeout(() => saveDeveloppement(devId), 1500),
    );
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

// ─── Modal grille de correction ────────────────────────────────────────────────

const showGrilleModal = ref(false);

// ─── Correction globale Antidote ──────────────────────────────────────────────

const showAntidoteGlobal = ref(false);

/**
 * Annule tous les timers de sauvegarde en attente pour éviter qu'une ancienne
 * version du contenu écrase les corrections appliquées par Antidote.
 */
function clearAllDebounces() {
    if (debounceShared) {
        clearTimeout(debounceShared);
    }

    debounceConclusions.forEach((t) => clearTimeout(t));
    debounceSections.forEach((t) => clearTimeout(t));
    debounceDev.forEach((t) => clearTimeout(t));
    debounceSectionParagraphes.forEach((t) => clearTimeout(t));
    debounceSectionConclusions.forEach((t) => clearTimeout(t));
}

/**
 * Annule tous les timers en attente et exécute immédiatement toutes les sauvegardes.
 *
 * Utilisé après une opération critique sur les renvois (insertion, suppression) pour
 * garantir que le HTML des sections — incluant les nœuds renvoiMark mis à jour —
 * est persisté en base avant une éventuelle navigation. Sans ce flush, un départ
 * rapide de la page (< 1,5 s) laisserait la DB sans le <sup> inséré, désynchronisant
 * la liste de références et les exposants au prochain chargement.
 */
async function flushAllPendingSaves(): Promise<void> {
    if (!props.peutEditer) {
        return;
    }

    const saves: Promise<void>[] = [];

    // Champs partagés (introduction, page titre, table des matières)
    if (debounceShared) {
        clearTimeout(debounceShared);
        debounceShared = null;
        saves.push(saveShared());
    }

    // Sections de type texte
    for (const sectionId of [...debounceSections.keys()]) {
        clearTimeout(debounceSections.get(sectionId)!);
        debounceSections.delete(sectionId);
        saves.push(saveSection(sectionId));
    }

    // Paragraphes de sections dynamiques
    for (const [paragrapheId, { sectionId }] of [
        ...debounceSectionParagraphesData.entries(),
    ]) {
        const timer = debounceSectionParagraphes.get(paragrapheId);

        if (timer) {
            clearTimeout(timer);
        }

        debounceSectionParagraphes.delete(paragrapheId);
        saves.push(saveSectionParagraphe(paragrapheId, sectionId));
    }

    debounceSectionParagraphesData.clear();

    // Conclusions de sections individuelles (clé = "${sectionId}_${userId}")
    for (const key of [...debounceSectionConclusions.keys()]) {
        const [sectionIdStr, userIdStr] = key.split('_');
        clearTimeout(debounceSectionConclusions.get(key)!);
        debounceSectionConclusions.delete(key);
        saves.push(
            saveSectionConclusion(Number(sectionIdStr), Number(userIdStr)),
        );
    }

    await Promise.all(saves);
}

/**
 * Collecte toutes les sections éditables (type texte/paragraphes ou mode classique)
 * en un tableau de GlobalSection pour la modale Antidote.
 * Les sections de type 'individuel' et 'entrevue' sont exclues.
 */
function buildSectionsForAntidote(): GlobalSection[] {
    const result: GlobalSection[] = [];

    if (props.sections.length > 0) {
        for (const section of props.sections) {
            if (section.type === 'texte') {
                result.push({
                    id: `section_${section.id}`,
                    label: section.label,
                    html: sectionContenus[section.id] ?? '',
                });
            } else if (section.type === 'paragraphes') {
                for (const para of sectionParagraphesLocaux[section.id] ?? []) {
                    result.push({
                        id: `para_${para.id}`,
                        label: `${section.label} — §${para.ordre}`,
                        html: para.contenu ?? '',
                    });
                }
            }
        }
    } else {
        result.push(
            {
                id: 'intro_amener',
                label: 'Introduction — Amener',
                html: form.introduction_amener,
            },
            {
                id: 'intro_poser',
                label: 'Introduction — Poser',
                html: form.introduction_poser,
            },
            {
                id: 'intro_diviser',
                label: 'Introduction — Diviser',
                html: form.introduction_diviser,
            },
        );

        for (const dev of developpements.value) {
            result.push({
                id: `dev_${dev.id}`,
                label: `Développement ${dev.ordre}`,
                html: dev.contenu ?? '',
            });
        }

        for (const m of props.membres) {
            result.push({
                id: `concl_${m.id}`,
                label: `Conclusion — ${m.prenom} ${m.nom}`,
                html: conclusionsLocales[m.id] ?? '',
            });
        }
    }

    return result;
}

/**
 * Applique les sections corrigées par Antidote dans les refs locales,
 * puis planifie la sauvegarde de chaque zone modifiée.
 */
function onSectionsCorrigees(corrigees: GlobalSection[]) {
    clearAllDebounces();

    for (const s of corrigees) {
        if (s.id.startsWith('section_')) {
            const id = Number(s.id.replace('section_', ''));
            sectionContenus[id] = s.html;
            scheduleSectionSave(id);
        } else if (s.id.startsWith('para_')) {
            const paraId = Number(s.id.replace('para_', ''));

            for (const [rawSectionId, paragraphes] of Object.entries(
                sectionParagraphesLocaux,
            )) {
                const para = paragraphes.find((p) => p.id === paraId);

                if (para) {
                    para.contenu = s.html;
                    scheduleSectionParagrapheSave(paraId, Number(rawSectionId));
                    break;
                }
            }
        } else if (s.id === 'intro_amener') {
            form.introduction_amener = s.html;
        } else if (s.id === 'intro_poser') {
            form.introduction_poser = s.html;
        } else if (s.id === 'intro_diviser') {
            form.introduction_diviser = s.html;
        } else if (s.id.startsWith('dev_')) {
            const devId = Number(s.id.replace('dev_', ''));
            const dev = developpements.value.find((d) => d.id === devId);

            if (dev) {
                dev.contenu = s.html;
                scheduleDeveloppementSave(devId);
            }
        } else if (s.id.startsWith('concl_')) {
            const membreId = Number(s.id.replace('concl_', ''));
            conclusionsLocales[membreId] = s.html;
            scheduleConclusionSave(membreId);
        }
    }
}

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

const debounceSectionParagraphes = new Map<
    number,
    ReturnType<typeof setTimeout>
>();
/** Stocke le sectionId associé à chaque paragrapheId pour permettre le flush immédiat. */
const debounceSectionParagraphesData = new Map<number, { sectionId: number }>();
const sectionParagrapheEnCours = reactive<Record<number, boolean>>({});

function scheduleSectionParagrapheSave(
    paragrapheId: number,
    sectionId: number,
) {
    if (!props.peutEditer) {
        return;
    }

    saveStatus.value = 'saving';
    const existing = debounceSectionParagraphes.get(paragrapheId);

    if (existing) {
        clearTimeout(existing);
    }

    debounceSectionParagraphes.set(
        paragrapheId,
        setTimeout(() => saveSectionParagraphe(paragrapheId, sectionId), 1500),
    );
    debounceSectionParagraphesData.set(paragrapheId, { sectionId });
}

async function saveSectionParagraphe(paragrapheId: number, sectionId: number) {
    if (!props.peutEditer) {
        return;
    }

    const paragraphes = sectionParagraphesLocaux[sectionId];

    if (!paragraphes) {
        return;
    }

    const p = paragraphes.find((p) => p.id === paragrapheId);

    if (!p) {
        return;
    }

    try {
        await axios.patch(
            `${baseUrl.value}/sections/${sectionId}/paragraphes/${paragrapheId}`,
            {
                titre: p.titre,
                contenu: p.contenu,
            },
        );
        saveStatus.value = 'saved';
        setTimeout(() => {
            saveStatus.value = 'idle';
        }, 2000);
    } catch {
        saveStatus.value = 'error';
    }
}

async function ajouterSectionParagraphe(sectionId: number) {
    if (sectionParagrapheEnCours[sectionId]) {
        return;
    }

    sectionParagrapheEnCours[sectionId] = true;

    try {
        const response = await axios.post(
            `${baseUrl.value}/sections/${sectionId}/paragraphes`,
        );

        if (!sectionParagraphesLocaux[sectionId]) {
            sectionParagraphesLocaux[sectionId] = [];
        }

        sectionParagraphesLocaux[sectionId].push(response.data.paragraphe);
    } finally {
        sectionParagrapheEnCours[sectionId] = false;
    }
}

async function supprimerSectionParagraphe(
    paragrapheId: number,
    sectionId: number,
) {
    const paragraphes = sectionParagraphesLocaux[sectionId];

    if (!paragraphes || paragraphes.length <= 1) {
        return;
    }

    if (sectionParagrapheEnCours[sectionId]) {
        return;
    }

    sectionParagrapheEnCours[sectionId] = true;

    try {
        await axios.delete(
            `${baseUrl.value}/sections/${sectionId}/paragraphes/${paragrapheId}`,
        );
        sectionParagraphesLocaux[sectionId] = sectionParagraphesLocaux[
            sectionId
        ]
            .filter((p) => p.id !== paragrapheId)
            .map((p, i) => ({ ...p, ordre: i + 1 }));
    } finally {
        sectionParagrapheEnCours[sectionId] = false;
    }
}

// ─── Conclusions de sections dynamiques (type 'individuel') ──────────────────

const debounceSectionConclusions = new Map<
    string,
    ReturnType<typeof setTimeout>
>();

function scheduleSectionConclusionSave(sectionId: number, userId: number) {
    if (!props.peutEditer) {
        return;
    }

    saveStatus.value = 'saving';
    const key = `${sectionId}_${userId}`;
    const existing = debounceSectionConclusions.get(key);

    if (existing) {
        clearTimeout(existing);
    }

    debounceSectionConclusions.set(
        key,
        setTimeout(() => saveSectionConclusion(sectionId, userId), 1500),
    );
}

async function saveSectionConclusion(sectionId: number, userId: number) {
    if (!props.peutEditer) {
        return;
    }

    try {
        await axios.put(`${baseUrl.value}/conclusion`, {
            user_id: userId,
            section_id: sectionId,
            contenu: sectionConclusionsLocales[sectionId]?.[userId] ?? '',
        });
        saveStatus.value = 'saved';
        setTimeout(() => {
            saveStatus.value = 'idle';
        }, 2000);
    } catch {
        saveStatus.value = 'error';
    }
}

// ─── Concepts d'entrevue (type 'entrevue') ────────────────────────────────────

const debounceConceptsLabel = new Map<number, ReturnType<typeof setTimeout>>();
const debounceLignes = new Map<number, ReturnType<typeof setTimeout>>();
const conceptEnCours = reactive<Record<number, boolean>>({});

function scheduleConceptLabelSave(conceptId: number, sectionId: number) {
    if (!props.peutEditer) {
        return;
    }

    saveStatus.value = 'saving';
    const existing = debounceConceptsLabel.get(conceptId);

    if (existing) {
        clearTimeout(existing);
    }

    debounceConceptsLabel.set(
        conceptId,
        setTimeout(() => saveConceptLabel(conceptId, sectionId), 1500),
    );
}

async function saveConceptLabel(conceptId: number, sectionId: number) {
    if (!props.peutEditer) {
        return;
    }

    const concepts = sectionConceptsLocaux[sectionId];

    if (!concepts) {
        return;
    }

    const c = concepts.find((c) => c.id === conceptId);

    if (!c) {
        return;
    }

    try {
        await axios.patch(
            `${baseUrl.value}/sections/${sectionId}/concepts/${conceptId}`,
            { label: c.label },
        );
        saveStatus.value = 'saved';
        setTimeout(() => {
            saveStatus.value = 'idle';
        }, 2000);
    } catch {
        saveStatus.value = 'error';
    }
}

function scheduleLigneSave(
    ligneId: number,
    conceptId: number,
    sectionId: number,
) {
    if (!props.peutEditer) {
        return;
    }

    saveStatus.value = 'saving';
    const existing = debounceLignes.get(ligneId);

    if (existing) {
        clearTimeout(existing);
    }

    debounceLignes.set(
        ligneId,
        setTimeout(() => saveLigne(ligneId, conceptId, sectionId), 1500),
    );
}

async function saveLigne(
    ligneId: number,
    conceptId: number,
    sectionId: number,
) {
    if (!props.peutEditer) {
        return;
    }

    const concepts = sectionConceptsLocaux[sectionId];

    if (!concepts) {
        return;
    }

    const c = concepts.find((c) => c.id === conceptId);

    if (!c) {
        return;
    }

    const l = c.lignes.find((l) => l.id === ligneId);

    if (!l) {
        return;
    }

    try {
        await axios.patch(
            `${baseUrl.value}/sections/${sectionId}/concepts/${conceptId}/lignes/${ligneId}`,
            {
                dimension: l.dimension,
                indicateur: l.indicateur,
                questions: l.questions,
            },
        );
        saveStatus.value = 'saved';
        setTimeout(() => {
            saveStatus.value = 'idle';
        }, 2000);
    } catch {
        saveStatus.value = 'error';
    }
}

async function ajouterConcept(sectionId: number) {
    if (conceptEnCours[sectionId]) {
        return;
    }

    conceptEnCours[sectionId] = true;

    try {
        const response = await axios.post(
            `${baseUrl.value}/sections/${sectionId}/concepts`,
            { label: 'Nouveau concept' },
        );

        if (!sectionConceptsLocaux[sectionId]) {
            sectionConceptsLocaux[sectionId] = [];
        }

        sectionConceptsLocaux[sectionId].push({
            ...response.data.concept,
            lignes: [],
        });
    } finally {
        conceptEnCours[sectionId] = false;
    }
}

async function supprimerConcept(conceptId: number, sectionId: number) {
    if (conceptEnCours[sectionId]) {
        return;
    }

    conceptEnCours[sectionId] = true;

    try {
        await axios.delete(
            `${baseUrl.value}/sections/${sectionId}/concepts/${conceptId}`,
        );
        sectionConceptsLocaux[sectionId] = sectionConceptsLocaux[sectionId]
            .filter((c) => c.id !== conceptId)
            .map((c, i) => ({ ...c, ordre: i + 1 }));
    } finally {
        conceptEnCours[sectionId] = false;
    }
}

async function ajouterLigne(conceptId: number, sectionId: number) {
    const concepts = sectionConceptsLocaux[sectionId];

    if (!concepts) {
        return;
    }

    try {
        const response = await axios.post(
            `${baseUrl.value}/sections/${sectionId}/concepts/${conceptId}/lignes`,
        );
        const c = concepts.find((c) => c.id === conceptId);

        if (c) {
            c.lignes.push({ ...response.data.ligne, questions: [] });
        }
    } catch {
        saveStatus.value = 'error';
    }
}

async function supprimerLigne(
    ligneId: number,
    conceptId: number,
    sectionId: number,
) {
    const concepts = sectionConceptsLocaux[sectionId];

    if (!concepts) {
        return;
    }

    try {
        await axios.delete(
            `${baseUrl.value}/sections/${sectionId}/concepts/${conceptId}/lignes/${ligneId}`,
        );
        const c = concepts.find((c) => c.id === conceptId);

        if (c) {
            c.lignes = c.lignes
                .filter((l) => l.id !== ligneId)
                .map((l, i) => ({ ...l, ordre: i + 1 }));
        }
    } catch {
        saveStatus.value = 'error';
    }
}

function ajouterQuestion(
    ligne: EntrevueLigne,
    conceptId: number,
    sectionId: number,
) {
    ligne.questions = [...ligne.questions, ''];
    scheduleLigneSave(ligne.id, conceptId, sectionId);
}

function supprimerQuestion(
    ligne: EntrevueLigne,
    qIndex: number,
    conceptId: number,
    sectionId: number,
) {
    ligne.questions = ligne.questions.filter((_, i) => i !== qIndex);
    scheduleLigneSave(ligne.id, conceptId, sectionId);
}

function totalQuestions(sectionId: number): number {
    return (sectionConceptsLocaux[sectionId] ?? []).reduce(
        (sum, c) =>
            sum + c.lignes.reduce((s, l) => s + (l.questions?.length ?? 0), 0),
        0,
    );
}

// ─── Collapse / expand des sections ──────────────────────────────────────────

const collapsed = reactive<Record<string, boolean>>({
    pageTitre: false,
    tdm: false,
    introduction: false,
    criteres_global: true,
});

/**
 * Retourne true si la grille de critères d'une section est repliée.
 * Par défaut (clé absente) : repliée.
 */
function isCriteresSectionCollapsed(sectionId: number): boolean {
    return collapsed[`criteres_section_${sectionId}`] ?? true;
}

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

// ─── Données pour la page titre ──────────────────────────────────────────────

const dateAujourd = computed(() =>
    new Date().toLocaleDateString('fr-CA', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

const codeComplet = computed(() => {
    const cours = (props as any).cours as
        | { code?: string; groupe?: string }
        | undefined;

    return `${cours?.code ?? props.classe.code} / Gr. ${cours?.groupe ?? ''}`;
});

/**
 * Génère le HTML du gabarit de page titre à partir des données disponibles.
 * Appelé une seule fois (onMounted) quand genererPageTitre = true et que le contenu est vide.
 */
function genererContenuPageTitre(): string {
    const cours = (props as any).cours as { nom_cours?: string } | undefined;
    const lignesMembres = props.membres
        .map((m) => `<p>${m.prenom} ${m.nom}</p>`)
        .join('');

    return [
        lignesMembres,
        `<p>${cours?.nom_cours ?? ''}</p>`,
        `<p>${codeComplet.value}</p>`,
        '<p>&nbsp;</p>',
        '<p><strong>[Titre du projet]</strong></p>',
        '<p>RECHERCHE DOCUMENTAIRE</p>',
        '<p>&nbsp;</p>',
        '<p>Travail présenté à</p>',
        `<p><strong>${props.enseignant.prenom} ${props.enseignant.nom}</strong></p>`,
        '<p>&nbsp;</p>',
        '<p>Département des sciences humaines</p>',
        '<p>Cégep de Drummondville</p>',
        `<p>Le ${dateAujourd.value}</p>`,
    ].join('');
}

// ─── Pré-remplissage page titre en mode auto ──────────────────────────────────

onMounted(() => {
    if (props.genererPageTitre && !form.page_titre_contenu) {
        form.page_titre_contenu = genererContenuPageTitre();
    }
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
        champs.push(
            'introduction_amener',
            'introduction_poser',
            'introduction_diviser',
        );
        champs.push(
            ...developpements.value.map((d) => `developpement_${d.id}`),
        );
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

const annotations = reactive<Record<string, Annotation[]>>({
    ...props.annotationsParChamp,
});
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

const modeEditionEnseignant = ref(props.modeEditionEnseignant);

async function toggleModeEditionEnseignant(): Promise<void> {
    const response = await axios.patch(
        `${baseUrl.value}/mode-edition-enseignant`,
    );
    modeEditionEnseignant.value = response.data.mode_edition_enseignant;
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
    only: [
        'verrouille',
        'correctionVisible',
        'peutEditer',
        'peutRemettre',
        'annotationsParChamp',
        'votes',
        'remisLe',
    ],
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
        const response = await axios.post(`${baseUrl.value}/voter-remise`, {
            vote: true,
        });

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
    payload: {
        commentaire_id: string;
        contenu: string;
        annotation_type: 'commentaire' | 'correction';
        points_malus: number | null;
        cible_user_id: number | null;
        html: string;
    },
): Promise<void> {
    const response = await axios.put(`${baseUrl.value}/annotations`, {
        champ,
        ...payload,
    });

    if (!annotations[champ]) {
        annotations[champ] = [];
    }

    // L'endpoint fait un upsert sur commentaire_id — on met à jour localement si déjà présent
    const existingIndex = annotations[champ].findIndex(
        (a) => a.commentaire_id === payload.commentaire_id,
    );

    if (existingIndex !== -1) {
        annotations[champ][existingIndex].contenu = response.data.contenu;
        annotations[champ][existingIndex].annotation_type =
            response.data.annotation_type ?? 'commentaire';
        annotations[champ][existingIndex].points_malus =
            response.data.points_malus ?? null;
        annotations[champ][existingIndex].cible_user_id =
            response.data.cible_user_id ?? null;
    } else {
        annotations[champ].push({
            id: response.data.id,
            commentaire_id: response.data.commentaire_id,
            contenu: response.data.contenu,
            annotation_type: response.data.annotation_type ?? 'commentaire',
            points_malus: response.data.points_malus ?? null,
            cible_user_id: response.data.cible_user_id ?? null,
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
        await axios.delete(
            `${baseUrl.value}/annotations/${payload.correction.id}`,
            {
                data: { champ, html: payload.html },
            },
        );

        if (annotations[champ]) {
            annotations[champ] = annotations[champ].filter(
                (a) => a.id !== payload.correction.id,
            );
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
            const paragId = parseInt(
                champ.replace('section_paragraphe_', ''),
                10,
            );

            for (const paragraphes of Object.values(sectionParagraphesLocaux)) {
                const p = (paragraphes as SectionParagraphe[]).find(
                    (p) => p.id === paragId,
                );

                if (p) {
                    p.contenu = payload.html;
                    break;
                }
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
            const paragId = parseInt(
                champ.replace('section_paragraphe_', ''),
                10,
            );

            for (const paragraphes of Object.values(sectionParagraphesLocaux)) {
                const p = (paragraphes as SectionParagraphe[]).find(
                    (p) => p.id === paragId,
                );

                if (p) {
                    p.contenu = payload.htmlOriginal;
                    break;
                }
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

        annotationDeleteError.value =
            "Impossible de supprimer l'annotation. Réessayez.";
        setTimeout(() => (annotationDeleteError.value = null), 5000);
    }
}

// ─── Renvois (endnotes) ───────────────────────────────────────────────────────

const renvoisLocaux = ref<Renvoi[]>([...props.renvois]);
const renvoiEnCours = ref(false);

/**
 * Incrémenté après chaque supprimerRenvoi pour forcer le watcher syncRenvois
 * dans tous les RichEditor, même si Vue rate une mutation profonde sur renvoisLocaux.
 */
const renvoisSyncVersion = ref(0);

/** Snapshot des renvoiId présents dans chaque éditeur, indexés par editorId. */
const renvoisParEditor = ref(new Map<string, number[]>());

/**
 * Éditeurs ayant déjà émis au moins un rapport depuis ce chargement de page.
 * Empêche la suppression automatique lors du premier rapport (initialisation TipTap).
 */
const editorsInitialises = ref(new Set<string>());

/**
 * Reçoit le snapshot des renvoiId utilisés par un éditeur.
 *
 * - Premier rapport (montage de l'éditeur) : pas de suppression — les autres éditeurs
 *   n'ont pas encore eu le temps de signaler leurs IDs, ce qui provoquerait des faux positifs.
 * - Rapports suivants : si un renvoiId disparaît de TOUS les éditeurs, le modal de
 *   confirmation s'ouvre (source='editeur') plutôt que de supprimer silencieusement.
 */
function handleRenvoisUtilises(editorId: string, ids: number[]): void {
    const isFirstReport = !editorsInitialises.value.has(editorId);
    const previousIds = renvoisParEditor.value.get(editorId) ?? [];

    renvoisParEditor.value.set(editorId, ids);
    editorsInitialises.value.add(editorId);

    if (isFirstReport) {
        return;
    }

    // IDs retirés de cet éditeur ET absents de tous les autres éditeurs → demander confirmation
    const tousLesIds = new Set([...renvoisParEditor.value.values()].flat());
    const aSupprimer = previousIds.filter((id) => {
        if (tousLesIds.has(id)) {
            return false;
        } // Toujours présent dans un autre éditeur

        if (id === renvoisSupprimerCibleId.value) {
            return false;
        } // Déjà en cours de confirmation

        if (renvoisSupprimerFile.value.some((f) => f.id === id)) {
            return false;
        } // Déjà en file

        return true;
    });
    aSupprimer.forEach((id) =>
        demanderSupprimerRenvoi(id, 'editeur', editorId),
    );
}

/**
 * Crée un nouveau renvoi via l'API puis l'insère dans l'éditeur actif.
 * Appelé directement depuis chaque RichEditor sans passer par un modal.
 *
 * Le flush immédiat après insertFn est critique : TipTap émet onUpdate de façon
 * synchrone, ce qui déclenche scheduleSectionSave, mais le debounce de 1,5 s
 * serait perdu si l'utilisateur naviguait avant son expiration. On sauvegarde
 * donc immédiatement pour garantir la persistance du nœud renvoiMark inséré.
 */
async function creerEtInsererRenvoi(
    insertFn: (renvoiId: number, numero: number) => void,
    contenuInitial: string | null = null,
    typeReference: string | null = null,
    champsReference: Record<string, string> | null = null,
) {
    if (renvoiEnCours.value) {
        return;
    }

    renvoiEnCours.value = true;

    try {
        const response = await axios.post(`${baseUrl.value}/renvois`, {
            contenu: contenuInitial,
            type_reference: typeReference,
            champs_reference: champsReference,
        });
        const renvoi: Renvoi = { ...response.data.renvoi, commentaires: [] };
        renvoisLocaux.value.push(renvoi);
        insertFn(renvoi.id, renvoi.numero);
        // TipTap → onUpdate → emit update:modelValue → scheduleSectionSave : tout synchrone.
        // On flush immédiatement plutôt que d'attendre le debounce.
        await flushAllPendingSaves();
    } finally {
        renvoiEnCours.value = false;
    }
}

// ─── Aide à la référence APA ──────────────────────────────────────────────────

/**
 * Stocke le callback d'insertion en attente quand le modal APA est ouvert.
 * Le callback est fourni par le RichEditor via @demander-renvoi.
 */
const insertFnEnAttente = ref<
    ((renvoiId: number, numero: number) => void) | null
>(null);
const referenceModalOuvert = ref(false);
/** Renvoi en cours d'édition — null en mode création */
const renvoisEnEdition = ref<Renvoi | null>(null);

// Nettoyer l'état de mode quand le modal se ferme (annulation ou confirmation)
watch(referenceModalOuvert, (ouvert) => {
    if (!ouvert) {
        renvoisEnEdition.value = null;
        insertFnEnAttente.value = null;
    }
});

/**
 * Intercepte la demande d'insertion d'un renvoi.
 *
 * Si aide_reference est activé sur ce type de projet, ouvre le modal APA
 * au lieu de créer le renvoi immédiatement.
 */
function demanderRenvoi(
    insertFn: (renvoiId: number, numero: number) => void,
): void {
    if (props.aideReference) {
        insertFnEnAttente.value = insertFn;
        referenceModalOuvert.value = true;
    } else {
        creerEtInsererRenvoi(insertFn);
    }
}

/**
 * Reçoit la référence APA depuis le modal et dispatch vers création ou mise à jour.
 */
async function confirmerReferenceApa(
    contenu: string,
    typeReference: string,
    champsReference: Record<string, string>,
): Promise<void> {
    if (renvoisEnEdition.value) {
        await mettreAJourRenvoi(
            renvoisEnEdition.value.id,
            contenu,
            typeReference,
            champsReference,
        );
    } else {
        if (!insertFnEnAttente.value) {
            return;
        }

        await creerEtInsererRenvoi(
            insertFnEnAttente.value,
            contenu,
            typeReference,
            champsReference,
        );
    }
}

/**
 * Ouvre le modal APA en mode édition pré-rempli avec le renvoi existant.
 */
function ouvrirEditionRenvoi(renvoi: Renvoi): void {
    renvoisEnEdition.value = renvoi;
    referenceModalOuvert.value = true;
}

/**
 * Met à jour un renvoi existant via PATCH et synchronise renvoisLocaux réactivement.
 */
async function mettreAJourRenvoi(
    renvoiId: number,
    contenu: string,
    typeReference: string,
    champsReference: Record<string, string>,
): Promise<void> {
    await axios.patch(`${baseUrl.value}/renvois/${renvoiId}`, {
        contenu,
        type_reference: typeReference,
        champs_reference: champsReference,
    });
    const renvoi = renvoisLocaux.value.find((r) => r.id === renvoiId);

    if (renvoi) {
        renvoi.contenu = contenu;
        renvoi.type_reference = typeReference;
        renvoi.champs_reference = champsReference;
    }
}

const debounceRenvois = new Map<number, ReturnType<typeof setTimeout>>();

/**
 * Convertit le contenu d'un renvoi en HTML compatible TipTap.
 *
 * Les renvois créés avant l'introduction du RichEditor sont en texte brut ;
 * on les enveloppe dans un <p> pour que TipTap les charge correctement.
 */
function renvoiContenuHtml(contenu: string | null): string {
    if (!contenu) {
        return '';
    }

    // Déjà du HTML (sauvegardé par TipTap lors d'une session précédente)
    if (contenu.trimStart().startsWith('<')) {
        return contenu;
    }

    return `<p>${contenu}</p>`;
}

/**
 * Planifie la sauvegarde du contenu d'un renvoi après 1,5 s sans frappe.
 */
function scheduleRenvoiSave(renvoiId: number) {
    if (!props.peutEditer) {
        return;
    }

    const existing = debounceRenvois.get(renvoiId);

    if (existing) {
        clearTimeout(existing);
    }

    debounceRenvois.set(
        renvoiId,
        setTimeout(() => saveRenvoi(renvoiId), 1500),
    );
}

async function saveRenvoi(renvoiId: number) {
    const renvoi = renvoisLocaux.value.find((r) => r.id === renvoiId);

    if (!renvoi) {
        return;
    }

    try {
        await axios.patch(`${baseUrl.value}/renvois/${renvoiId}`, {
            contenu: renvoi.contenu,
        });
    } catch {
        saveStatus.value = 'error';
    }
}

async function supprimerRenvoi(renvoiId: number) {
    const cible = renvoisLocaux.value.find((r) => r.id === renvoiId);

    if (!cible) {
        return;
    }

    const numeroCible = cible.numero;
    const url = `${baseUrl.value}/renvois/${renvoiId}`;

    try {
        await axios.delete(url);
        renvoisLocaux.value = renvoisLocaux.value.filter(
            (r) => r.id !== renvoiId,
        );
        await renumeroterapresSupression(numeroCible);
        // Force le déclenchement du watcher syncRenvois dans tous les RichEditor,
        // même si Vue a raté une mutation profonde sur renvoisLocaux.
        renvoisSyncVersion.value++;
        // Attendre que Vue flush les watchers syncRenvois (async) → onUpdate → scheduleSectionSave,
        // puis sauvegarder immédiatement pour ne pas perdre le HTML corrigé si l'utilisateur navigue.
        await nextTick();
        await flushAllPendingSaves();
    } catch {
        saveStatus.value = 'error';
    }
}

// ─── Confirmation suppression de référence (bouton 🗑️ ou exposant retiré) ───

/** ID du renvoi ciblé par le modal de confirmation. */
const renvoisSupprimerCibleId = ref<number | null>(null);
/** true → le modal de confirmation est visible. */
const renvoisSupprimerModalOuvert = ref(false);
/** true pendant l'appel API de suppression déclenché via le modal. */
const renvoisSupprimerEnCours = ref(false);
/**
 * 'bouton' → clic sur 🗑️ dans la liste des références.
 * 'editeur' → l'exposant a été retiré directement dans TipTap.
 */
const renvoisSupprimerSource = ref<'bouton' | 'editeur'>('bouton');
/** File d'attente pour les suppressions simultanées (multi-exposants retirés d'un coup). */
const renvoisSupprimerFile = ref<
    Array<{ id: number; source: 'bouton' | 'editeur'; editorId?: string }>
>([]);

/** editorId de l'éditeur TipTap ayant déclenché la suppression via source='editeur'. */
const renvoisSupprimerEditorId = ref<string | null>(null);

/**
 * Signal d'annulation — incrémenté quand l'utilisateur annule le modal après avoir retiré
 * un exposant directement dans l'éditeur. RichEditor surveille ce ref via inject pour
 * appeler undo() sur l'éditeur concerné et restaurer l'exposant.
 */
const renvoisUndoTarget = ref<{ editorId: string; version: number } | null>(
    null,
);
provide('renvoisUndoTarget', renvoisUndoTarget);

/** Description du modal selon la source de la demande. */
const renvoisSupprimerDescription = computed(() =>
    renvoisSupprimerSource.value === 'editeur'
        ? "L'exposant a été retiré du texte. Voulez-vous aussi supprimer la note de référence correspondante ?"
        : 'Cette note de renvoi sera définitivement supprimée. Les exposants correspondants seront retirés du texte.',
);

/**
 * Ouvre le modal de confirmation avant de supprimer un renvoi.
 * Appelé par le bouton 🗑️ (source='bouton') ou lorsque l'éditeur TipTap détecte
 * qu'un exposant a été retiré du texte (source='editeur').
 * Si le modal est déjà ouvert, la demande est mise en file d'attente.
 *
 * @param renvoiId  ID du renvoi à supprimer.
 * @param source    Origine de la demande ('bouton' par défaut).
 * @param editorId  Identifiant de l'éditeur source (uniquement quand source='editeur').
 */
function demanderSupprimerRenvoi(
    renvoiId: number,
    source: 'bouton' | 'editeur' = 'bouton',
    editorId?: string,
): void {
    if (renvoisSupprimerModalOuvert.value) {
        renvoisSupprimerFile.value.push({ id: renvoiId, source, editorId });

        return;
    }

    renvoisSupprimerCibleId.value = renvoiId;
    renvoisSupprimerSource.value = source;
    renvoisSupprimerEditorId.value = editorId ?? null;
    renvoisSupprimerModalOuvert.value = true;
}

/** Traite la prochaine demande en file d'attente, si elle existe. */
function processerFileSuppressionRenvois(): void {
    let next = renvoisSupprimerFile.value.shift();

    // Ignorer silencieusement les entrées dont le renvoi a déjà été supprimé (doublons)
    while (next && !renvoisLocaux.value.some((r) => r.id === next!.id)) {
        next = renvoisSupprimerFile.value.shift();
    }

    if (next) {
        nextTick().then(() =>
            demanderSupprimerRenvoi(next!.id, next!.source, next!.editorId),
        );
    }
}

/** Confirme et exécute la suppression après validation dans le modal. */
async function confirmerSupprimerRenvoi(): Promise<void> {
    if (!renvoisSupprimerCibleId.value) {
        return;
    }

    renvoisSupprimerEnCours.value = true;

    try {
        await supprimerRenvoi(renvoisSupprimerCibleId.value);
        renvoisSupprimerModalOuvert.value = false;
    } finally {
        renvoisSupprimerEnCours.value = false;
        renvoisSupprimerCibleId.value = null;
        processerFileSuppressionRenvois();
    }
}

/**
 * Ferme le modal après annulation par l'utilisateur (X, Escape, clic extérieur)
 * et traite la prochaine demande en file d'attente.
 * Appelé uniquement par @update:open du ConfirmationModal (pas par confirmerSupprimerRenvoi).
 *
 * @param ouvert  Nouvelle valeur de visibilité émise par le modal.
 */
function onModalRenvoisUpdateOpen(ouvert: boolean): void {
    renvoisSupprimerModalOuvert.value = ouvert;

    if (!ouvert) {
        // Si l'utilisateur annule et que c'est l'éditeur qui avait retiré l'exposant,
        // on demande au RichEditor concerné d'effectuer un undo pour le restaurer.
        if (
            renvoisSupprimerSource.value === 'editeur' &&
            renvoisSupprimerEditorId.value
        ) {
            renvoisUndoTarget.value = {
                editorId: renvoisSupprimerEditorId.value,
                version: (renvoisUndoTarget.value?.version ?? 0) + 1,
            };
        }

        renvoisSupprimerCibleId.value = null;
        renvoisSupprimerEditorId.value = null;
        processerFileSuppressionRenvois();
    }
}

/**
 * Décrémente le numéro de tous les renvois dont le numéro dépasse celui du renvoi supprimé,
 * met à jour renvoisLocaux réactivement (ce qui déclenche le watcher dans RichEditor),
 * puis persiste les nouveaux numéros en base via PATCH séquentiels.
 *
 * Les PATCHes sont envoyés en ordre croissant de numéro et un par un — la contrainte
 * unique (projet_id, numero) interdit les mises à jour parallèles : si #4→3 et #5→4
 * s'exécutent simultanément, la DB rejette #5→4 tant que #4 n'est pas encore à 3.
 */
async function renumeroterapresSupression(numeroSupprime: number) {
    const affectees = renvoisLocaux.value
        .filter((r) => r.numero > numeroSupprime)
        .sort((a, b) => a.numero - b.numero);
    affectees.forEach((r) => {
        r.numero -= 1;
    });

    for (const r of affectees) {
        await axios.patch(`${baseUrl.value}/renvois/${r.id}`, {
            numero: r.numero,
        });
    }
}

// ─── Commentaires d'enseignant sur les renvois ────────────────────────────────

/** Texte en cours de saisie par l'enseignant, indexé par renvoiId. */
const renvoiNouveauCommentaire = reactive<Record<number, string>>({});
/** Verrou anti-double-envoi par renvoi. */
const renvoiCommentaireEnCours = reactive<Record<number, boolean>>({});

/**
 * Soumet un nouveau commentaire d'enseignant sur un renvoi donné.
 */
async function ajouterCommentaireRenvoi(renvoiId: number): Promise<void> {
    const contenu = (renvoiNouveauCommentaire[renvoiId] ?? '').trim();

    if (!contenu || renvoiCommentaireEnCours[renvoiId]) {
        return;
    }

    renvoiCommentaireEnCours[renvoiId] = true;

    try {
        const response = await axios.post(
            `${baseUrl.value}/renvois/${renvoiId}/commentaires`,
            { contenu },
        );
        const renvoi = renvoisLocaux.value.find((r) => r.id === renvoiId);

        if (renvoi) {
            renvoi.commentaires.push(
                response.data.commentaire as RenvoiCommentaire,
            );
        }

        renvoiNouveauCommentaire[renvoiId] = '';
    } finally {
        renvoiCommentaireEnCours[renvoiId] = false;
    }
}

/**
 * Supprime un commentaire d'enseignant sur un renvoi.
 */
async function supprimerCommentaireRenvoi(
    renvoiId: number,
    commentaireId: number,
): Promise<void> {
    await axios.delete(
        `${baseUrl.value}/renvois/${renvoiId}/commentaires/${commentaireId}`,
    );
    const renvoi = renvoisLocaux.value.find((r) => r.id === renvoiId);

    if (renvoi) {
        renvoi.commentaires = renvoi.commentaires.filter(
            (c) => c.id !== commentaireId,
        );
    }
}
</script>

<template>
    <AppLayout>
        <Head
            :title="
                t('projets.show.page_head', {
                    nom: t('classes.groupes.group_number', {
                        n: groupe.numero,
                    }),
                })
            "
        />

        <div class="mx-auto flex w-full max-w-6xl">
            <div class="flex min-w-0 flex-1 flex-col gap-3 p-3">
                <!-- En-tête navigation -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <Button variant="ghost" size="sm" as-child>
                        <Link
                            :href="`/cours/${classe.cours_id}/classes/${groupe.classe_id}/groupes/${groupe.id}/projets`"
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

                        <span v-if="saveStatus === 'saving'">{{
                            t('projets.show.saving')
                        }}</span>
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
                            >{{ annotationDeleteError }}</span
                        >
                    </div>
                </div>

                <Heading
                    :title="
                        t('classes.groupes.group_number', { n: groupe.numero })
                    "
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

                <!-- Boutons d'export + notes finales par étudiant -->
                <div
                    class="-mx-3 border-b bg-white px-3 py-2 shadow-sm dark:bg-zinc-950"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div class="flex flex-wrap items-center gap-1">
                            <!-- ── Groupe 1 : Consultation & exports ─────────────── -->
                            <BoutonTooltip
                                texte="Voir le rendu final du projet"
                                variant="ghost"
                                size="sm"
                                as-child
                            >
                                <Link :href="`${baseUrl}/apercu`">
                                    <Eye class="h-4 w-4" />
                                    Aperçu
                                </Link>
                            </BoutonTooltip>
                            <BoutonTooltip
                                texte="Télécharger une version PDF (s'ouvre dans un nouvel onglet)"
                                variant="ghost"
                                size="sm"
                                as-child
                            >
                                <a :href="`${baseUrl}/pdf`" target="_blank">
                                    <FileText class="h-4 w-4" />
                                    PDF
                                </a>
                            </BoutonTooltip>
                            <BoutonTooltip
                                texte="Télécharger une version Word (.docx)"
                                variant="ghost"
                                size="sm"
                                as-child
                            >
                                <a :href="`${baseUrl}/word`">
                                    <Download class="h-4 w-4" />
                                    Word
                                </a>
                            </BoutonTooltip>
                            <BoutonTooltip
                                v-if="estEnseignant"
                                texte="Voir les notes de tous les étudiants du groupe"
                                variant="ghost"
                                size="sm"
                                as-child
                            >
                                <Link :href="`${baseUrl}/apercu-notes`">
                                    <FileBarChart class="h-4 w-4" />
                                    Notes
                                </Link>
                            </BoutonTooltip>

                            <!-- ── Séparateur ─────────────────────────────────────── -->
                            <Separator
                                orientation="vertical"
                                class="mx-1 h-5"
                            />

                            <!-- ── Groupe 2 : Outils ──────────────────────────────── -->
                            <BoutonTooltip
                                v-if="peutEditer && !verrouille"
                                texte="Lancer la correction orthographique globale avec Antidote"
                                variant="ghost"
                                size="sm"
                                class="text-green-700 hover:bg-green-50 hover:text-green-700"
                                @click="showAntidoteGlobal = true"
                            >
                                <SpellCheck class="h-4 w-4" />
                                Antidote
                            </BoutonTooltip>
                            <!-- Consentement vidéo — affiché si au moins une section vidéo/audio existe -->
                            <ConsentementVideo
                                v-if="hasVideoOrAudioSection && !estEnseignant"
                                :params="{
                                    cours: classe.cours_id,
                                    groupe: groupe.id,
                                    typeProjet: typeProjet.id,
                                }"
                                :consentement="consentement"
                            />
                            <BoutonTooltip
                                v-if="estEnseignant"
                                texte="Gérer les sections disponibles pour ce type de projet"
                                variant="ghost"
                                size="sm"
                                as-child
                            >
                                <Link
                                    :href="
                                        editTypeProjet.url({
                                            cours: classe.cours_id,
                                            typeProjet: typeProjet.id,
                                        })
                                    "
                                >
                                    <Settings2 class="h-4 w-4" />
                                    Sections
                                </Link>
                            </BoutonTooltip>
                            <BoutonTooltip
                                v-if="champsVisibles.length > 0"
                                :texte="
                                    tousCommentairesReduits
                                        ? 'Développer tous les commentaires enseignant'
                                        : 'Réduire tous les commentaires enseignant'
                                "
                                variant="ghost"
                                size="sm"
                                @click="toggleTousCommentaires"
                            >
                                <MessageSquare class="h-4 w-4" />
                                Commentaires
                            </BoutonTooltip>

                            <!-- ── Groupe 3 : Actions enseignant ──────────────────── -->
                            <template v-if="estEnseignant">
                                <Separator
                                    orientation="vertical"
                                    class="mx-1 h-5"
                                />
                                <Button
                                    :variant="
                                        modeEditionEnseignant
                                            ? 'default'
                                            : 'outline'
                                    "
                                    size="sm"
                                    @click="toggleModeEditionEnseignant"
                                >
                                    <Settings2 class="mr-2 h-4 w-4" />
                                    {{
                                        modeEditionEnseignant
                                            ? 'Mode édition actif'
                                            : 'Activer mode édition'
                                    }}
                                </Button>
                                <BoutonTooltip
                                    :texte="
                                        correctionVisible
                                            ? 'Masquer les corrections aux étudiants'
                                            : 'Publier les corrections pour que les étudiants puissent les consulter'
                                    "
                                    :variant="
                                        correctionVisible ? 'default' : 'ghost'
                                    "
                                    size="sm"
                                    @click="toggleCorrectionVisible"
                                >
                                    <CheckCircle2
                                        v-if="correctionVisible"
                                        class="h-4 w-4"
                                    />
                                    <Send v-else class="h-4 w-4" />
                                    Correction
                                </BoutonTooltip>
                                <BoutonTooltip
                                    :texte="
                                        verrouille
                                            ? 'Déverrouiller le document pour permettre les modifications'
                                            : 'Verrouiller le document pour empêcher toute modification'
                                    "
                                    :variant="
                                        verrouille ? 'destructive' : 'ghost'
                                    "
                                    size="sm"
                                    @click="toggleVerrouille"
                                >
                                    <Lock class="h-4 w-4" />
                                    Verrouiller
                                </BoutonTooltip>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- ─── Page titre ────────────────────────────────────────────── -->
                <Card v-if="genererPageTitre">
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle
                            class="text-sm font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ t('projets.show.page_title_card') }}
                        </CardTitle>
                        <BoutonTooltip
                            :texte="
                                collapsed.pageTitre ? 'Développer' : 'Réduire'
                            "
                            @click="toggleSection('pageTitre')"
                        >
                            <ChevronUp
                                v-if="!collapsed.pageTitre"
                                class="h-4 w-4"
                            />
                            <ChevronDown v-else class="h-4 w-4" />
                        </BoutonTooltip>
                    </CardHeader>
                    <CardContent v-show="!collapsed.pageTitre">
                        <RichEditor
                            v-model="form.page_titre_contenu"
                            :placeholder="
                                t('projets.show.page_titre_manuel_placeholder')
                            "
                            :read-only="!peutEditer"
                            :est-enseignant="estEnseignant"
                            :corrections="
                                annotations['page_titre_contenu'] ?? []
                            "
                            :renvois="renvoisLocaux"
                            :renvois-sync-version="renvoisSyncVersion"
                            editor-id="page-titre-contenu"
                            :membres="membres"
                            @update:model-value="scheduleSharedSave"
                            @save-annotation="
                                (p) =>
                                    sauvegarderAnnotation(
                                        'page_titre_contenu',
                                        p,
                                    )
                            "
                            @delete-annotation="
                                (p) =>
                                    supprimerAnnotation('page_titre_contenu', p)
                            "
                            @demander-renvoi="demanderRenvoi"
                            @renvois-utilises="handleRenvoisUtilises"
                        />

                        <!-- Commentaire enseignant -->
                        <CommentaireEnseignant
                            :commentaire="commentaires['normes_presentation']"
                            :brouillon="getBrouillon('normes_presentation')"
                            :est-reduit="
                                !!commentairesReduits['normes_presentation']
                            "
                            :is-saving="
                                !!commentairesSaving['normes_presentation']
                            "
                            :est-enseignant="estEnseignant"
                            :placeholder="
                                t('projets.show.comment_presentation')
                            "
                            class="mt-4"
                            @toggle="toggleCommentaire('normes_presentation')"
                            @save="
                                sauvegarderCommentaire('normes_presentation')
                            "
                            @delete="
                                supprimerCommentaire('normes_presentation')
                            "
                            @update:brouillon="
                                (v) => setBrouillon('normes_presentation', v)
                            "
                        />
                    </CardContent>
                </Card>
                <Card v-else>
                    <!-- ─── Page titre (mode manuel) ──────────────────────────────── -->
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle
                            class="text-sm font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ t('projets.show.page_titre_manuel_card') }}
                        </CardTitle>
                        <BoutonTooltip
                            :texte="
                                collapsed.pageTitre ? 'Développer' : 'Réduire'
                            "
                            @click="toggleSection('pageTitre')"
                        >
                            <ChevronUp
                                v-if="!collapsed.pageTitre"
                                class="h-4 w-4"
                            />
                            <ChevronDown v-else class="h-4 w-4" />
                        </BoutonTooltip>
                    </CardHeader>
                    <CardContent v-show="!collapsed.pageTitre">
                        <p class="mb-3 text-xs text-muted-foreground">
                            {{ t('projets.show.page_titre_manuel_hint') }}
                        </p>
                        <RichEditor
                            v-model="form.page_titre_contenu"
                            :placeholder="
                                t('projets.show.page_titre_manuel_placeholder')
                            "
                            :read-only="!peutEditer"
                            :est-enseignant="estEnseignant"
                            :corrections="
                                annotations['page_titre_contenu'] ?? []
                            "
                            :renvois="renvoisLocaux"
                            :renvois-sync-version="renvoisSyncVersion"
                            editor-id="page-titre-contenu"
                            :membres="membres"
                            @update:model-value="scheduleSharedSave"
                            @save-annotation="
                                (p) =>
                                    sauvegarderAnnotation(
                                        'page_titre_contenu',
                                        p,
                                    )
                            "
                            @delete-annotation="
                                (p) =>
                                    supprimerAnnotation('page_titre_contenu', p)
                            "
                            @demander-renvoi="demanderRenvoi"
                            @renvois-utilises="handleRenvoisUtilises"
                        />

                        <!-- Commentaire enseignant -->
                        <CommentaireEnseignant
                            :commentaire="commentaires['normes_presentation']"
                            :brouillon="getBrouillon('normes_presentation')"
                            :est-reduit="
                                !!commentairesReduits['normes_presentation']
                            "
                            :is-saving="
                                !!commentairesSaving['normes_presentation']
                            "
                            :est-enseignant="estEnseignant"
                            :placeholder="
                                t('projets.show.comment_presentation')
                            "
                            class="mt-4"
                            @toggle="toggleCommentaire('normes_presentation')"
                            @save="
                                sauvegarderCommentaire('normes_presentation')
                            "
                            @delete="
                                supprimerCommentaire('normes_presentation')
                            "
                            @update:brouillon="
                                (v) => setBrouillon('normes_presentation', v)
                            "
                        />
                    </CardContent>
                </Card>

                <!-- ─── Table des matières ─────────────────────────────────────── -->
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle
                            class="text-sm font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{
                                genererTableMatieres
                                    ? t('projets.show.toc_card')
                                    : t('projets.show.toc_manuel_card')
                            }}
                        </CardTitle>
                        <BoutonTooltip
                            :texte="collapsed.tdm ? 'Développer' : 'Réduire'"
                            @click="toggleSection('tdm')"
                        >
                            <ChevronUp v-if="!collapsed.tdm" class="h-4 w-4" />
                            <ChevronDown v-else class="h-4 w-4" />
                        </BoutonTooltip>
                    </CardHeader>
                    <CardContent v-show="!collapsed.tdm">
                        <!-- Mode automatique : notice informative, pas d'éditeur -->
                        <p
                            v-if="genererTableMatieres"
                            class="text-sm text-muted-foreground italic"
                        >
                            {{ t('projets.show.toc_auto_notice') }}
                        </p>
                        <!-- Mode manuel : hint + éditeur -->
                        <template v-else>
                            <p class="mb-3 text-xs text-muted-foreground">
                                {{ t('projets.show.tdm_manuel_hint') }}
                            </p>
                            <RichEditor
                                v-model="form.table_matieres_contenu"
                                :placeholder="
                                    t('projets.show.tdm_manuel_placeholder')
                                "
                                :read-only="!peutEditer"
                                :est-enseignant="estEnseignant"
                                :corrections="
                                    annotations['table_matieres_contenu'] ?? []
                                "
                                :renvois="renvoisLocaux"
                                :renvois-sync-version="renvoisSyncVersion"
                                editor-id="table-matieres-contenu"
                                :membres="membres"
                                @update:model-value="scheduleSharedSave"
                                @save-annotation="
                                    (p) =>
                                        sauvegarderAnnotation(
                                            'table_matieres_contenu',
                                            p,
                                        )
                                "
                                @delete-annotation="
                                    (p) =>
                                        supprimerAnnotation(
                                            'table_matieres_contenu',
                                            p,
                                        )
                                "
                                @demander-renvoi="demanderRenvoi"
                                @renvois-utilises="handleRenvoisUtilises"
                            />
                        </template>
                    </CardContent>
                </Card>

                <!-- ─── Critères globaux (hors section) ───────────────────────── -->
                <Card v-if="criteresGlobaux.length > 0">
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle
                            class="text-sm font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ t('criteres.titre_global') }}
                        </CardTitle>
                        <BoutonTooltip
                            :texte="
                                collapsed.criteres_global
                                    ? 'Développer'
                                    : 'Réduire'
                            "
                            @click="toggleSection('criteres_global')"
                        >
                            <ChevronUp
                                v-if="!collapsed.criteres_global"
                                class="h-4 w-4"
                            />
                            <ChevronDown v-else class="h-4 w-4" />
                        </BoutonTooltip>
                    </CardHeader>
                    <CardContent
                        v-show="!collapsed.criteres_global"
                        class="space-y-1.5 pt-0 pb-4"
                    >
                        <template
                            v-for="critere in criteresGlobaux"
                            :key="critere.id"
                        >
                            <CritereCorrection
                                v-if="estEnseignant"
                                :cours-id="classe.cours_id"
                                :classe-id="classe.id"
                                :groupe-id="groupe.id"
                                :type-projet-id="typeProjet.id"
                                :critere="critere"
                                :corrections="
                                    correctionsLocales[critere.id] ?? []
                                "
                                :membres="membres"
                                @updated="
                                    (nouvelles) =>
                                        onCorrectionsUpdated(
                                            critere.id,
                                            nouvelles,
                                        )
                                "
                            />
                            <CritereEtudiant
                                v-else
                                :critere="critere"
                                :correction="correctionEffective(critere.id)"
                                :correction-visible="correctionVisible"
                                :est-coche="cochesLocales.has(critere.id)"
                                :peut-cocher="estMembre"
                                :route-args="routeArgsCritere"
                                @updated-coche="
                                    (cochee) =>
                                        onCocheUpdated(critere.id, cochee)
                                "
                            />
                        </template>
                    </CardContent>
                </Card>

                <!-- ─── Sections dynamiques (définies par le professeur) ─────── -->
                <template v-if="props.sections.length > 0">
                    <template
                        v-for="section in props.sections"
                        :key="section.id"
                    >
                        <!-- ── Type texte : zone de rédaction unique ─────────────── -->
                        <Card v-if="section.type === 'texte'">
                            <CardHeader
                                class="flex flex-row items-center justify-between"
                            >
                                <CardTitle class="flex flex-col gap-0.5">
                                    <span class="text-base font-semibold">{{
                                        section.label
                                    }}</span>
                                    <span
                                        v-if="section.description"
                                        class="text-xs font-normal text-muted-foreground"
                                    >
                                        {{ section.description }}
                                    </span>
                                </CardTitle>
                                <div class="flex items-center gap-1">
                                    <BoutonTooltip
                                        :texte="
                                            collapsed[`section_${section.id}`]
                                                ? 'Développer'
                                                : 'Réduire'
                                        "
                                        @click="
                                            toggleSection(
                                                `section_${section.id}`,
                                            )
                                        "
                                    >
                                        <ChevronUp
                                            v-if="
                                                !collapsed[
                                                    `section_${section.id}`
                                                ]
                                            "
                                            class="h-4 w-4"
                                        />
                                        <ChevronDown v-else class="h-4 w-4" />
                                    </BoutonTooltip>
                                </div>
                            </CardHeader>
                            <CardContent
                                v-show="!collapsed[`section_${section.id}`]"
                                class="space-y-4"
                            >
                                <RichEditor
                                    v-model="sectionContenus[section.id]"
                                    :placeholder="`Rédigez la section « ${section.label} »…`"
                                    :read-only="!peutEditer"
                                    :est-enseignant="estEnseignant"
                                    :corrections="
                                        annotations[`section_${section.id}`] ??
                                        []
                                    "
                                    :renvois="renvoisLocaux"
                                    :renvois-sync-version="renvoisSyncVersion"
                                    :editor-id="`section-${section.id}`"
                                    :membres="membres"
                                    @update:model-value="
                                        scheduleSectionSave(section.id)
                                    "
                                    @save-annotation="
                                        (p) =>
                                            sauvegarderAnnotation(
                                                `section_${section.id}`,
                                                p,
                                            )
                                    "
                                    @delete-annotation="
                                        (p) =>
                                            supprimerAnnotation(
                                                `section_${section.id}`,
                                                p,
                                            )
                                    "
                                    @demander-renvoi="demanderRenvoi"
                                    @renvois-utilises="handleRenvoisUtilises"
                                />
                                <CommentaireEnseignant
                                    :commentaire="
                                        commentaires[`section_${section.id}`]
                                    "
                                    :brouillon="
                                        getBrouillon(`section_${section.id}`)
                                    "
                                    :est-reduit="
                                        !!commentairesReduits[
                                            `section_${section.id}`
                                        ]
                                    "
                                    :is-saving="
                                        !!commentairesSaving[
                                            `section_${section.id}`
                                        ]
                                    "
                                    :est-enseignant="estEnseignant"
                                    class="mt-3"
                                    @toggle="
                                        toggleCommentaire(
                                            `section_${section.id}`,
                                        )
                                    "
                                    @save="
                                        sauvegarderCommentaire(
                                            `section_${section.id}`,
                                        )
                                    "
                                    @delete="
                                        supprimerCommentaire(
                                            `section_${section.id}`,
                                        )
                                    "
                                    @update:brouillon="
                                        (v) =>
                                            setBrouillon(
                                                `section_${section.id}`,
                                                v,
                                            )
                                    "
                                />
                            </CardContent>
                        </Card>

                        <!-- ── Type paragraphes : N sous-paragraphes ajoutables ───── -->
                        <template v-else-if="section.type === 'paragraphes'">
                            <Card
                                v-for="para in sectionParagraphesLocaux[
                                    section.id
                                ] ?? []"
                                :key="para.id"
                            >
                                <CardHeader
                                    class="flex flex-row items-center justify-between"
                                >
                                    <CardTitle class="flex items-center gap-2">
                                        <span
                                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary"
                                        >
                                            {{ para.ordre }}
                                        </span>
                                        <span
                                            class="text-sm font-normal text-muted-foreground italic"
                                        >
                                            {{
                                                para.titre ||
                                                `${section.label} — paragraphe ${para.ordre}`
                                            }}
                                        </span>
                                    </CardTitle>
                                    <div class="flex items-center gap-1">
                                        <BoutonTooltip
                                            v-if="
                                                peutEditer &&
                                                (sectionParagraphesLocaux[
                                                    section.id
                                                ]?.length ?? 0) > 1
                                            "
                                            texte="Supprimer ce paragraphe"
                                            size="icon-sm"
                                            class="text-destructive"
                                            :disabled="
                                                !!sectionParagrapheEnCours[
                                                    section.id
                                                ]
                                            "
                                            @click="
                                                supprimerSectionParagraphe(
                                                    para.id,
                                                    section.id,
                                                )
                                            "
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </BoutonTooltip>
                                        <BoutonTooltip
                                            :texte="
                                                collapsedDev[para.id]
                                                    ? 'Développer'
                                                    : 'Réduire'
                                            "
                                            @click="toggleDev(para.id)"
                                        >
                                            <ChevronUp
                                                v-if="!collapsedDev[para.id]"
                                                class="h-4 w-4"
                                            />
                                            <ChevronDown
                                                v-else
                                                class="h-4 w-4"
                                            />
                                        </BoutonTooltip>
                                    </div>
                                </CardHeader>
                                <CardContent
                                    v-show="!collapsedDev[para.id]"
                                    class="space-y-2"
                                >
                                    <div v-if="peutEditer" class="mb-1">
                                        <Label
                                            class="text-xs text-muted-foreground"
                                            >Titre du paragraphe</Label
                                        >
                                        <Input
                                            :model-value="para.titre ?? ''"
                                            :placeholder="`Titre du paragraphe ${para.ordre}`"
                                            class="mt-1"
                                            @update:model-value="
                                                (val: string) => {
                                                    para.titre = val;
                                                    scheduleSectionParagrapheSave(
                                                        para.id,
                                                        section.id,
                                                    );
                                                }
                                            "
                                        />
                                    </div>
                                    <RichEditor
                                        :model-value="para.contenu ?? ''"
                                        :placeholder="`Rédigez le contenu du paragraphe ${para.ordre}…`"
                                        :read-only="!peutEditer"
                                        :est-enseignant="estEnseignant"
                                        :corrections="
                                            annotations[
                                                `section_paragraphe_${para.id}`
                                            ] ?? []
                                        "
                                        :renvois="renvoisLocaux"
                                        :renvois-sync-version="
                                            renvoisSyncVersion
                                        "
                                        :editor-id="`section-paragraphe-${para.id}`"
                                        :membres="membres"
                                        @update:model-value="
                                            (val: string) => {
                                                para.contenu = val;
                                                scheduleSectionParagrapheSave(
                                                    para.id,
                                                    section.id,
                                                );
                                            }
                                        "
                                        @save-annotation="
                                            (p) =>
                                                sauvegarderAnnotation(
                                                    `section_paragraphe_${para.id}`,
                                                    p,
                                                )
                                        "
                                        @delete-annotation="
                                            (p) =>
                                                supprimerAnnotation(
                                                    `section_paragraphe_${para.id}`,
                                                    p,
                                                )
                                        "
                                        @demander-renvoi="demanderRenvoi"
                                        @renvois-utilises="
                                            handleRenvoisUtilises
                                        "
                                    />
                                    <CommentaireEnseignant
                                        :commentaire="
                                            commentaires[
                                                `section_paragraphe_${para.id}`
                                            ]
                                        "
                                        :brouillon="
                                            getBrouillon(
                                                `section_paragraphe_${para.id}`,
                                            )
                                        "
                                        :est-reduit="
                                            !!commentairesReduits[
                                                `section_paragraphe_${para.id}`
                                            ]
                                        "
                                        :is-saving="
                                            !!commentairesSaving[
                                                `section_paragraphe_${para.id}`
                                            ]
                                        "
                                        :est-enseignant="estEnseignant"
                                        class="mt-3"
                                        @toggle="
                                            toggleCommentaire(
                                                `section_paragraphe_${para.id}`,
                                            )
                                        "
                                        @save="
                                            sauvegarderCommentaire(
                                                `section_paragraphe_${para.id}`,
                                            )
                                        "
                                        @delete="
                                            supprimerCommentaire(
                                                `section_paragraphe_${para.id}`,
                                            )
                                        "
                                        @update:brouillon="
                                            (v) =>
                                                setBrouillon(
                                                    `section_paragraphe_${para.id}`,
                                                    v,
                                                )
                                        "
                                    />
                                </CardContent>
                            </Card>

                            <div v-if="peutEditer" class="flex justify-center">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="
                                        !!sectionParagrapheEnCours[section.id]
                                    "
                                    @click="
                                        ajouterSectionParagraphe(section.id)
                                    "
                                >
                                    <Loader2
                                        v-if="
                                            sectionParagrapheEnCours[section.id]
                                        "
                                        class="mr-2 h-4 w-4 animate-spin"
                                    />
                                    <Plus v-else class="mr-2 h-4 w-4" />
                                    Ajouter un paragraphe — {{ section.label }}
                                </Button>
                            </div>
                        </template>

                        <!-- ── Type individuel : 1 zone par membre du groupe ─────── -->
                        <template v-else-if="section.type === 'individuel'">
                            <Card
                                v-for="membre in props.membres"
                                :key="membre.id"
                            >
                                <CardHeader
                                    class="flex flex-row items-center justify-between"
                                >
                                    <CardTitle class="flex items-center gap-2">
                                        <span
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary"
                                        >
                                            {{ membre.prenom[0]
                                            }}{{ membre.nom[0] }}
                                        </span>
                                        {{ section.label }} —
                                        {{ membre.prenom }}
                                        {{ membre.nom }}
                                    </CardTitle>
                                    <BoutonTooltip
                                        :texte="
                                            collapsedConclusion[
                                                section.id * 10000 + membre.id
                                            ]
                                                ? 'Développer'
                                                : 'Réduire'
                                        "
                                        @click="
                                            toggleConclusion(
                                                section.id * 10000 + membre.id,
                                            )
                                        "
                                    >
                                        <ChevronUp
                                            v-if="
                                                !collapsedConclusion[
                                                    section.id * 10000 +
                                                        membre.id
                                                ]
                                            "
                                            class="h-4 w-4"
                                        />
                                        <ChevronDown v-else class="h-4 w-4" />
                                    </BoutonTooltip>
                                </CardHeader>
                                <CardContent
                                    v-show="
                                        !collapsedConclusion[
                                            section.id * 10000 + membre.id
                                        ]
                                    "
                                >
                                    <template v-if="peutEditer">
                                        <p
                                            class="mb-2 text-xs text-muted-foreground"
                                        >
                                            N'importe quel membre peut rédiger
                                            la partie d'un autre.
                                        </p>
                                        <RichEditor
                                            :model-value="
                                                sectionConclusionsLocales[
                                                    section.id
                                                ]?.[membre.id] ?? ''
                                            "
                                            placeholder="Rédigez votre partie…"
                                            :est-enseignant="estEnseignant"
                                            :renvois="renvoisLocaux"
                                            :renvois-sync-version="
                                                renvoisSyncVersion
                                            "
                                            :editor-id="`section-conclusion-${section.id}-${membre.id}`"
                                            @update:model-value="
                                                (val: string) => {
                                                    if (
                                                        !sectionConclusionsLocales[
                                                            section.id
                                                        ]
                                                    )
                                                        sectionConclusionsLocales[
                                                            section.id
                                                        ] = {};
                                                    sectionConclusionsLocales[
                                                        section.id
                                                    ][membre.id] = val;
                                                    scheduleSectionConclusionSave(
                                                        section.id,
                                                        membre.id,
                                                    );
                                                }
                                            "
                                            @demander-renvoi="demanderRenvoi"
                                            @renvois-utilises="
                                                handleRenvoisUtilises
                                            "
                                        />
                                    </template>
                                    <template v-else>
                                        <RichEditor
                                            :model-value="
                                                sectionConclusionsLocales[
                                                    section.id
                                                ]?.[membre.id] ?? ''
                                            "
                                            :read-only="true"
                                            :est-enseignant="estEnseignant"
                                            placeholder="Non rédigé"
                                        />
                                    </template>
                                    <CommentaireEnseignant
                                        :commentaire="
                                            commentaires[
                                                `section_individuel_${section.id}_${membre.id}`
                                            ]
                                        "
                                        :brouillon="
                                            getBrouillon(
                                                `section_individuel_${section.id}_${membre.id}`,
                                            )
                                        "
                                        :est-reduit="
                                            !!commentairesReduits[
                                                `section_individuel_${section.id}_${membre.id}`
                                            ]
                                        "
                                        :is-saving="
                                            !!commentairesSaving[
                                                `section_individuel_${section.id}_${membre.id}`
                                            ]
                                        "
                                        :est-enseignant="estEnseignant"
                                        class="mt-3"
                                        @toggle="
                                            toggleCommentaire(
                                                `section_individuel_${section.id}_${membre.id}`,
                                            )
                                        "
                                        @save="
                                            sauvegarderCommentaire(
                                                `section_individuel_${section.id}_${membre.id}`,
                                            )
                                        "
                                        @delete="
                                            supprimerCommentaire(
                                                `section_individuel_${section.id}_${membre.id}`,
                                            )
                                        "
                                        @update:brouillon="
                                            (v) =>
                                                setBrouillon(
                                                    `section_individuel_${section.id}_${membre.id}`,
                                                    v,
                                                )
                                        "
                                    />
                                </CardContent>
                            </Card>
                        </template>

                        <!-- ── Type entrevue DEP/Complet : concepts + tableau dim/indic/questions ── -->
                        <template
                            v-else-if="
                                section.type === 'entrevue' &&
                                cours.type_cours !== 'cours_complementaire'
                            "
                        >
                            <!-- Compteur de questions -->
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">
                                    Questions renseignées :
                                    <span
                                        :class="
                                            totalQuestions(section.id) >= 10
                                                ? 'font-semibold text-green-600'
                                                : 'font-semibold text-amber-500'
                                        "
                                    >
                                        {{ totalQuestions(section.id) }}
                                    </span>
                                    <span class="text-muted-foreground">
                                        / 10 min.</span
                                    >
                                </span>
                            </div>

                            <!-- Liste des concepts -->
                            <Card
                                v-for="concept in sectionConceptsLocaux[
                                    section.id
                                ] ?? []"
                                :key="concept.id"
                            >
                                <CardHeader
                                    class="group flex flex-row items-start justify-between gap-2 pb-2"
                                >
                                    <div class="flex flex-1 items-center gap-2">
                                        <span
                                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary"
                                        >
                                            {{ concept.ordre }}
                                        </span>
                                        <input
                                            v-if="peutEditer"
                                            :value="concept.label"
                                            class="flex-1 rounded border-0 bg-transparent text-sm font-medium outline-none focus:ring-1 focus:ring-ring"
                                            placeholder="Nom du concept…"
                                            @input="
                                                (e) => {
                                                    concept.label = (
                                                        e.target as HTMLInputElement
                                                    ).value;
                                                    scheduleConceptLabelSave(
                                                        concept.id,
                                                        section.id,
                                                    );
                                                }
                                            "
                                        />
                                        <span
                                            v-else
                                            class="text-sm font-medium"
                                            >{{ concept.label }}</span
                                        >
                                    </div>
                                    <BoutonTooltip
                                        v-if="peutEditer"
                                        texte="Supprimer ce concept"
                                        class="size-7 shrink-0 text-destructive opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100"
                                        :disabled="!!conceptEnCours[section.id]"
                                        @click="
                                            supprimerConcept(
                                                concept.id,
                                                section.id,
                                            )
                                        "
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </BoutonTooltip>
                                </CardHeader>

                                <CardContent class="pt-0">
                                    <!-- Tableau lignes -->
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr
                                                class="border-b text-xs text-muted-foreground"
                                            >
                                                <th
                                                    class="pr-2 pb-1 text-left font-medium"
                                                >
                                                    Dimension
                                                </th>
                                                <th
                                                    class="pr-2 pb-1 text-left font-medium"
                                                >
                                                    Indicateur
                                                </th>
                                                <th
                                                    class="pb-1 text-left font-medium"
                                                >
                                                    Questions spécifiques
                                                </th>
                                                <th
                                                    v-if="peutEditer"
                                                    class="w-8"
                                                />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="ligne in concept.lignes"
                                                :key="ligne.id"
                                                class="group align-top"
                                            >
                                                <!-- Dimension -->
                                                <td class="py-1 pr-2 align-top">
                                                    <textarea
                                                        v-if="peutEditer"
                                                        :value="
                                                            ligne.dimension ??
                                                            ''
                                                        "
                                                        rows="2"
                                                        class="w-full resize-none rounded border border-input bg-transparent p-1 text-sm outline-none focus:ring-1 focus:ring-ring"
                                                        placeholder="Dimension…"
                                                        @input="
                                                            (e) => {
                                                                ligne.dimension =
                                                                    (
                                                                        e.target as HTMLTextAreaElement
                                                                    ).value;
                                                                scheduleLigneSave(
                                                                    ligne.id,
                                                                    concept.id,
                                                                    section.id,
                                                                );
                                                            }
                                                        "
                                                    />
                                                    <span
                                                        v-else
                                                        class="block whitespace-pre-wrap"
                                                        >{{
                                                            ligne.dimension
                                                        }}</span
                                                    >
                                                </td>
                                                <!-- Indicateur -->
                                                <td class="py-1 pr-2 align-top">
                                                    <textarea
                                                        v-if="peutEditer"
                                                        :value="
                                                            ligne.indicateur ??
                                                            ''
                                                        "
                                                        rows="2"
                                                        class="w-full resize-none rounded border border-input bg-transparent p-1 text-sm outline-none focus:ring-1 focus:ring-ring"
                                                        placeholder="Indicateur…"
                                                        @input="
                                                            (e) => {
                                                                ligne.indicateur =
                                                                    (
                                                                        e.target as HTMLTextAreaElement
                                                                    ).value;
                                                                scheduleLigneSave(
                                                                    ligne.id,
                                                                    concept.id,
                                                                    section.id,
                                                                );
                                                            }
                                                        "
                                                    />
                                                    <span
                                                        v-else
                                                        class="block whitespace-pre-wrap"
                                                        >{{
                                                            ligne.indicateur
                                                        }}</span
                                                    >
                                                </td>
                                                <!-- Questions -->
                                                <td class="py-1 align-top">
                                                    <div class="space-y-1">
                                                        <div
                                                            v-for="(
                                                                q, qi
                                                            ) in ligne.questions"
                                                            :key="qi"
                                                            class="group/q flex items-start gap-1"
                                                        >
                                                            <span
                                                                class="mt-1.5 shrink-0 text-xs text-muted-foreground"
                                                                >{{
                                                                    qi + 1
                                                                }}.</span
                                                            >
                                                            <input
                                                                v-if="
                                                                    peutEditer
                                                                "
                                                                :value="q"
                                                                class="flex-1 rounded border border-input bg-transparent p-1 text-sm outline-none focus:ring-1 focus:ring-ring"
                                                                placeholder="Question…"
                                                                @input="
                                                                    (e) => {
                                                                        ligne.questions[
                                                                            qi
                                                                        ] = (
                                                                            e.target as HTMLInputElement
                                                                        ).value;
                                                                        scheduleLigneSave(
                                                                            ligne.id,
                                                                            concept.id,
                                                                            section.id,
                                                                        );
                                                                    }
                                                                "
                                                            />
                                                            <span
                                                                v-else
                                                                class="flex-1"
                                                                >{{ q }}</span
                                                            >
                                                            <BoutonTooltip
                                                                v-if="
                                                                    peutEditer
                                                                "
                                                                texte="Supprimer cette question"
                                                                class="size-6 shrink-0 text-destructive opacity-0 transition-opacity group-focus-within/q:opacity-100 group-hover/q:opacity-100"
                                                                @click="
                                                                    supprimerQuestion(
                                                                        ligne,
                                                                        qi,
                                                                        concept.id,
                                                                        section.id,
                                                                    )
                                                                "
                                                            >
                                                                <Trash2
                                                                    class="h-3 w-3"
                                                                />
                                                            </BoutonTooltip>
                                                        </div>
                                                        <Button
                                                            v-if="peutEditer"
                                                            variant="ghost"
                                                            size="sm"
                                                            class="h-6 gap-1 text-xs"
                                                            @click="
                                                                ajouterQuestion(
                                                                    ligne,
                                                                    concept.id,
                                                                    section.id,
                                                                )
                                                            "
                                                        >
                                                            <Plus
                                                                class="h-3 w-3"
                                                            />
                                                            Ajouter une question
                                                        </Button>
                                                        <p
                                                            v-else-if="
                                                                !ligne.questions
                                                                    ?.length
                                                            "
                                                            class="text-xs text-muted-foreground italic"
                                                        >
                                                            Aucune question
                                                        </p>
                                                    </div>
                                                </td>
                                                <!-- Supprimer ligne -->
                                                <td
                                                    v-if="peutEditer"
                                                    class="py-1 pl-1 align-top"
                                                >
                                                    <BoutonTooltip
                                                        texte="Supprimer cette ligne"
                                                        class="size-7 text-muted-foreground opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100 hover:text-destructive"
                                                        @click="
                                                            supprimerLigne(
                                                                ligne.id,
                                                                concept.id,
                                                                section.id,
                                                            )
                                                        "
                                                    >
                                                        <Trash2
                                                            class="h-3.5 w-3.5"
                                                        />
                                                    </BoutonTooltip>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <!-- Bouton ajouter ligne -->
                                    <Button
                                        v-if="peutEditer"
                                        variant="outline"
                                        size="sm"
                                        class="mt-2 gap-1 text-xs"
                                        @click="
                                            ajouterLigne(concept.id, section.id)
                                        "
                                    >
                                        <Plus class="h-3 w-3" />
                                        Ajouter une ligne
                                    </Button>
                                </CardContent>
                            </Card>

                            <!-- Bouton ajouter concept -->
                            <div v-if="peutEditer" class="flex justify-center">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="!!conceptEnCours[section.id]"
                                    @click="ajouterConcept(section.id)"
                                >
                                    <Loader2
                                        v-if="conceptEnCours[section.id]"
                                        class="mr-2 h-4 w-4 animate-spin"
                                    />
                                    <Plus v-else class="mr-2 h-4 w-4" />
                                    Ajouter un concept — {{ section.label }}
                                </Button>
                            </div>
                        </template>

                        <!-- ── Type vidéo : lecteur + upload de vidéos ───────────── -->
                        <Card v-else-if="section.type === 'video'">
                            <CardHeader>
                                <CardTitle class="flex flex-col gap-0.5">
                                    <span class="text-base font-semibold">{{
                                        section.label
                                    }}</span>
                                    <span
                                        v-if="section.description"
                                        class="text-sm font-normal text-muted-foreground"
                                    >
                                        {{ section.description }}
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <SectionVideo
                                    :params="{
                                        cours: classe.cours_id,
                                        classe: classe.id,
                                        groupe: groupe.id,
                                        typeProjet: typeProjet.id,
                                        section: section.id,
                                    }"
                                    :medias="section.medias ?? []"
                                    :readonly="!peutEditer"
                                />
                            </CardContent>
                        </Card>

                        <!-- ── Type entrevue CC : question principale + sous-questions ─ -->
                        <Card
                            v-else-if="
                                section.type === 'entrevue' &&
                                cours.type_cours === 'cours_complementaire'
                            "
                        >
                            <CardHeader>
                                <CardTitle class="text-base font-semibold">{{
                                    section.label
                                }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <SectionEntrevueCC
                                    :params="{
                                        cours: classe.cours_id,
                                        classe: classe.id,
                                        groupe: groupe.id,
                                        typeProjet: typeProjet.id,
                                        section: section.id,
                                    }"
                                    :concepts="section.concepts ?? []"
                                    :readonly="!peutEditer"
                                />
                            </CardContent>
                        </Card>

                        <!-- ── Type audio : lecteur + upload d'audios ────────────── -->
                        <Card v-else-if="section.type === 'audio'">
                            <CardHeader>
                                <CardTitle class="flex flex-col gap-0.5">
                                    <span class="text-base font-semibold">{{
                                        section.label
                                    }}</span>
                                    <span
                                        v-if="section.description"
                                        class="text-sm font-normal text-muted-foreground"
                                    >
                                        {{ section.description }}
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <SectionAudio
                                    :params="{
                                        cours: classe.cours_id,
                                        classe: classe.id,
                                        groupe: groupe.id,
                                        typeProjet: typeProjet.id,
                                        section: section.id,
                                    }"
                                    :medias="section.medias ?? []"
                                    :readonly="!peutEditer"
                                />
                            </CardContent>
                        </Card>

                        <!-- ── Type choix_questions : sélection de questions dans la banque ─ -->
                        <Card v-else-if="section.type === 'choix_questions'">
                            <CardHeader>
                                <CardTitle class="flex flex-col gap-0.5">
                                    <span class="text-base font-semibold">{{
                                        section.label
                                    }}</span>
                                    <span
                                        v-if="section.description"
                                        class="text-sm font-normal text-muted-foreground"
                                    >
                                        {{ section.description }}
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <SectionChoixQuestions
                                    :params="{
                                        cours: classe.cours_id,
                                        classe: classe.id,
                                        groupe: groupe.id,
                                        typeProjet: typeProjet.id,
                                        section: section.id,
                                    }"
                                    :questions="section.questions ?? []"
                                    :questions-choisies="
                                        section.questionsChoisies ?? []
                                    "
                                    :readonly="!peutEditer"
                                />
                            </CardContent>
                        </Card>

                        <!-- ── Type tache : liste de tâches assignables ───────────── -->
                        <Card v-else-if="section.type === 'tache'">
                            <CardHeader>
                                <CardTitle class="flex flex-col gap-0.5">
                                    <span class="text-base font-semibold">{{
                                        section.label
                                    }}</span>
                                    <span
                                        v-if="section.description"
                                        class="text-sm font-normal text-muted-foreground"
                                    >
                                        {{ section.description }}
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <SectionTache
                                    :params="{
                                        cours: classe.cours_id,
                                        classe: classe.id,
                                        groupe: groupe.id,
                                        typeProjet: typeProjet.id,
                                    }"
                                    :taches="section.taches ?? []"
                                    :membres="membres"
                                    :readonly="!peutEditer"
                                />
                            </CardContent>
                        </Card>

                        <!-- ── Type schema_visuel : schéma DEP drag-and-drop ─────── -->
                        <Card v-else-if="section.type === 'schema_visuel'">
                            <CardHeader>
                                <CardTitle class="flex flex-col gap-0.5">
                                    <span class="text-base font-semibold">{{
                                        section.label
                                    }}</span>
                                    <span
                                        v-if="section.description"
                                        class="text-sm font-normal text-muted-foreground"
                                    >
                                        {{ section.description }}
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <SectionSchemaVisuel
                                    :params="{
                                        cours: classe.cours_id,
                                        classe: classe.id,
                                        groupe: groupe.id,
                                        typeProjet: typeProjet.id,
                                        section: section.id,
                                    }"
                                    :schema-visuel="section.schemaVisuel"
                                    :read-only="!peutEditer"
                                />
                            </CardContent>
                        </Card>

                        <!-- ── Critères de correction de cette section ──────────── -->
                        <Card v-if="section.criteres?.length">
                            <CardHeader
                                class="flex flex-row items-center justify-between"
                            >
                                <CardTitle
                                    class="text-sm font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ t('criteres.titre_section') }}
                                </CardTitle>
                                <BoutonTooltip
                                    :texte="
                                        isCriteresSectionCollapsed(section.id)
                                            ? 'Développer'
                                            : 'Réduire'
                                    "
                                    @click="
                                        toggleSection(
                                            `criteres_section_${section.id}`,
                                        )
                                    "
                                >
                                    <ChevronUp
                                        v-if="
                                            !isCriteresSectionCollapsed(
                                                section.id,
                                            )
                                        "
                                        class="h-4 w-4"
                                    />
                                    <ChevronDown v-else class="h-4 w-4" />
                                </BoutonTooltip>
                            </CardHeader>
                            <CardContent
                                v-show="!isCriteresSectionCollapsed(section.id)"
                                class="space-y-1.5 pt-0 pb-4"
                            >
                                <template
                                    v-for="critere in section.criteres ?? []"
                                    :key="critere.id"
                                >
                                    <CritereCorrection
                                        v-if="estEnseignant"
                                        :cours-id="classe.cours_id"
                                        :classe-id="classe.id"
                                        :groupe-id="groupe.id"
                                        :type-projet-id="typeProjet.id"
                                        :critere="critere"
                                        :corrections="
                                            correctionsLocales[critere.id] ?? []
                                        "
                                        :membres="membres"
                                        @updated="
                                            (nouvelles) =>
                                                onCorrectionsUpdated(
                                                    critere.id,
                                                    nouvelles,
                                                )
                                        "
                                    />
                                    <CritereEtudiant
                                        v-else
                                        :critere="critere"
                                        :correction="
                                            correctionEffective(critere.id)
                                        "
                                        :correction-visible="correctionVisible"
                                        :est-coche="
                                            cochesLocales.has(critere.id)
                                        "
                                        :peut-cocher="estMembre"
                                        :route-args="routeArgsCritere"
                                        @updated-coche="
                                            (cochee) =>
                                                onCocheUpdated(
                                                    critere.id,
                                                    cochee,
                                                )
                                        "
                                    />
                                </template>
                            </CardContent>
                        </Card>
                    </template>
                </template>

                <!-- ─── Introduction (fallback si aucune section définie) ──────── -->
                <template v-else>
                    <!-- ─── Introduction ───────────────────────────────────────────── -->
                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between"
                        >
                            <CardTitle class="text-base font-semibold">{{
                                t('projets.show.introduction')
                            }}</CardTitle>
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
                        <CardContent
                            v-show="!collapsed.introduction"
                            class="space-y-4"
                        >
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
                                    {{
                                        tab.charAt(0).toUpperCase() +
                                        tab.slice(1)
                                    }}
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
                                    :corrections="
                                        annotations['introduction_amener'] ?? []
                                    "
                                    :renvois="renvoisLocaux"
                                    :renvois-sync-version="renvoisSyncVersion"
                                    editor-id="introduction-amener"
                                    :membres="membres"
                                    @save-annotation="
                                        (p) =>
                                            sauvegarderAnnotation(
                                                'introduction_amener',
                                                p,
                                            )
                                    "
                                    @delete-annotation="
                                        (p) =>
                                            supprimerAnnotation(
                                                'introduction_amener',
                                                p,
                                            )
                                    "
                                    @demander-renvoi="demanderRenvoi"
                                    @renvois-utilises="handleRenvoisUtilises"
                                />
                                <CommentaireEnseignant
                                    :commentaire="
                                        commentaires['introduction_amener']
                                    "
                                    :brouillon="
                                        getBrouillon('introduction_amener')
                                    "
                                    :est-reduit="
                                        !!commentairesReduits[
                                            'introduction_amener'
                                        ]
                                    "
                                    :is-saving="
                                        !!commentairesSaving[
                                            'introduction_amener'
                                        ]
                                    "
                                    :est-enseignant="estEnseignant"
                                    class="mt-3"
                                    @toggle="
                                        toggleCommentaire('introduction_amener')
                                    "
                                    @save="
                                        sauvegarderCommentaire(
                                            'introduction_amener',
                                        )
                                    "
                                    @delete="
                                        supprimerCommentaire(
                                            'introduction_amener',
                                        )
                                    "
                                    @update:brouillon="
                                        (v) =>
                                            setBrouillon(
                                                'introduction_amener',
                                                v,
                                            )
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
                                    :corrections="
                                        annotations['introduction_poser'] ?? []
                                    "
                                    :renvois="renvoisLocaux"
                                    :renvois-sync-version="renvoisSyncVersion"
                                    editor-id="introduction-poser"
                                    :membres="membres"
                                    @save-annotation="
                                        (p) =>
                                            sauvegarderAnnotation(
                                                'introduction_poser',
                                                p,
                                            )
                                    "
                                    @delete-annotation="
                                        (p) =>
                                            supprimerAnnotation(
                                                'introduction_poser',
                                                p,
                                            )
                                    "
                                    @demander-renvoi="demanderRenvoi"
                                    @renvois-utilises="handleRenvoisUtilises"
                                />
                                <CommentaireEnseignant
                                    :commentaire="
                                        commentaires['introduction_poser']
                                    "
                                    :brouillon="
                                        getBrouillon('introduction_poser')
                                    "
                                    :est-reduit="
                                        !!commentairesReduits[
                                            'introduction_poser'
                                        ]
                                    "
                                    :is-saving="
                                        !!commentairesSaving[
                                            'introduction_poser'
                                        ]
                                    "
                                    :est-enseignant="estEnseignant"
                                    class="mt-3"
                                    @toggle="
                                        toggleCommentaire('introduction_poser')
                                    "
                                    @save="
                                        sauvegarderCommentaire(
                                            'introduction_poser',
                                        )
                                    "
                                    @delete="
                                        supprimerCommentaire(
                                            'introduction_poser',
                                        )
                                    "
                                    @update:brouillon="
                                        (v) =>
                                            setBrouillon(
                                                'introduction_poser',
                                                v,
                                            )
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
                                    :corrections="
                                        annotations['introduction_diviser'] ??
                                        []
                                    "
                                    :renvois="renvoisLocaux"
                                    :renvois-sync-version="renvoisSyncVersion"
                                    editor-id="introduction-diviser"
                                    :membres="membres"
                                    @save-annotation="
                                        (p) =>
                                            sauvegarderAnnotation(
                                                'introduction_diviser',
                                                p,
                                            )
                                    "
                                    @delete-annotation="
                                        (p) =>
                                            supprimerAnnotation(
                                                'introduction_diviser',
                                                p,
                                            )
                                    "
                                    @demander-renvoi="demanderRenvoi"
                                    @renvois-utilises="handleRenvoisUtilises"
                                />
                                <CommentaireEnseignant
                                    :commentaire="
                                        commentaires['introduction_diviser']
                                    "
                                    :brouillon="
                                        getBrouillon('introduction_diviser')
                                    "
                                    :est-reduit="
                                        !!commentairesReduits[
                                            'introduction_diviser'
                                        ]
                                    "
                                    :is-saving="
                                        !!commentairesSaving[
                                            'introduction_diviser'
                                        ]
                                    "
                                    :est-enseignant="estEnseignant"
                                    class="mt-3"
                                    @toggle="
                                        toggleCommentaire(
                                            'introduction_diviser',
                                        )
                                    "
                                    @save="
                                        sauvegarderCommentaire(
                                            'introduction_diviser',
                                        )
                                    "
                                    @delete="
                                        supprimerCommentaire(
                                            'introduction_diviser',
                                        )
                                    "
                                    @update:brouillon="
                                        (v) =>
                                            setBrouillon(
                                                'introduction_diviser',
                                                v,
                                            )
                                    "
                                />
                            </div>
                        </CardContent>
                    </Card> </template
                ><!-- /v-else intro fallback -->

                <!-- ─── Paragraphes de développement (legacy — masqué si sections dynamiques) ── -->
                <template v-if="sections.length === 0">
                    <Card v-for="dev in developpements" :key="dev.id">
                        <CardHeader
                            class="flex flex-row items-center justify-between"
                        >
                            <CardTitle class="flex items-center gap-2">
                                <span
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary"
                                    >{{ dev.ordre }}</span
                                >
                                <span
                                    class="text-sm font-normal text-muted-foreground italic"
                                >
                                    {{
                                        dev.titre ||
                                        t('projets.show.dev_paragraph', {
                                            n: dev.ordre,
                                        })
                                    }}
                                </span>
                            </CardTitle>
                            <div class="flex items-center gap-1">
                                <BoutonTooltip
                                    v-if="
                                        peutEditer && developpements.length > 1
                                    "
                                    :texte="t('projets.show.delete_paragraph')"
                                    size="icon-sm"
                                    class="text-destructive"
                                    :disabled="devEnCours"
                                    @click="supprimerDev(dev.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </BoutonTooltip>
                                <BoutonTooltip
                                    :texte="
                                        collapsedDev[dev.id]
                                            ? 'Développer'
                                            : 'Réduire'
                                    "
                                    @click="toggleDev(dev.id)"
                                >
                                    <ChevronUp
                                        v-if="!collapsedDev[dev.id]"
                                        class="h-4 w-4"
                                    />
                                    <ChevronDown v-else class="h-4 w-4" />
                                </BoutonTooltip>
                            </div>
                        </CardHeader>
                        <CardContent
                            v-show="!collapsedDev[dev.id]"
                            class="space-y-2"
                        >
                            <div v-if="peutEditer" class="mb-1">
                                <Label class="text-xs text-muted-foreground">{{
                                    t('projets.show.paragraph_title_label')
                                }}</Label>
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
                                :corrections="
                                    annotations[`developpement_${dev.id}`] ?? []
                                "
                                :renvois="renvoisLocaux"
                                :renvois-sync-version="renvoisSyncVersion"
                                :editor-id="`developpement-${dev.id}`"
                                :membres="membres"
                                @update:model-value="
                                    (val: string) => {
                                        dev.contenu = val;
                                        scheduleDeveloppementSave(dev.id);
                                    }
                                "
                                @save-annotation="
                                    (p) =>
                                        sauvegarderAnnotation(
                                            `developpement_${dev.id}`,
                                            p,
                                        )
                                "
                                @delete-annotation="
                                    (p) =>
                                        supprimerAnnotation(
                                            `developpement_${dev.id}`,
                                            p,
                                        )
                                "
                                @demander-renvoi="demanderRenvoi"
                                @renvois-utilises="handleRenvoisUtilises"
                            />

                            <!-- Commentaire -->
                            <CommentaireEnseignant
                                :commentaire="
                                    commentaires[`developpement_${dev.id}`]
                                "
                                :brouillon="
                                    getBrouillon(`developpement_${dev.id}`)
                                "
                                :est-reduit="
                                    !!commentairesReduits[
                                        `developpement_${dev.id}`
                                    ]
                                "
                                :is-saving="
                                    !!commentairesSaving[
                                        `developpement_${dev.id}`
                                    ]
                                "
                                :est-enseignant="estEnseignant"
                                class="mt-3"
                                @toggle="
                                    toggleCommentaire(`developpement_${dev.id}`)
                                "
                                @save="
                                    sauvegarderCommentaire(
                                        `developpement_${dev.id}`,
                                    )
                                "
                                @delete="
                                    supprimerCommentaire(
                                        `developpement_${dev.id}`,
                                    )
                                "
                                @update:brouillon="
                                    (v) =>
                                        setBrouillon(
                                            `developpement_${dev.id}`,
                                            v,
                                        )
                                "
                            />
                        </CardContent>
                    </Card>

                    <!-- Bouton ajouter un paragraphe (pas de limite) -->
                    <div v-if="peutEditer" class="flex justify-center">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="devEnCours"
                            @click="ajouterDev"
                        >
                            <Loader2
                                v-if="devEnCours"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <Plus v-else class="mr-2 h-4 w-4" />
                            {{ t('projets.show.add_paragraph') }}
                        </Button>
                    </div> </template
                ><!-- /legacy développements -->

                <!-- ─── Conclusions individuelles (legacy — masqué si sections dynamiques) ── -->
                <template v-if="sections.length === 0">
                    <Card v-for="item in conclusions" :key="item.etudiant.id">
                        <CardHeader
                            class="flex flex-row items-center justify-between"
                        >
                            <CardTitle class="flex items-center gap-2">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary"
                                >
                                    {{ item.etudiant.prenom[0]
                                    }}{{ item.etudiant.nom[0] }}
                                </span>
                                {{
                                    t('projets.show.conclusion_member', {
                                        prenom: item.etudiant.prenom,
                                        nom: item.etudiant.nom,
                                    })
                                }}
                            </CardTitle>
                            <BoutonTooltip
                                :texte="
                                    collapsedConclusion[item.etudiant.id]
                                        ? 'Développer'
                                        : 'Réduire'
                                "
                                @click="toggleConclusion(item.etudiant.id)"
                            >
                                <ChevronUp
                                    v-if="
                                        !collapsedConclusion[item.etudiant.id]
                                    "
                                    class="h-4 w-4"
                                />
                                <ChevronDown v-else class="h-4 w-4" />
                            </BoutonTooltip>
                        </CardHeader>
                        <CardContent
                            v-show="!collapsedConclusion[item.etudiant.id]"
                        >
                            <template v-if="peutEditer">
                                <p class="mb-2 text-xs text-muted-foreground">
                                    {{ t('projets.show.conclusion_hint') }}
                                </p>
                                <RichEditor
                                    :model-value="
                                        conclusionsLocales[item.etudiant.id]
                                    "
                                    placeholder="Rédigez votre conclusion…"
                                    :est-enseignant="estEnseignant"
                                    :renvois="renvoisLocaux"
                                    :renvois-sync-version="renvoisSyncVersion"
                                    :editor-id="`conclusion-${item.etudiant.id}`"
                                    @update:model-value="
                                        (val: string) => {
                                            conclusionsLocales[
                                                item.etudiant.id
                                            ] = val;
                                            scheduleConclusionSave(
                                                item.etudiant.id,
                                            );
                                        }
                                    "
                                    @demander-renvoi="demanderRenvoi"
                                    @renvois-utilises="handleRenvoisUtilises"
                                />
                            </template>
                            <template v-else>
                                <RichEditor
                                    :model-value="
                                        conclusionsLocales[item.etudiant.id] ??
                                        ''
                                    "
                                    :read-only="true"
                                    :est-enseignant="estEnseignant"
                                    :placeholder="
                                        t('projets.show.section_not_written')
                                    "
                                />
                            </template>

                            <!-- Commentaire -->
                            <CommentaireEnseignant
                                :commentaire="
                                    commentaires[
                                        `conclusion_${item.etudiant.id}`
                                    ]
                                "
                                :brouillon="
                                    getBrouillon(
                                        `conclusion_${item.etudiant.id}`,
                                    )
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
                                    toggleCommentaire(
                                        `conclusion_${item.etudiant.id}`,
                                    )
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
                    </Card> </template
                ><!-- /legacy conclusions -->

                <!-- ─── Références (renvois / endnotes) ──────────────────────────── -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <BookmarkPlus class="h-4 w-4 text-primary" />
                            Références
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="renvoisLocaux.length === 0"
                            class="text-sm text-muted-foreground italic"
                        >
                            Aucune référence. Cliquez sur ¹ dans la barre d'un
                            éditeur pour en insérer une.
                        </p>
                        <ol v-else class="space-y-3">
                            <li
                                v-for="renvoi in renvoisLocaux"
                                :key="renvoi.id"
                                class="flex items-start gap-2"
                            >
                                <span
                                    class="mt-2 min-w-[1.5rem] text-right text-xs font-bold text-blue-600 dark:text-blue-400"
                                >
                                    {{ renvoi.numero }}.
                                </span>
                                <div class="flex-1 space-y-1.5">
                                    <RichEditor
                                        :model-value="
                                            renvoiContenuHtml(renvoi.contenu)
                                        "
                                        :read-only="!peutEditer"
                                        :est-enseignant="estEnseignant"
                                        :corrections="
                                            annotations[
                                                'renvoi_' + renvoi.id
                                            ] ?? []
                                        "
                                        :renvois="[]"
                                        :membres="membres"
                                        :editor-id="`renvoi-${renvoi.id}`"
                                        :compact="true"
                                        @update:model-value="
                                            (html: string) => {
                                                renvoi.contenu = html;
                                                scheduleRenvoiSave(renvoi.id);
                                            }
                                        "
                                        @save-annotation="
                                            (p) =>
                                                sauvegarderAnnotation(
                                                    'renvoi_' + renvoi.id,
                                                    p,
                                                )
                                        "
                                        @delete-annotation="
                                            (p) =>
                                                supprimerAnnotation(
                                                    'renvoi_' + renvoi.id,
                                                    p,
                                                )
                                        "
                                    />
                                    <!-- Commentaires enseignant sur ce renvoi -->
                                    <div
                                        v-if="
                                            estEnseignant ||
                                            (renvoi.commentaires?.length ?? 0) >
                                                0
                                        "
                                        class="space-y-1"
                                    >
                                        <div
                                            v-for="commentaire in renvoi.commentaires ??
                                            []"
                                            :key="commentaire.id"
                                            class="flex items-start gap-1.5 rounded border border-amber-200 bg-amber-50 px-2 py-1 dark:border-amber-800 dark:bg-amber-950/30"
                                        >
                                            <MessageSquare
                                                class="mt-0.5 h-3 w-3 shrink-0 text-amber-600 dark:text-amber-400"
                                            />
                                            <span
                                                class="flex-1 text-xs text-foreground"
                                                >{{ commentaire.contenu }}</span
                                            >
                                            <button
                                                v-if="estEnseignant"
                                                type="button"
                                                class="text-destructive hover:text-destructive/70"
                                                title="Supprimer ce commentaire"
                                                @click="
                                                    supprimerCommentaireRenvoi(
                                                        renvoi.id,
                                                        commentaire.id,
                                                    )
                                                "
                                            >
                                                <Trash2 class="h-3 w-3" />
                                            </button>
                                        </div>
                                        <!-- Saisie nouveau commentaire (enseignant seulement) -->
                                        <div
                                            v-if="estEnseignant"
                                            class="flex gap-1"
                                        >
                                            <input
                                                v-model="
                                                    renvoiNouveauCommentaire[
                                                        renvoi.id
                                                    ]
                                                "
                                                type="text"
                                                placeholder="Commenter cette référence…"
                                                class="flex-1 rounded border border-input bg-background px-2 py-0.5 text-xs focus:ring-1 focus:ring-ring focus:outline-none"
                                                @keydown.enter.prevent="
                                                    ajouterCommentaireRenvoi(
                                                        renvoi.id,
                                                    )
                                                "
                                            />
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-6 w-6 shrink-0"
                                                title="Envoyer"
                                                :disabled="
                                                    !renvoiNouveauCommentaire[
                                                        renvoi.id
                                                    ]?.trim() ||
                                                    renvoiCommentaireEnCours[
                                                        renvoi.id
                                                    ]
                                                "
                                                @click="
                                                    ajouterCommentaireRenvoi(
                                                        renvoi.id,
                                                    )
                                                "
                                            >
                                                <Send class="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <BoutonTooltip
                                    v-if="aideReference && peutEditer"
                                    texte="Modifier cette référence"
                                    class="mt-1 size-7 shrink-0"
                                    @click="ouvrirEditionRenvoi(renvoi)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </BoutonTooltip>
                                <BoutonTooltip
                                    v-if="peutEditer"
                                    texte="Supprimer cette référence"
                                    class="mt-1 size-7 shrink-0 text-destructive"
                                    @click="demanderSupprimerRenvoi(renvoi.id)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </BoutonTooltip>
                            </li>
                        </ol>
                    </CardContent>
                </Card>

                <!-- ─── Remise de travail ──────────────────────────────────────────── -->

                <!-- Panneau de configuration de la remise (enseignant — lecture seule) -->
                <Card v-if="estEnseignant">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <CalendarDays class="h-4 w-4 text-primary" />
                            {{ t('projets.show.submission_settings') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Paramètres (lecture seule — configurés au niveau du type de projet) -->
                        <div
                            class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
                        >
                            <div class="mb-2 flex flex-wrap gap-x-6 gap-y-1">
                                <span v-if="dateRemise">
                                    <CalendarDays
                                        class="mr-1 inline-block h-3.5 w-3.5"
                                    />
                                    {{ dateRemiseFormatee }}
                                </span>
                                <span v-else class="italic"
                                    >Aucune date limite définie.</span
                                >
                                <span
                                    v-if="remisesMultiples"
                                    class="text-green-600 dark:text-green-400"
                                    >Remises multiples ✓</span
                                >
                                <span
                                    v-if="retardPermis"
                                    class="text-amber-600 dark:text-amber-400"
                                    >Retard permis ✓</span
                                >
                            </div>
                            <Link
                                :href="
                                    editTypeProjet.url({
                                        cours: classe.cours_id,
                                        typeProjet: typeProjet.id,
                                    })
                                "
                                class="inline-flex items-center gap-1 text-xs text-primary underline-offset-2 hover:underline"
                            >
                                <Settings2 class="h-3 w-3" />
                                Modifier les paramètres dans le type de projet
                            </Link>
                        </div>
                        <!-- Annulation de remise si déjà soumis -->
                        <div
                            v-if="remisLe"
                            class="flex items-center justify-between gap-3"
                        >
                            <div class="text-sm text-muted-foreground">
                                <CheckCircle2
                                    class="mr-1 inline-block h-4 w-4 text-green-500"
                                />
                                {{ t('projets.show.submitted_on') }}
                                {{
                                    new Date(remisLe).toLocaleDateString(
                                        'fr-CA',
                                        {
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        },
                                    )
                                }}
                            </div>
                            <Button
                                variant="destructive"
                                size="sm"
                                :disabled="annulationEnCours"
                                @click="annulerRemise"
                            >
                                <Loader2
                                    v-if="annulationEnCours"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
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
                        <div
                            v-if="remisLe"
                            class="flex items-center gap-2 text-sm text-green-700 dark:text-green-400"
                        >
                            <CheckCircle2 class="h-5 w-5 shrink-0" />
                            <span>
                                {{ t('projets.show.submitted_on') }}
                                {{
                                    new Date(remisLe).toLocaleDateString(
                                        'fr-CA',
                                        {
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        },
                                    )
                                }}
                            </span>
                        </div>

                        <!-- Date limite -->
                        <div
                            v-if="dateRemise"
                            class="text-sm"
                            :class="
                                dateRemiseDepassee
                                    ? 'font-semibold text-destructive'
                                    : 'text-muted-foreground'
                            "
                        >
                            <CalendarDays class="mr-1 inline-block h-4 w-4" />
                            {{ t('projets.show.deadline') }}
                            {{ dateRemiseFormatee }}
                            <span v-if="dateRemiseDepassee">
                                — {{ t('projets.show.deadline_passed') }}</span
                            >
                        </div>

                        <!-- Liste des votes par membre -->
                        <ul class="space-y-1">
                            <li
                                v-for="membre in membres"
                                :key="membre.id"
                                class="flex items-center gap-2 text-sm"
                            >
                                <CheckCircle2
                                    v-if="
                                        votes.find(
                                            (v) => v.user_id === membre.id,
                                        )?.vote
                                    "
                                    class="h-4 w-4 shrink-0 text-green-500"
                                />
                                <Square
                                    v-else
                                    class="h-4 w-4 shrink-0 text-muted-foreground"
                                />
                                <span
                                    >{{ membre.prenom }} {{ membre.nom }}</span
                                >
                                <span class="text-xs text-muted-foreground">
                                    —
                                    {{
                                        votes.find(
                                            (v) => v.user_id === membre.id,
                                        )?.vote
                                            ? t('projets.show.voted')
                                            : t('projets.show.waiting_vote')
                                    }}
                                </span>
                            </li>
                        </ul>

                        <!-- Bouton voter (si pas encore voté et peut encore remettre) -->
                        <div
                            v-if="
                                peutRemettre &&
                                !votes.find((v) => v.user_id === userId)?.vote
                            "
                            class="flex justify-end"
                        >
                            <Button
                                :disabled="voteEnCours"
                                @click="voterRemise"
                            >
                                <Loader2
                                    v-if="voteEnCours"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                <Send v-else class="mr-2 h-4 w-4" />
                                {{ t('projets.show.vote_to_submit') }}
                            </Button>
                        </div>

                        <!-- Confirmation que le vote a été enregistré (voté mais pas encore tous) -->
                        <div
                            v-else-if="
                                peutRemettre &&
                                votes.find((v) => v.user_id === userId)?.vote &&
                                !remisLe
                            "
                            class="text-sm text-muted-foreground"
                        >
                            <CheckCircle2
                                class="mr-1 inline-block h-4 w-4 text-green-500"
                            />
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

            <!-- ─── Panneau droit sticky — notes en temps réel ─────────────── -->
            <div
                v-if="estEnseignant"
                v-show="!showGrilleModal"
                class="hidden lg:block lg:w-56 lg:shrink-0"
            >
                <div
                    class="sticky top-4 mx-auto w-52 rounded-lg border bg-card p-3 text-card-foreground shadow-sm"
                >
                    <button
                        class="-mx-1 mb-2 flex w-full items-center justify-between rounded px-1 transition-colors hover:bg-muted/50"
                        title="Ouvrir la grille de correction"
                        @click="showGrilleModal = true"
                    >
                        <span
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Notes
                        </span>
                        <div class="flex items-center gap-1.5">
                            <span
                                v-if="maxPoints > 0"
                                class="text-[10px] text-muted-foreground"
                            >
                                / {{ maxPoints }} pts
                            </span>
                            <Maximize2 class="h-3 w-3 text-muted-foreground" />
                        </div>
                    </button>
                    <ul class="space-y-2">
                        <li
                            v-for="membre in membres"
                            :key="membre.id"
                            class="space-y-1"
                        >
                            <div
                                class="flex items-center justify-between gap-1"
                            >
                                <span class="truncate text-xs">
                                    {{ membre.prenom }} {{ membre.nom }}
                                </span>
                                <span
                                    class="shrink-0 text-xs font-semibold tabular-nums"
                                    :class="
                                        maxPoints > 0 &&
                                        notesParMembre[membre.id] === maxPoints
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-foreground'
                                    "
                                >
                                    {{ notesParMembre[membre.id] ?? 0 }}
                                </span>
                            </div>
                            <!-- Barre de progression -->
                            <div
                                v-if="maxPoints > 0"
                                class="h-1 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-emerald-500 transition-all duration-300"
                                    :style="{
                                        width: `${Math.min(100, Math.max(0, ((notesParMembre[membre.id] ?? 0) / maxPoints) * 100))}%`,
                                    }"
                                />
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ─── Modale correction globale Antidote ────────────────────────── -->
        <!-- Pas de v-if : le composant reste monté pour que le watcher réagisse
             au passage de open=false → true et pré-charge le contenu dans l'éditeur. -->
        <AntidoteGlobalModal
            :open="showAntidoteGlobal"
            :sections="buildSectionsForAntidote()"
            @update:open="showAntidoteGlobal = $event"
            @corrected="onSectionsCorrigees"
        />

        <!-- ─── Modal confirmation suppression de référence ─────────────────── -->
        <ConfirmationModal
            :open="renvoisSupprimerModalOuvert"
            title="Supprimer cette référence ?"
            :description="renvoisSupprimerDescription"
            confirm-label="Oui, supprimer"
            :loading="renvoisSupprimerEnCours"
            @update:open="onModalRenvoisUpdateOpen"
            @confirm="confirmerSupprimerRenvoi"
        />

        <!-- ─── Modal aide à la saisie des références APA ─────────────────── -->
        <ReferenceApaModal
            v-if="aideReference"
            v-model:open="referenceModalOuvert"
            :renvoi="renvoisEnEdition"
            :mes-references="mesReferences"
            @inserer="confirmerReferenceApa"
        />

        <!-- ─── Modal grille de correction ───────────────────────────────────── -->
        <Dialog v-model:open="showGrilleModal">
            <DialogScrollContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Grille de correction</DialogTitle>
                </DialogHeader>

                <!-- Résumé des notes par membre -->
                <div
                    v-if="maxPoints > 0"
                    class="grid grid-cols-2 gap-2 sm:grid-cols-4"
                >
                    <div
                        v-for="membre in membres"
                        :key="membre.id"
                        class="rounded-md border bg-muted/30 p-2 text-center"
                    >
                        <p class="truncate text-xs text-muted-foreground">
                            {{ membre.prenom }} {{ membre.nom.charAt(0) }}.
                        </p>
                        <p
                            class="mt-0.5 text-lg font-bold tabular-nums"
                            :class="
                                notesParMembre[membre.id] === maxPoints
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-foreground'
                            "
                        >
                            {{ notesParMembre[membre.id] ?? 0 }}
                        </p>
                        <p class="text-[10px] text-muted-foreground">
                            / {{ maxPoints }}
                        </p>
                    </div>
                </div>

                <Separator v-if="maxPoints > 0" />

                <!-- Critères globaux -->
                <div v-if="criteresGlobaux.length > 0" class="space-y-1.5">
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ t('criteres.titre_global') }}
                    </p>
                    <CritereCorrection
                        v-for="critere in criteresGlobaux"
                        :key="critere.id"
                        :cours-id="classe.cours_id"
                        :classe-id="classe.id"
                        :groupe-id="groupe.id"
                        :type-projet-id="typeProjet.id"
                        :critere="critere"
                        :corrections="correctionsLocales[critere.id] ?? []"
                        :membres="membres"
                        @updated="
                            (nouvelles) =>
                                onCorrectionsUpdated(critere.id, nouvelles)
                        "
                    />
                </div>

                <!-- Critères par section -->
                <template
                    v-for="section in props.sections.filter(
                        (s) => s.criteres?.length,
                    )"
                    :key="section.id"
                >
                    <Separator />
                    <div class="space-y-1.5">
                        <p
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            {{ section.label }}
                        </p>
                        <CritereCorrection
                            v-for="critere in section.criteres"
                            :key="critere.id"
                            :cours-id="classe.cours_id"
                            :classe-id="classe.id"
                            :groupe-id="groupe.id"
                            :type-projet-id="typeProjet.id"
                            :critere="critere"
                            :corrections="correctionsLocales[critere.id] ?? []"
                            :membres="membres"
                            @updated="
                                (nouvelles) =>
                                    onCorrectionsUpdated(critere.id, nouvelles)
                            "
                        />
                    </div>
                </template>
            </DialogScrollContent>
        </Dialog>
    </AppLayout>
</template>
