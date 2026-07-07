import { ref } from 'vue';

type PendingDelete = {
    titre: string;
    description: string;
    action: () => void | Promise<void>;
};

/**
 * Composable générique pour gérer la confirmation avant suppression.
 *
 * Expose un état réactif (pendingDelete, pendingDeleteEnCours) et deux fonctions :
 * - demanderSupprimer : ouvre le dialog avec l'action à confirmer
 * - confirmerSupprimer : exécute l'action et ferme le dialog
 */
export function useConfirmDelete() {
    /** Demande en attente de confirmation (null = dialog fermé). */
    const pendingDelete = ref<PendingDelete | null>(null);

    /** true pendant l'exécution de l'action de suppression confirmée. */
    const pendingDeleteEnCours = ref(false);

    /**
     * Ouvre le dialog de confirmation avec les options fournies.
     */
    function demanderSupprimer(options: PendingDelete): void {
        pendingDelete.value = options;
    }

    /**
     * Exécute l'action confirmée puis ferme le dialog.
     */
    async function confirmerSupprimer(): Promise<void> {
        if (!pendingDelete.value) {
            return;
        }

        pendingDeleteEnCours.value = true;

        try {
            await pendingDelete.value.action();
            pendingDelete.value = null;
        } finally {
            pendingDeleteEnCours.value = false;
        }
    }

    return {
        pendingDelete,
        pendingDeleteEnCours,
        demanderSupprimer,
        confirmerSupprimer,
    };
}
