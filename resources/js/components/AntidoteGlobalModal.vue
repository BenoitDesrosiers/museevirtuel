<script setup lang="ts">
import CharacterCount from '@tiptap/extension-character-count';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import Typography from '@tiptap/extension-typography';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { BookOpen, BookText, SpellCheck, X } from 'lucide-vue-next';
import { onBeforeUnmount, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { AntidoteExtension, generateAntidoteGroupeId } from '@/extensions/AntidoteExtension';
import { SectionSeparatorNode } from '@/extensions/SectionSeparatorNode';

// ─── Types ─────────────────────────────────────────────────────────────────────

export type GlobalSection = {
    /** Identifiant unique de la section (ex. : "section_12", "intro_amener", "dev_3") */
    id: string;
    /** Libellé affiché dans le séparateur visuel */
    label: string;
    /** Contenu HTML courant de la section */
    html: string;
};

// ─── Props & emits ─────────────────────────────────────────────────────────────

const props = defineProps<{
    open: boolean;
    sections: GlobalSection[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    /** Émis après "Appliquer" avec le tableau des sections corrigées */
    corrected: [sections: GlobalSection[]];
}>();

// ─── TipTap ────────────────────────────────────────────────────────────────────

const antidoteGroupeId = generateAntidoteGroupeId();

const extensions = [
    StarterKit.configure({ underline: false, link: false }),
    Underline,
    Subscript,
    Superscript,
    TextStyle,
    Color,
    Highlight.configure({ multicolor: true }),
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    Link.configure({ openOnClick: false }),
    Typography,
    Placeholder.configure({ placeholder: '' }),
    CharacterCount,
    SectionSeparatorNode,
    AntidoteExtension.configure({ groupeId: antidoteGroupeId }),
];

const editor = useEditor({
    content: '',
    editable: true,
    extensions,
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none min-h-[300px] focus:outline-none px-4 py-3',
        },
    },
});

onBeforeUnmount(() => editor.value?.destroy());

// ─── Ouverture : construction du contenu combiné ───────────────────────────────

/**
 * Construit une chaîne HTML combinant le contenu de toutes les sections,
 * chaque section étant suivie d'un nœud séparateur non-éditable.
 */
function buildCombinedHtml(sections: GlobalSection[]): string {
    return sections
        .map((s) => {
            const content = s.html?.trim() || '<p></p>';
            const sep = `<div data-type="section-separator" data-section-id="${s.id}" data-section-label="${s.label}"></div>`;
            return content + sep;
        })
        .join('');
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && editor.value) {
            editor.value.commands.setContent(buildCombinedHtml(props.sections), false);
        }
    },
);

// ─── Parsing après correction ──────────────────────────────────────────────────

/**
 * Parcourt le DOM de l'éditeur et découpe le HTML par les séparateurs de section.
 * Retourne un tableau de sections corrigées dans le même ordre que `props.sections`.
 */
function parseCorrigee(): GlobalSection[] {
    if (!editor.value) return [];

    const fullHtml = editor.value.getHTML();
    const parser = new DOMParser();
    const doc = parser.parseFromString(fullHtml, 'text/html');
    const children = Array.from(doc.body.childNodes);

    const result: GlobalSection[] = [];
    let currentHtml = '';
    let sectionIndex = 0;

    for (const node of children) {
        const el = node as Element;
        if (
            el.nodeType === 1 &&
            el.getAttribute('data-type') === 'section-separator'
        ) {
            // On a atteint un séparateur : on ferme la section courante
            const source = props.sections[sectionIndex];
            if (source) {
                result.push({ ...source, html: currentHtml.trim() });
            }
            currentHtml = '';
            sectionIndex++;
        } else {
            currentHtml += (el as Element).outerHTML ?? el.textContent ?? '';
        }
    }

    return result;
}

// ─── Actions ───────────────────────────────────────────────────────────────────

function appliquer() {
    emit('corrected', parseCorrigee());
    emit('update:open', false);
}

function annuler() {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="flex max-h-[90vh] max-w-4xl flex-col gap-0 p-0">
            <DialogHeader class="border-b px-6 py-4">
                <DialogTitle class="flex items-center gap-2 text-base font-semibold">
                    <SpellCheck class="h-5 w-5 text-green-600" />
                    Correction globale avec Antidote
                </DialogTitle>
                <p class="text-sm text-muted-foreground">
                    Tout le texte du projet est regroupé ci-dessous. Lancez Antidote,
                    corrigez, puis cliquez sur <strong>Appliquer</strong> pour redistribuer
                    les corrections dans chaque section.
                </p>
            </DialogHeader>

            <!-- Barre Antidote -->
            <div class="flex items-center gap-1.5 border-b bg-muted/40 px-4 py-2">
                <span class="mr-1 text-xs font-medium text-muted-foreground">Antidote :</span>
                <button
                    type="button"
                    class="flex items-center gap-1 rounded px-2 py-1 text-xs font-semibold text-green-700 transition-colors hover:bg-green-100"
                    title="Lancer le correcteur Antidote"
                    data-antidoteapi_jsconnect_lanceoutil="C"
                    :data-antidoteapi_jsconnect_groupe_id="antidoteGroupeId"
                >
                    <SpellCheck class="h-3.5 w-3.5" />
                    Corriger
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1 rounded px-2 py-1 text-xs font-semibold text-green-700 transition-colors hover:bg-green-100"
                    title="Ouvrir les dictionnaires Antidote"
                    data-antidoteapi_jsconnect_lanceoutil="D"
                    :data-antidoteapi_jsconnect_groupe_id="antidoteGroupeId"
                >
                    <BookOpen class="h-3.5 w-3.5" />
                    Dictionnaires
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1 rounded px-2 py-1 text-xs font-semibold text-green-700 transition-colors hover:bg-green-100"
                    title="Ouvrir les guides Antidote"
                    data-antidoteapi_jsconnect_lanceoutil="G"
                    :data-antidoteapi_jsconnect_groupe_id="antidoteGroupeId"
                >
                    <BookText class="h-3.5 w-3.5" />
                    Guides
                </button>
            </div>

            <!-- Éditeur combiné -->
            <div class="min-h-0 flex-1 overflow-y-auto">
                <EditorContent :editor="editor" />
            </div>

            <DialogFooter class="border-t px-6 py-4">
                <Button variant="ghost" @click="annuler">
                    <X class="mr-2 h-4 w-4" />
                    Annuler
                </Button>
                <Button class="bg-green-600 hover:bg-green-700" @click="appliquer">
                    <SpellCheck class="mr-2 h-4 w-4" />
                    Appliquer les corrections
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
