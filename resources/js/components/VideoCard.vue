<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Edit2, Film, Play, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { formatDuree } from '@/lib/formatters';

type Auteur = {
    id: number;
    prenom: string;
    nom: string;
};

type Video = {
    id: number;
    titre: string;
    statut: 'brouillon' | 'publié' | 'archivé';
    traitement_statut: string | null;
    duree: number | null;
    taille: number;
    thumbnail_url: string | null;
    url: string;
    user_id: number;
    auteur: Auteur;
    created_at: string;
};

type Props = {
    video: Video;
    showUrl: string;
    publierUrl: string;
    destroyUrl: string;
    peutPublier: boolean;
    peutSupprimer: boolean;
};

const props = defineProps<Props>();

const publierForm = useForm({});
const deleteForm = useForm({});

function publier() {
    publierForm.post(props.publierUrl, { preserveScroll: true });
}

function supprimer() {
    if (!confirm(`Supprimer la vidéo « ${props.video.titre} » ?`)) {
        return;
    }

    deleteForm.delete(props.destroyUrl);
}

function formatSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} Ko`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
}

const statutClasses: Record<string, string> = {
    brouillon: 'bg-amber-100 text-amber-800',
    publié: 'bg-green-100 text-green-800',
    archivé: 'bg-muted text-muted-foreground',
};
</script>

<template>
    <div class="flex flex-col overflow-hidden rounded-lg border bg-card">
        <!-- Miniature ou placeholder -->
        <div class="relative aspect-video overflow-hidden bg-black">
            <img
                v-if="video.thumbnail_url"
                :src="video.thumbnail_url"
                :alt="video.titre"
                class="h-full w-full object-cover"
            />
            <div v-else class="flex h-full w-full items-center justify-center">
                <Film class="h-10 w-10 text-white/40" />
            </div>

            <!-- Badge traitement en cours -->
            <div
                v-if="
                    video.traitement_statut === 'en_attente' ||
                    video.traitement_statut === 'en_cours'
                "
                class="absolute inset-0 flex items-center justify-center bg-black/50"
            >
                <p class="text-sm font-medium text-white">
                    Traitement en cours…
                </p>
            </div>

            <!-- Badge statut -->
            <span
                class="absolute top-2 left-2 rounded px-1.5 py-0.5 text-xs font-medium"
                :class="statutClasses[video.statut]"
            >
                {{ video.statut }}
            </span>
        </div>

        <!-- Informations -->
        <div class="flex flex-1 flex-col gap-2 p-3">
            <p class="line-clamp-2 text-sm leading-snug font-medium">
                {{ video.titre }}
            </p>
            <p class="text-xs text-muted-foreground">
                <span>{{ video.auteur.prenom }} {{ video.auteur.nom }}</span>
                <span v-if="video.duree">
                    · {{ formatDuree(video.duree) }}</span
                >
                <span> · {{ formatSize(video.taille) }}</span>
            </p>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2 border-t px-3 py-2">
            <Button size="sm" variant="outline" as-child>
                <Link :href="showUrl">
                    <Play class="mr-1.5 h-3.5 w-3.5" />
                    Voir / Éditer
                </Link>
            </Button>

            <Button
                v-if="peutPublier && video.statut === 'brouillon'"
                size="sm"
                variant="outline"
                :disabled="publierForm.processing"
                @click="publier"
            >
                Publier
            </Button>

            <Button
                v-if="peutSupprimer"
                size="sm"
                variant="ghost"
                class="ml-auto text-destructive hover:text-destructive"
                :disabled="deleteForm.processing"
                @click="supprimer"
            >
                <Trash2 class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
