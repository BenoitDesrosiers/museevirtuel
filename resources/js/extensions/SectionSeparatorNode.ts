import { Node, mergeAttributes } from '@tiptap/core';

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        sectionSeparator: {
            /** Insère un séparateur de section non-éditable à la position courante. */
            insertSectionSeparator: (attrs: {
                sectionId: string;
                sectionLabel: string;
            }) => ReturnType;
        };
    }
}

/**
 * Nœud TipTap atomique représentant la fin d'une section dans l'éditeur global Antidote.
 *
 * Propriétés clés :
 * - `atom: true` → non-éditable, non-fragmentable, le curseur passe par-dessus
 * - `contenteditable="false"` sur le rendu DOM → Antidote JS-Connect l'ignore
 * - Attributs `data-section-id` et `data-section-label` permettent de retrouver
 *   chaque section lors du parsing après correction.
 */
export const SectionSeparatorNode = Node.create({
    name: 'sectionSeparator',

    group: 'block',
    atom: true,
    draggable: false,
    selectable: false,

    addAttributes() {
        return {
            sectionId: {
                default: null,
                parseHTML: (el: HTMLElement) => el.getAttribute('data-section-id'),
                renderHTML: (attrs: Record<string, unknown>) => ({
                    'data-section-id': attrs.sectionId,
                }),
            },
            sectionLabel: {
                default: '',
                parseHTML: (el: HTMLElement) =>
                    el.getAttribute('data-section-label') ?? '',
                renderHTML: (attrs: Record<string, unknown>) => ({
                    'data-section-label': attrs.sectionLabel,
                }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-type="section-separator"]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return [
            'div',
            mergeAttributes(HTMLAttributes, {
                'data-type': 'section-separator',
                contenteditable: 'false',
                class: [
                    'section-separator',
                    'my-3 py-1.5 px-4',
                    'text-center text-xs font-semibold tracking-wide',
                    'text-green-700 bg-green-50 border border-green-200 rounded',
                    'select-none cursor-default',
                ].join(' '),
            }),
            `— FIN : ${node.attrs.sectionLabel as string} —`,
        ];
    },

    addCommands() {
        return {
            insertSectionSeparator:
                (attrs: { sectionId: string; sectionLabel: string }) =>
                ({ commands }) =>
                    commands.insertContent({ type: this.name, attrs }),
        };
    },
});
