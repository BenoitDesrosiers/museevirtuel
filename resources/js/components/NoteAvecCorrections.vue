<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import Highlight from '@tiptap/extension-highlight';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { MessageSquare, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { CommentMark } from '@/extensions/CommentMark';
import * as correctionsRoutes from '@/routes/groupes/notes/corrections';

type Correction = {
    id: number;
    commentaire_id: string;
    contenu: string;
    user_id: number;
};

type Note = {
    id: number;
    contenu: string;
    corrections: Correction[];
};

const props = defineProps<{
    note: Note;
    estEnseignant: boolean;
    coursId: number | string;
    classeId: number;
    groupeId: number;
}>();

// ─── Normalisation HTML ────────────────────────────────────────────────────────

/** Convertit le texte brut en paragraphes HTML pour TipTap. */
function normalizeHtml(content: string): string {
    if (!content) {
        return '<p></p>';
    }

    if (!/<[a-z][\s\S]*>/i.test(content)) {
        return content
            .split(/\n{2,}/)
            .map((p) => `<p>${p.replace(/\n/g, '<br>')}</p>`)
            .join('');
    }

    return content;
}

// ─── Éditeur TipTap ────────────────────────────────────────────────────────────

const editorWrapRef = ref<HTMLDivElement | null>(null);

// HTML réactif de l'éditeur — initialisé via onCreate puis mis à jour via onUpdate.
// Permet à sortedCorrections d'être un computed Vue correctement réactif.
const editorHtml = ref(normalizeHtml(props.note.contenu));

const editor = useEditor({
    content: normalizeHtml(props.note.contenu),
    editable: false,
    extensions: [
        StarterKit,
        Highlight.configure({ multicolor: true }),
        CommentMark,
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none px-3 py-2 min-h-[60px]',
        },
    },
    // onCreate garantit que editorHtml contient le HTML canonique TipTap dès le montage,
    // y compris après un rechargement de page où onUpdate ne se déclenche pas au démarrage.
    onCreate: ({ editor: e }) => {
        editorHtml.value = e.getHTML();
    },
    onUpdate: ({ editor: e }) => {
        editorHtml.value = e.getHTML();
    },
});

watch(
    () => props.note.contenu,
    (val) => {
        const html = normalizeHtml(val);

        // Synchronise l'éditeur avec le contenu confirmé par le serveur.
        // setContent déclenche onUpdate → editorHtml est mis à jour automatiquement.
        if (editor.value && editor.value.getHTML() !== html) {
            editor.value.commands.setContent(html, false);
        }
    },
);

onBeforeUnmount(() => editor.value?.destroy());

// ─── Mode annotation (enseignant) ─────────────────────────────────────────────

/** Génère un UUID v4, avec fallback pour les contextes non-sécurisés (HTTP). */
function generateUUID(): string {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) =>
        (
            +c ^
            (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (+c / 4)))
        ).toString(16),
    );
}

const annotationModeActive = ref(false);
const showBubble = ref(false);
const brouillon = ref('');
const isSaving = ref(false);
const savedRange = ref<Range | null>(null);

function toggleAnnotationMode() {
    annotationModeActive.value = !annotationModeActive.value;

    if (!annotationModeActive.value) {
        showBubble.value = false;
        brouillon.value = '';
        savedRange.value = null;
    }
}

/** Capture la sélection et signale qu'un texte est prêt à être annoté. */
function handleMouseUp() {
    if (!props.estEnseignant || !annotationModeActive.value) {
        return;
    }

    // Ne pas interférer si le formulaire d'annotation est déjà ouvert.
    if (showBubble.value) {
        return;
    }

    const selection = window.getSelection();

    if (!selection || selection.isCollapsed || !selection.toString().trim()) {
        return;
    }

    // Capturer le Range avant que la sélection DOM soit perdue au moment du clic sur "Publier".
    savedRange.value = selection.getRangeAt(0).cloneRange();
    showBubble.value = true;
}

async function saveCorrection() {
    if (!editor.value || !brouillon.value.trim() || !savedRange.value) {
        return;
    }

    isSaving.value = true;
    const commentId = generateUUID();

    // Active temporairement l'édition pour insérer la marque.
    editor.value.setEditable(true);

    const { from, to } = editor.value.state.selection;

    if (from === to) {
        // Utilise le Range capturé à l'ouverture du formulaire (la sélection DOM est perdue au clic).
        const domRange = savedRange.value;
        const fromPos = editor.value.view.posAtDOM(
            domRange.startContainer,
            domRange.startOffset,
        );
        const toPos = editor.value.view.posAtDOM(
            domRange.endContainer,
            domRange.endOffset,
        );
        editor.value
            .chain()
            .setTextSelection({ from: fromPos, to: toPos })
            .setComment(commentId, 'correction')
            .run();
    } else {
        editor.value.chain().focus().setComment(commentId, 'correction').run();
    }

    editor.value.setEditable(false);

    const updatedHtml = editor.value.getHTML();
    const contenuSaisi = brouillon.value.trim();

    // Affichage optimiste : la carte apparaît immédiatement dans la liste.
    // ID négatif = temporaire, remplacé par l'ID serveur à la confirmation.
    pendingCorrections.value.push({
        id: -Date.now(),
        commentaire_id: commentId,
        contenu: contenuSaisi,
        user_id: 0,
    });

    brouillon.value = '';
    showBubble.value = false;
    savedRange.value = null;

    router.put(
        correctionsRoutes.upsert({
            cours: props.coursId,
            classe: props.classeId,
            groupe: props.groupeId,
            note: props.note.id,
        }).url,
        {
            commentaire_id: commentId,
            contenu: contenuSaisi,
            note_html: updatedHtml,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                // Inertia a mis à jour props.note.corrections — on retire la version optimiste.
                pendingCorrections.value = pendingCorrections.value.filter(
                    (p) => p.commentaire_id !== commentId,
                );
            },
            onError: () => {
                // Annulation : retire la carte et la marque dans le texte.
                // onUpdate se déclenche via unsetComment et met à jour editorHtml.
                pendingCorrections.value = pendingCorrections.value.filter(
                    (p) => p.commentaire_id !== commentId,
                );

                if (editor.value) {
                    editor.value.setEditable(true);
                    editor.value.commands.unsetComment(commentId);
                    editor.value.setEditable(false);
                }
            },
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
}

function cancelBubble() {
    showBubble.value = false;
    brouillon.value = '';
    savedRange.value = null;
}

// ─── Corrections optimistes (affichage immédiat avant confirmation serveur) ────

/** Corrections ajoutées localement, en attente de confirmation serveur. ID négatif = temporaire. */
const pendingCorrections = ref<Correction[]>([]);

// ─── Suppression d'une correction ─────────────────────────────────────────────

const isDeletingId = ref<number | null>(null);
const hoveredCommentId = ref<string | null>(null);
const activeAnnotationId = ref<string | null>(null);

function deleteCorrection(correction: Correction) {
    if (!editor.value) {
        return;
    }

    isDeletingId.value = correction.id;

    // Retire la marque localement avant la requête pour un retour visuel immédiat.
    // onUpdate se déclenche et met à jour editorHtml automatiquement.
    editor.value.setEditable(true);
    editor.value.commands.unsetComment(correction.commentaire_id);
    editor.value.setEditable(false);

    const updatedHtml = editor.value.getHTML();

    router.delete(
        correctionsRoutes.destroy({
            cours: props.coursId,
            classe: props.classeId,
            groupe: props.groupeId,
            note: props.note.id,
            correction: correction.id,
        }).url,
        {
            data: { note_html: updatedHtml },
            preserveScroll: true,
            onFinish: () => {
                isDeletingId.value = null;
            },
        },
    );
}

// ─── Tri des corrections par position dans le texte ────────────────────────────

/** Retourne un Map commentId → index d'apparition dans le HTML. */
function getCommentIdOrder(html: string): Map<string, number> {
    const order = new Map<string, number>();
    const regex = /data-comment-id="([^"]+)"/g;
    let match: RegExpExecArray | null = null;
    let i = 0;

    while ((match = regex.exec(html)) !== null) {
        const id = match[1];

        if (!order.has(id)) {
            order.set(id, i++);
        }
    }

    return order;
}

/** Corrections triées selon leur position d'apparition dans le texte rendu par TipTap. */
const sortedCorrections = computed(() => {
    // Fusionne les corrections confirmées (serveur) et les optimistes (locales),
    // en évitant les doublons par commentaire_id.
    const serverIds = new Set(props.note.corrections.map((c) => c.commentaire_id));
    const list = [
        ...props.note.corrections,
        ...pendingCorrections.value.filter((p) => !serverIds.has(p.commentaire_id)),
    ];

    if (list.length <= 1) {
        return list;
    }

    // editorHtml est un ref Vue réactif mis à jour via onUpdate — contrairement à
    // editor.value.getHTML() appelé directement, il déclenche correctement le recalcul.
    const order = getCommentIdOrder(editorHtml.value);

    return [...list].sort((a, b) => {
        const pa = order.get(a.commentaire_id);
        const pb = order.get(b.commentaire_id);

        if (pa === undefined && pb === undefined) {
            return a.id - b.id;
        }
        if (pa === undefined) {
            return 1;
        }
        if (pb === undefined) {
            return -1;
        }
        if (pa !== pb) {
            return pa - pb;
        }

        return a.id - b.id;
    });
});

/**
 * Applique la classe CSS active sur la marque survolée/cliquée dans le panneau.
 * Si scroll est true, fait défiler l'éditeur jusqu'à la marque correspondante.
 */
function highlightMark(commentId: string | null, scroll = false) {
    hoveredCommentId.value = commentId;

    if (!editorWrapRef.value) {
        return;
    }

    editorWrapRef.value.querySelectorAll('mark.comment-mark').forEach((el) => {
        el.classList.remove('comment-mark--active');
    });

    if (commentId) {
        const marks = editorWrapRef.value.querySelectorAll(`mark[data-comment-id="${commentId}"]`);

        marks.forEach((el) => el.classList.add('comment-mark--active'));

        if (scroll && marks.length > 0) {
            marks[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

/**
 * Gère le clic sur une marque de correction dans l'éditeur.
 * Active ou désactive la liaison marque ↔ carte dans le panneau.
 */
function handleEditorClick(e: MouseEvent): void {
    const mark = (e.target as HTMLElement).closest('mark[data-comment-id]');

    if (mark) {
        const commentId = mark.getAttribute('data-comment-id');
        activeAnnotationId.value = commentId === activeAnnotationId.value ? null : commentId;
        highlightMark(activeAnnotationId.value);
    }
}

/**
 * Gère le clic sur une carte de correction dans le panneau.
 * Active ou désactive la liaison carte ↔ marque dans le texte et fait défiler jusqu'à elle.
 */
function handleCardClick(commentId: string): void {
    activeAnnotationId.value = commentId === activeAnnotationId.value ? null : commentId;
    highlightMark(activeAnnotationId.value, true);
}
</script>

<template>
    <div class="flex gap-4">
        <!-- ─── Éditeur TipTap ──────────────────────────────────────────────────── -->
        <div
            ref="editorWrapRef"
            class="note-editor-wrap min-w-0 flex-1"
            @mouseup="handleMouseUp"
            @click="handleEditorClick"
        >
            <EditorContent :editor="editor" />
        </div>

        <!-- ─── Panneau latéral ──────────────────────────────────────────────────── -->
        <div
            v-if="note.corrections.length > 0 || estEnseignant"
            class="flex w-48 shrink-0 flex-col gap-2"
        >
            <!-- Bouton mode annotation -->
            <Button
                v-if="estEnseignant"
                size="sm"
                :variant="annotationModeActive ? 'default' : 'outline'"
                class="w-full text-xs"
                @click="toggleAnnotationMode"
            >
                <MessageSquare class="mr-1.5 h-3.5 w-3.5" />
                {{ annotationModeActive ? 'Terminer' : 'Annoter' }}
            </Button>

            <!-- Formulaire d'annotation (texte sélectionné en mode annotation) -->
            <div
                v-if="showBubble"
                class="space-y-1.5 rounded-md border border-amber-200 bg-amber-50 p-2 dark:border-amber-700 dark:bg-amber-950"
            >
                <p class="text-xs text-amber-600 dark:text-amber-400">
                    Correction pour le texte sélectionné
                </p>
                <Textarea
                    v-model="brouillon"
                    placeholder="Écrire une correction…"
                    class="min-h-[60px] text-sm"
                    rows="2"
                    autofocus
                />
                <div class="flex gap-1.5">
                    <Button
                        size="sm"
                        :disabled="isSaving || !brouillon.trim()"
                        @click="saveCorrection"
                    >
                        Publier
                    </Button>
                    <Button size="sm" variant="ghost" @click="cancelBubble">
                        Annuler
                    </Button>
                </div>
            </div>

            <!-- Liste des corrections -->
            <div
                v-for="correction in sortedCorrections"
                :key="correction.id"
                class="correction-card group relative cursor-pointer rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
                :class="{ 'ring-2 ring-blue-400 ring-offset-1': activeAnnotationId === correction.commentaire_id }"
                @mouseenter="highlightMark(correction.commentaire_id)"
                @mouseleave="activeAnnotationId ? highlightMark(activeAnnotationId) : highlightMark(null)"
                @click="handleCardClick(correction.commentaire_id)"
            >
                <MessageSquare class="mb-1 h-3 w-3 text-amber-500" />
                <p>{{ correction.contenu }}</p>
                <button
                    v-if="estEnseignant && correction.id > 0"
                    type="button"
                    class="absolute top-1 right-1 hidden text-amber-400 group-hover:block hover:text-amber-700"
                    :disabled="isDeletingId === correction.id"
                    @click.stop="deleteCorrection(correction)"
                >
                    <X class="h-3 w-3" />
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ─── Marques de correction ──────────────────────────────────────────────────── */
:deep(mark.comment-mark) {
    background: transparent;
    border-bottom: 2px solid #f97316;
    border-radius: 0;
    cursor: pointer;
    padding-bottom: 1px;
}

:deep(mark.comment-mark--active) {
    background: #bfdbfe;
    border-bottom-color: #3b82f6;
}

/* ─── Mode annotation actif ─────────────────────────────────────────────────── */
.note-editor-wrap :deep(.ProseMirror) {
    user-select: text;
}
</style>
