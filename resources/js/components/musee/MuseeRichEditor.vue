<script setup>
import { computed, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Rédigez votre contenu ici…',
    },
    readonly: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['update:modelValue'])

// Extension Link enrichie d'un attribut data-externe pour identifier les liens sortants
const LinkExterne = Link.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            'data-externe': {
                default: null,
                renderHTML: (attrs) =>
                    attrs['data-externe'] === 'true' ? { 'data-externe': 'true' } : {},
                parseHTML: (el) => el.getAttribute('data-externe') ?? null,
            },
        }
    },
}).configure({
    openOnClick: false,
    HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' },
})

const editor = useEditor({
    content: props.modelValue,
    editable: !props.readonly,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            code: false,
            codeBlock: false,
        }),
        Underline,
        LinkExterne,
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    onUpdate: ({ editor: ed }) => {
        emit('update:modelValue', ed.getHTML())
    },
})

// Synchronise le contenu quand le parent change (ex : reset du formulaire)
watch(
    () => props.modelValue,
    (val) => {
        if (editor.value && editor.value.getHTML() !== val) {
            editor.value.commands.setContent(val ?? '', false)
        }
    },
)

watch(
    () => props.readonly,
    (val) => editor.value?.setEditable(!val),
)

/**
 * Ouvre un prompt pour insérer ou modifier un lien.
 * Marque automatiquement le lien comme externe (data-externe="true").
 */
function insererLien() {
    const url = window.prompt(
        'URL du lien (laisser vide pour supprimer) :',
        editor.value?.getAttributes('link').href ?? '',
    )

    if (url === null) return // annulé

    if (url === '') {
        editor.value?.chain().focus().unsetLink().run()
        return
    }

    editor.value
        ?.chain()
        .focus()
        .setLink({ href: url, 'data-externe': 'true' })
        .run()
}

const isActive = computed(() => (type, attrs = {}) => editor.value?.isActive(type, attrs) ?? false)
</script>

<template>
    <div class="musee-rich-editor rounded-md border border-gray-300 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
        <!-- Barre d'outils -->
        <div
            v-if="!readonly"
            class="flex flex-wrap gap-0.5 border-b border-gray-200 bg-gray-50 p-1.5"
        >
            <!-- Styles de caractères -->
            <button
                type="button"
                class="toolbar-btn"
                :class="{ 'toolbar-btn--active': isActive('bold') }"
                title="Gras"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <strong>G</strong>
            </button>

            <button
                type="button"
                class="toolbar-btn italic"
                :class="{ 'toolbar-btn--active': isActive('italic') }"
                title="Italique"
                @click="editor?.chain().focus().toggleItalic().run()"
            >
                I
            </button>

            <button
                type="button"
                class="toolbar-btn underline"
                :class="{ 'toolbar-btn--active': isActive('underline') }"
                title="Souligné"
                @click="editor?.chain().focus().toggleUnderline().run()"
            >
                S
            </button>

            <span class="mx-1 my-auto h-4 w-px bg-gray-300" />

            <!-- Niveaux de titre -->
            <button
                type="button"
                class="toolbar-btn"
                :class="{ 'toolbar-btn--active': isActive('heading', { level: 2 }) }"
                title="Titre (H2)"
                @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
            >
                H2
            </button>

            <button
                type="button"
                class="toolbar-btn"
                :class="{ 'toolbar-btn--active': isActive('heading', { level: 3 }) }"
                title="Sous-titre (H3)"
                @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
            >
                H3
            </button>

            <span class="mx-1 my-auto h-4 w-px bg-gray-300" />

            <!-- Listes -->
            <button
                type="button"
                class="toolbar-btn"
                :class="{ 'toolbar-btn--active': isActive('bulletList') }"
                title="Liste à puces"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                &#8226;&#8212;
            </button>

            <button
                type="button"
                class="toolbar-btn"
                :class="{ 'toolbar-btn--active': isActive('orderedList') }"
                title="Liste numérotée"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                1.
            </button>

            <!-- Citation -->
            <button
                type="button"
                class="toolbar-btn"
                :class="{ 'toolbar-btn--active': isActive('blockquote') }"
                title="Citation"
                @click="editor?.chain().focus().toggleBlockquote().run()"
            >
                ❝
            </button>

            <span class="mx-1 my-auto h-4 w-px bg-gray-300" />

            <!-- Lien externe -->
            <button
                type="button"
                class="toolbar-btn"
                :class="{ 'toolbar-btn--active': isActive('link') }"
                title="Insérer un lien externe"
                @click="insererLien"
            >
                🔗
            </button>
        </div>

        <!-- Zone de texte -->
        <EditorContent
            class="musee-prose px-3 py-2 text-sm"
            :editor="editor"
        />
    </div>
</template>

<style scoped>
/* Tailwind v4 — @apply interdit dans les blocs scoped : CSS standard uniquement */

.toolbar-btn {
    border-radius: 0.25rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    line-height: 1rem;
    font-weight: 500;
    color: #4b5563;
    min-width: 1.75rem;
}

.toolbar-btn:hover {
    background-color: #e5e7eb;
}

.toolbar-btn--active {
    background-color: #dbeafe;
    color: #1d4ed8;
}

:deep(.tiptap) {
    min-height: 8rem;
    outline: none;
}

:deep(.tiptap p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    color: #9ca3af;
    pointer-events: none;
    float: left;
    height: 0;
}

:deep(.tiptap h2) {
    font-size: 1.125rem;
    font-weight: 600;
    margin-top: 0.75rem;
    margin-bottom: 0.25rem;
}

:deep(.tiptap h3) {
    font-size: 1rem;
    font-weight: 600;
    margin-top: 0.5rem;
    margin-bottom: 0.25rem;
}

:deep(.tiptap ul) {
    list-style-type: disc;
    padding-left: 1.25rem;
}

:deep(.tiptap ol) {
    list-style-type: decimal;
    padding-left: 1.25rem;
}

:deep(.tiptap blockquote) {
    border-left: 4px solid #d1d5db;
    padding-left: 0.75rem;
    font-style: italic;
    color: #4b5563;
}

:deep(.tiptap a) {
    color: #2563eb;
    text-decoration: underline;
}
</style>
