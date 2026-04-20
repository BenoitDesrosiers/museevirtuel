import { Node, mergeAttributes } from '@tiptap/core';

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        renvoiMark: {
            /** Insère un exposant de renvoi (endnote) à la position courante du curseur. */
            insertRenvoi: (attrs: { renvoiId: number; numero: number }) => ReturnType;
        };
    }
}

/**
 * Nœud TipTap inline atomique représentant un renvoi (endnote) dans le texte.
 *
 * Comportement :
 * - `inline: true` + `atom: true` → s'insère comme un seul caractère non-éditable
 * - Stocke l'id du renvoi (`data-renvoi-id`) et son numéro (`data-renvoi-numero`)
 * - Affiche <sup>N</sup> — bleu si le renvoi existe, rouge si orphelin
 * - La détection orphelin se fait côté Vue en comparant les ids présents dans renvois[]
 *
 * Exemple de HTML produit :
 * <sup class="renvoi text-blue-600 ..." data-renvoi-id="5" data-renvoi-numero="2">2</sup>
 */
export const RenvoiMark = Node.create({
    name: 'renvoiMark',

    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,
    draggable: false,

    addAttributes() {
        return {
            renvoiId: {
                default: null,
                parseHTML: (el: HTMLElement) => {
                    const val = el.getAttribute('data-renvoi-id');
                    return val !== null ? parseInt(val, 10) : null;
                },
                renderHTML: (attrs: Record<string, unknown>) => ({
                    'data-renvoi-id': attrs.renvoiId,
                }),
            },
            numero: {
                default: null,
                parseHTML: (el: HTMLElement) => {
                    const val = el.getAttribute('data-renvoi-numero');
                    return val !== null ? parseInt(val, 10) : null;
                },
                renderHTML: (attrs: Record<string, unknown>) => ({
                    'data-renvoi-numero': attrs.numero,
                }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'sup[data-renvoi-id]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return [
            'sup',
            mergeAttributes(HTMLAttributes, {
                class: 'renvoi text-blue-600 cursor-pointer font-bold text-xs align-super',
            }),
            String(node.attrs.numero ?? '?'),
        ];
    },

    addCommands() {
        return {
            insertRenvoi:
                (attrs: { renvoiId: number; numero: number }) =>
                ({ commands }) =>
                    commands.insertContent({
                        type: this.name,
                        attrs: {
                            renvoiId: attrs.renvoiId,
                            numero: attrs.numero,
                        },
                    }),
        };
    },
});
