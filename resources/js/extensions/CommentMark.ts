import { Mark, mergeAttributes } from '@tiptap/core';

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        comment: {
            /** Applique la marque de correction sur la sélection courante. */
            setComment: (
                commentId: string,
                annotationType?: 'commentaire' | 'correction',
            ) => ReturnType;
            /** Retire la marque de correction dont l'ID correspond. */
            unsetComment: (commentId: string) => ReturnType;
            /** Met à jour le type d'une marque existante sans retirer / réappliquer la sélection. */
            updateCommentType: (
                commentId: string,
                annotationType: 'commentaire' | 'correction',
            ) => ReturnType;
        };
    }
}

/**
 * Mark TipTap représentant une correction inline de l'enseignant.
 * Stocke un UUID (commentId) comme attribut HTML data-comment-id.
 */
export const CommentMark = Mark.create({
    name: 'comment',

    // Priorité haute pour que parseHTML soit évalué AVANT Highlight (priorité 50 par défaut).
    // Sans cela, Highlight consomme tous les <mark> via sa règle générique { tag: 'mark' },
    // et les attributs data-comment-id sont perdus au rechargement de page.
    priority: 1000,

    // Les marques de correction peuvent se superposer à d'autres marques
    inclusive: false,
    excludes: '',

    addAttributes() {
        return {
            commentId: {
                default: null,
                parseHTML: (el: HTMLElement) =>
                    el.getAttribute('data-comment-id'),
                renderHTML: (attrs: Record<string, string | null>) => ({
                    'data-comment-id': attrs.commentId,
                }),
            },
            annotationType: {
                default: 'commentaire',
                parseHTML: (el: HTMLElement) =>
                    el.getAttribute('data-annotation-type') ?? 'commentaire',
                renderHTML: (attrs: Record<string, string | null>) => ({
                    'data-annotation-type':
                        attrs.annotationType ?? 'commentaire',
                }),
            },
            isActive: {
                default: false,
                // Pas rendu en HTML — géré en JS uniquement pour le highlight au survol
                rendered: false,
            },
        };
    },

    parseHTML() {
        return [{ tag: 'mark[data-comment-id]' }];
    },

    renderHTML({ HTMLAttributes }) {
        const annotationType =
            (HTMLAttributes['data-annotation-type'] as string | undefined) ??
            'commentaire';

        return [
            'mark',
            mergeAttributes(HTMLAttributes, {
                class: `comment-mark comment-mark--${annotationType}`,
            }),
            0,
        ];
    },

    addCommands() {
        return {
            setComment:
                (
                    commentId: string,
                    annotationType:
                        | 'commentaire'
                        | 'correction' = 'commentaire',
                ) =>
                ({ commands }) =>
                    commands.setMark(this.name, { commentId, annotationType }),

            unsetComment:
                (commentId: string) =>
                ({ tr, state, dispatch }) => {
                    const { doc, schema } = state;
                    const markType = schema.marks.comment;

                    if (!markType || !dispatch) {
                        return false;
                    }

                    let modified = false;
                    doc.descendants((node, pos) => {
                        const mark = node.marks.find(
                            (m) =>
                                m.type === markType &&
                                m.attrs.commentId === commentId,
                        );

                        if (mark) {
                            tr.removeMark(pos, pos + node.nodeSize, mark);
                            modified = true;
                        }
                    });

                    if (modified) {
                        dispatch(tr);
                    }

                    return modified;
                },

            updateCommentType:
                (
                    commentId: string,
                    annotationType: 'commentaire' | 'correction',
                ) =>
                ({ tr, state, dispatch }) => {
                    const { doc, schema } = state;
                    const markType = schema.marks.comment;

                    if (!markType || !dispatch) {
                        return false;
                    }

                    let modified = false;
                    doc.descendants((node, pos) => {
                        const mark = node.marks.find(
                            (m) =>
                                m.type === markType &&
                                m.attrs.commentId === commentId,
                        );

                        if (mark) {
                            const newMark = markType.create({
                                ...mark.attrs,
                                annotationType,
                            });
                            tr.removeMark(pos, pos + node.nodeSize, mark);
                            tr.addMark(pos, pos + node.nodeSize, newMark);
                            modified = true;
                        }
                    });

                    if (modified) {
                        dispatch(tr);
                    }

                    return modified;
                },
        };
    },
});
