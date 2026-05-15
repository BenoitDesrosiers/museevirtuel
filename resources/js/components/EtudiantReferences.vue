<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { BookMarked, Check, Copy, ExternalLink, Link2, Loader2, Plus, RefreshCw, Settings, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import referencesRoutes from '@/routes/etudiant/references';
import credentialRoutes from '@/routes/etudiant/zotero/credential';

type Reference = {
    id: number;
    titre: string;
    auteurs: { prenom: string; nom: string }[] | null;
    annee: number | null;
    type_source: string | null;
    url: string | null;
    doi: string | null;
    publication: string | null;
    ordre: number;
    est_depuis_zotero: boolean;
};

type ZoteroConfig = {
    configure: boolean;
    synchronise_le: string | null;
};

const props = defineProps<{
    references: Reference[];
    zoteroConfig: ZoteroConfig;
}>();

// ─── Ajouter une référence manuellement ───────────────────────────────────────
const showAddForm = ref(false);
const addForm = useForm({ titre: '', url: '' });

/**
 * Soumet le formulaire d'ajout d'une référence manuelle.
 */
function submitAdd() {
    addForm.post(referencesRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAddForm.value = false;
        },
    });
}

// ─── Supprimer une référence ──────────────────────────────────────────────────
const deleteForm = useForm({});

/**
 * Supprime une référence après confirmation.
 */
function supprimerReference(reference: Reference) {
    if (! confirm(`Supprimer « ${reference.titre.slice(0, 60)} » ?`)) return;

    deleteForm.delete(referencesRoutes.destroy.url(reference.id), {
        preserveScroll: true,
    });
}

// ─── Synchronisation Zotero ───────────────────────────────────────────────────
const syncForm = useForm({});

/**
 * Lance la synchronisation avec la bibliothèque Zotero de l'étudiant.
 */
function syncZotero() {
    syncForm.post(referencesRoutes.sync.url(), { preserveScroll: true });
}

// ─── Configuration Zotero ─────────────────────────────────────────────────────
const showZoteroSetup = ref(false);
const credentialForm = useForm({ zotero_user_id: '', api_key: '' });

/**
 * Sauvegarde les credentials Zotero après validation côté serveur.
 */
function saveCredential() {
    credentialForm.post(credentialRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            credentialForm.reset();
            showZoteroSetup.value = false;
        },
    });
}

const destroyCredentialForm = useForm({});

/**
 * Déconnecte le compte Zotero et supprime les références importées.
 */
function destroyCredential() {
    if (! confirm('Déconnecter votre compte Zotero ? Les références importées de Zotero seront supprimées.')) return;

    destroyCredentialForm.delete(credentialRoutes.destroy.url(), { preserveScroll: true });
}

// ─── Aperçu APA ───────────────────────────────────────────────────────────────

/** ID de la référence dont l'aperçu APA est ouvert (null = aucun). */
const referenceExpandee = ref<number | null>(null);

/** ID de la référence dont le texte vient d'être copié (pour le feedback "Copié !"). */
const copieId = ref<number | null>(null);

/**
 * Bascule l'aperçu APA d'une référence (ouvre si fermé, ferme si déjà ouvert).
 */
function toggleApercu(id: number): void {
    referenceExpandee.value = referenceExpandee.value === id ? null : id;
}

/**
 * Formate les auteurs d'une référence en une chaîne lisible.
 */
function formatAuteurs(auteurs: Reference['auteurs']): string {
    if (! auteurs || auteurs.length === 0) return '';

    return (
        auteurs
            .slice(0, 3)
            .map((a) => `${a.nom}${a.prenom ? ', ' + a.prenom.charAt(0) + '.' : ''}`)
            .join('; ') + (auteurs.length > 3 ? ' et al.' : '')
    );
}

/**
 * Formate un tableau d'auteurs au format APA : « Nom, P. » ou « Nom, P., & Nom2, Q. ».
 */
function formatAuteursApa(auteurs: Reference['auteurs']): string {
    if (! auteurs || auteurs.length === 0) return '';
    const formattes = auteurs.map((a) => {
        const initiale = a.prenom ? `${a.prenom.charAt(0).toUpperCase()}.` : '';
        return initiale ? `${a.nom}, ${initiale}` : a.nom;
    });
    if (formattes.length === 1) return formattes[0];
    const dernier = formattes.pop()!;
    return `${formattes.join(', ')}, & ${dernier}`;
}

/**
 * Génère un aperçu APA en meilleur effort à partir des données disponibles.
 *
 * Certains champs (volume, numéro, pages) ne sont pas stockés — l'aperçu peut être partiel.
 */
function apercuApa(ref: Reference): string {
    const auteursStr = formatAuteursApa(ref.auteurs);
    const annee = ref.annee ? String(ref.annee) : 's.d.';

    switch (ref.type_source) {
        case 'journalArticle': {
            const revue = ref.publication ? ` <em>${ref.publication}</em>.` : '';
            const lien = ref.doi ? ` ${ref.doi}` : ref.url ? ` ${ref.url}` : '';
            return `${auteursStr}. (${annee}). ${ref.titre}.${revue}${lien}`;
        }
        case 'book': {
            const editeur = ref.publication ? ` ${ref.publication}.` : '';
            return `${auteursStr}. (${annee}). <em>${ref.titre}</em>.${editeur}`;
        }
        case 'webpage': {
            const site = ref.publication ? ` <em>${ref.publication}</em>.` : '';
            const url = ref.url ? ` ${ref.url}` : '';
            return `${auteursStr}. (${annee}). ${ref.titre}.${site}${url}`;
        }
        case 'thesis': {
            const url = ref.url ? ` ${ref.url}` : '';
            return `${auteursStr}. (${annee}). <em>${ref.titre}</em>.${url}`;
        }
        case 'videoRecording':
        case 'film': {
            const url = ref.url ? ` ${ref.url}` : '';
            return `${auteursStr}. (${annee}). <em>${ref.titre}</em>.${url}`;
        }
        case 'newspaperArticle': {
            const journal = ref.publication ? ` <em>${ref.publication}</em>.` : '';
            const url = ref.url ? ` ${ref.url}` : '';
            return `${auteursStr}. ${ref.titre}.${journal}${url}`;
        }
        default: {
            const publication = ref.publication ? ` ${ref.publication}.` : '';
            const lien = ref.doi ? ` ${ref.doi}` : ref.url ? ` ${ref.url}` : '';
            return `${auteursStr}. (${annee}). <em>${ref.titre}</em>.${publication}${lien}`;
        }
    }
}

/**
 * Copie l'aperçu APA dans le presse-papiers (balises HTML retirées pour texte brut).
 */
async function copierApa(ref: Reference): Promise<void> {
    const texte = apercuApa(ref).replace(/<[^>]+>/g, '');
    try {
        await navigator.clipboard.writeText(texte);
    } catch {
        // Clipboard API indisponible (contexte non sécurisé) — échec silencieux
    }
    copieId.value = ref.id;
    setTimeout(() => (copieId.value = null), 2000);
}

/**
 * Formate la date de la dernière synchronisation Zotero.
 */
function formatSyncDate(iso: string | null): string {
    if (! iso) return 'jamais';

    return new Date(iso).toLocaleString('fr-CA', { dateStyle: 'medium', timeStyle: 'short' });
}
</script>

<template>
    <div class="space-y-3">
        <!-- Liste des références -->
        <div v-if="references.length === 0 && !showAddForm" class="py-4 text-center text-sm text-muted-foreground">
            Aucune référence personnelle. Ajoutez-en une ou synchronisez votre bibliothèque Zotero.
        </div>

        <ol v-else class="space-y-1.5">
            <li
                v-for="reference in [...references].sort((a, b) => a.ordre - b.ordre)"
                :key="reference.id"
                class="overflow-hidden rounded-md border bg-card"
            >
                <!-- Ligne principale : clic → aperçu APA -->
                <button
                    type="button"
                    class="flex w-full items-start gap-2 px-3 py-2 text-left transition-colors hover:bg-muted/30"
                    :class="{ 'bg-muted/30': referenceExpandee === reference.id }"
                    @click="toggleApercu(reference.id)"
                >
                    <BookMarked class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />

                    <div class="min-w-0 flex-1">
                        <span class="text-sm font-medium leading-snug">{{ reference.titre }}</span>

                        <!-- Méta : auteurs, publication, année -->
                        <p class="mt-0.5 truncate text-xs text-muted-foreground">
                            <span v-if="reference.auteurs?.length">{{ formatAuteurs(reference.auteurs) }}</span>
                            <span v-if="reference.publication">
                                {{ reference.auteurs?.length ? ' · ' : '' }}{{ reference.publication }}
                            </span>
                            <span v-if="reference.annee">
                                {{ (reference.auteurs?.length || reference.publication) ? ' · ' : '' }}{{ reference.annee }}
                            </span>
                        </p>

                        <!-- Badge Zotero -->
                        <span
                            v-if="reference.est_depuis_zotero"
                            class="mt-1 inline-flex items-center gap-1 rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                        >
                            <Link2 class="h-2.5 w-2.5" />
                            Zotero
                        </span>
                    </div>

                    <!-- Actions (clic isolé pour ne pas déclencher le toggle) -->
                    <div class="flex shrink-0 items-center gap-1" @click.stop>
                        <a
                            v-if="reference.url"
                            :href="reference.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-primary"
                            title="Ouvrir le lien"
                        >
                            <ExternalLink class="h-3.5 w-3.5" />
                        </a>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-7 w-7 shrink-0 p-0 text-muted-foreground hover:text-destructive"
                            :disabled="deleteForm.processing"
                            @click="supprimerReference(reference)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </button>

                <!-- Aperçu APA extensible -->
                <div v-if="referenceExpandee === reference.id" class="border-t bg-muted/40 px-3 py-3">
                    <p class="mb-1.5 text-xs font-medium text-muted-foreground">Aperçu APA</p>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <p class="text-sm leading-relaxed" v-html="apercuApa(reference)" />
                    <div class="mt-2.5 flex justify-end">
                        <Button size="sm" variant="outline" @click="copierApa(reference)">
                            <Copy class="mr-1.5 h-3.5 w-3.5" />
                            {{ copieId === reference.id ? 'Copié !' : 'Copier' }}
                        </Button>
                    </div>
                </div>
            </li>
        </ol>

        <!-- Formulaire d'ajout inline -->
        <div v-if="showAddForm" class="space-y-2 rounded-md border bg-card px-3 py-2">
            <div class="flex items-start gap-2">
                <div class="flex flex-1 flex-col gap-1.5">
                    <Label class="sr-only" for="new-ref-titre">Titre</Label>
                    <Input
                        id="new-ref-titre"
                        v-model="addForm.titre"
                        class="h-7 text-sm"
                        placeholder="Titre de l'article ou de l'ouvrage…"
                        maxlength="500"
                        autofocus
                        @keydown.escape="showAddForm = false"
                    />
                    <InputError :message="addForm.errors.titre" />
                    <Label class="sr-only" for="new-ref-url">URL (optionnel)</Label>
                    <Input
                        id="new-ref-url"
                        v-model="addForm.url"
                        class="h-7 text-sm"
                        placeholder="https://…"
                        type="url"
                        maxlength="500"
                        @keydown.enter.prevent="submitAdd"
                        @keydown.escape="showAddForm = false"
                    />
                    <InputError :message="addForm.errors.url" />
                </div>
                <Button size="sm" variant="ghost" class="h-7 w-7 shrink-0 p-0" :disabled="addForm.processing || !addForm.titre.trim()" @click="submitAdd">
                    <Check class="h-3.5 w-3.5 text-green-600" />
                </Button>
                <Button size="sm" variant="ghost" class="h-7 w-7 shrink-0 p-0" @click="showAddForm = false; addForm.reset()">
                    <X class="h-3.5 w-3.5" />
                </Button>
            </div>
        </div>

        <!-- Actions : Ajouter + Synchroniser Zotero -->
        <div class="flex flex-wrap gap-2">
            <Button
                v-if="!showAddForm"
                size="sm"
                variant="outline"
                @click="showAddForm = true"
            >
                <Plus class="mr-1.5 h-3.5 w-3.5" />
                Ajouter
            </Button>

            <Button
                v-if="zoteroConfig.configure"
                size="sm"
                variant="outline"
                :disabled="syncForm.processing"
                @click="syncZotero"
            >
                <Loader2 v-if="syncForm.processing" class="mr-1.5 h-3.5 w-3.5 animate-spin" />
                <RefreshCw v-else class="mr-1.5 h-3.5 w-3.5" />
                Sync Zotero
            </Button>

            <Button
                size="sm"
                variant="ghost"
                class="text-muted-foreground"
                @click="showZoteroSetup = !showZoteroSetup"
            >
                <Settings class="mr-1.5 h-3.5 w-3.5" />
                {{ zoteroConfig.configure ? 'Compte Zotero' : 'Connecter Zotero' }}
            </Button>
        </div>

        <!-- Infos dernière sync -->
        <p v-if="zoteroConfig.configure && zoteroConfig.synchronise_le" class="text-xs text-muted-foreground">
            Dernière synchronisation : {{ formatSyncDate(zoteroConfig.synchronise_le) }}
        </p>

        <!-- Assistant de configuration Zotero -->
        <div v-if="showZoteroSetup" class="space-y-4 rounded-md border bg-muted/30 p-4">
            <div v-if="!zoteroConfig.configure">
                <h4 class="mb-1 text-sm font-medium">Connecter votre bibliothèque Zotero</h4>
                <ol class="mb-3 list-inside list-decimal space-y-1 text-xs text-muted-foreground">
                    <li>Créez un compte gratuit sur <a href="https://www.zotero.org" target="_blank" rel="noopener noreferrer" class="underline">zotero.org</a></li>
                    <li>Allez dans <strong>Paramètres → Flux/API</strong></li>
                    <li>Cliquez sur <strong>Créer une nouvelle clé privée</strong> (accès lecture seule)</li>
                    <li>Copiez votre <strong>ID utilisateur</strong> et la <strong>clé API</strong> ci-dessous</li>
                </ol>

                <div class="space-y-2">
                    <div class="grid gap-1.5">
                        <Label for="zotero-user-id" class="text-xs">ID utilisateur Zotero</Label>
                        <Input
                            id="zotero-user-id"
                            v-model="credentialForm.zotero_user_id"
                            class="h-8 font-mono text-sm"
                            placeholder="12345678"
                        />
                        <InputError :message="credentialForm.errors.zotero_user_id" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="zotero-api-key" class="text-xs">Clé API</Label>
                        <Input
                            id="zotero-api-key"
                            v-model="credentialForm.api_key"
                            class="h-8 font-mono text-sm"
                            placeholder="AbCdEfGhIjKlMnOpQrStUvWx"
                            type="password"
                        />
                        <InputError :message="credentialForm.errors.api_key" />
                    </div>

                    <div class="flex gap-2 pt-1">
                        <Button
                            size="sm"
                            :disabled="credentialForm.processing || !credentialForm.zotero_user_id || !credentialForm.api_key"
                            @click="saveCredential"
                        >
                            <Loader2 v-if="credentialForm.processing" class="mr-1.5 h-3.5 w-3.5 animate-spin" />
                            <Check v-else class="mr-1.5 h-3.5 w-3.5" />
                            Vérifier et connecter
                        </Button>
                        <Button size="sm" variant="ghost" @click="showZoteroSetup = false">
                            Annuler
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Compte déjà configuré -->
            <div v-else class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-green-700 dark:text-green-400">Compte Zotero connecté</p>
                    <p class="text-xs text-muted-foreground">
                        Dernière sync : {{ formatSyncDate(zoteroConfig.synchronise_le) }}
                    </p>
                </div>
                <Button
                    size="sm"
                    variant="destructive"
                    :disabled="destroyCredentialForm.processing"
                    @click="destroyCredential"
                >
                    Déconnecter
                </Button>
            </div>
        </div>
    </div>
</template>
