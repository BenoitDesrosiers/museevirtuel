<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    Pencil,
    Play,
    PlayCircle,
    Square,
    Trash2,
    Upload,
    Video,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type VisioConference = {
    id: number;
    cours_id: number;
    groupe_id: number | null;
    jitsi_room: string;
    titre: string;
    scheduled_at: string | null;
    started_at: string | null;
    ended_at: string | null;
    recording_url: string | null;
    recording_stream_url: string | null;
    has_recording: boolean;
    recording_is_local: boolean;
    animateur: { id: number; prenom: string; nom: string };
};

const props = defineProps<{
    visio: VisioConference;
    canManage: boolean;
    canStart: boolean;
    estTemoin: boolean;
}>();

const statut = computed(() => {
    if (props.visio.ended_at) return 'terminée';
    if (props.visio.started_at) return 'en cours';
    if (props.visio.scheduled_at) return 'planifiée';
    return 'non planifiée';
});

const statutVariant = computed(
    (): 'default' | 'secondary' | 'outline' | 'destructive' => {
        if (props.visio.ended_at) return 'secondary';
        if (props.visio.started_at) return 'default';
        return 'outline';
    },
);

function formatDate(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleString('fr-CA', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

const jitsiUrl = computed(
    () => `https://meet.jit.si/${props.visio.jitsi_room}`,
);

// ─── Rejoindre (nouvel onglet) ─────────────────────────────────────────────────
function rejoindre() {
    window.open(jitsiUrl.value, '_blank', 'noopener,noreferrer');
}

// ─── Démarrer ─────────────────────────────────────────────────────────────────
const demarrerProcessing = ref(false);

function demarrerSession() {
    // window.open doit être appelé synchroniquement depuis l'événement clic
    window.open(jitsiUrl.value, '_blank', 'noopener,noreferrer');

    demarrerProcessing.value = true;
    router.patch(
        `/cours/${props.visio.cours_id}/visio/${props.visio.id}/start`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                demarrerProcessing.value = false;
            },
        },
    );
}

// ─── Terminer ─────────────────────────────────────────────────────────────────
const terminerProcessing = ref(false);

function terminerSession() {
    if (!confirm(`Terminer « ${props.visio.titre} » ?`)) return;

    terminerProcessing.value = true;
    router.patch(
        `/cours/${props.visio.cours_id}/visio/${props.visio.id}/end`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                terminerProcessing.value = false;
            },
        },
    );
}

// ─── Modifier ─────────────────────────────────────────────────────────────────
const showEditDialog = ref(false);
const editForm = useForm({
    titre: props.visio.titre,
    scheduled_at: props.visio.scheduled_at
        ? props.visio.scheduled_at.slice(0, 16)
        : '',
    recording_url: props.visio.recording_url ?? '',
});

function submitEdit() {
    editForm.put(`/cours/${props.visio.cours_id}/visio/${props.visio.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditDialog.value = false;
        },
    });
}

// ─── Supprimer ────────────────────────────────────────────────────────────────
const deleteForm = useForm({});

function supprimerVisio() {
    if (!confirm(`Supprimer « ${props.visio.titre} » ?`)) return;
    deleteForm.delete(
        `/cours/${props.visio.cours_id}/visio/${props.visio.id}`,
        { preserveScroll: true },
    );
}

// ─── Révisionner l'enregistrement (toggle lecteur) ───────────────────────────
const showPlayer = ref(false);

// ─── Upload enregistrement ────────────────────────────────────────────────────
const showUploadDialog = ref(false);
const uploadFile = ref<File | null>(null);
const uploadProcessing = ref(false);
const uploadProgress = ref(0);
const uploadError = ref<string | null>(null);

function handleFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    uploadFile.value = input.files?.[0] ?? null;
    uploadError.value = null;
}

function submitUpload() {
    if (!uploadFile.value) return;

    const data = new FormData();
    data.append('recording', uploadFile.value);

    uploadProcessing.value = true;
    uploadProgress.value = 0;
    uploadError.value = null;

    router.post(
        `/cours/${props.visio.cours_id}/visio/${props.visio.id}/recording`,
        data,
        {
            preserveScroll: true,
            onProgress: (progress) => {
                uploadProgress.value = progress?.percentage ?? 0;
            },
            onSuccess: () => {
                showUploadDialog.value = false;
                uploadFile.value = null;
                uploadProgress.value = 0;
            },
            onError: (errors) => {
                uploadError.value =
                    (errors.recording as string) ??
                    (Object.values(errors)[0] as string) ??
                    "Erreur lors de l'envoi.";
            },
            onFinish: () => {
                uploadProcessing.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="rounded-lg border bg-card p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ visio.titre }}</span>
                    <Badge :variant="statutVariant">{{ statut }}</Badge>
                    <Badge
                        v-if="visio.groupe_id"
                        variant="outline"
                        class="text-xs"
                    >
                        Groupe ciblé
                    </Badge>
                </div>
                <p
                    v-if="visio.scheduled_at"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Prévu : {{ formatDate(visio.scheduled_at) }}
                </p>
                <p
                    v-if="visio.started_at"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Démarrée : {{ formatDate(visio.started_at) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Animateur : {{ visio.animateur.prenom }}
                    {{ visio.animateur.nom }}
                </p>
                <!-- Lien externe (non-local) visible aux non-témoins seulement -->
                <p
                    v-if="
                        !estTemoin &&
                        visio.has_recording &&
                        !visio.recording_is_local &&
                        visio.recording_stream_url
                    "
                    class="mt-1"
                >
                    <a
                        :href="visio.recording_stream_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm text-primary underline-offset-2 hover:underline"
                    >
                        Voir l'enregistrement
                    </a>
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-1">
                <!-- Démarrer : enseignant ou membre, session non encore démarrée -->
                <Button
                    v-if="canStart && !visio.started_at && !visio.ended_at"
                    size="sm"
                    :disabled="demarrerProcessing"
                    @click="demarrerSession"
                >
                    <Play class="mr-1.5 h-4 w-4" />
                    Démarrer
                </Button>

                <!-- Rejoindre : session en cours (pour tous) -->
                <Button
                    v-if="visio.started_at && !visio.ended_at"
                    size="sm"
                    @click="rejoindre"
                >
                    <Video class="mr-1.5 h-4 w-4" />
                    Rejoindre
                </Button>

                <!-- Terminer : enseignant ou membre, session en cours -->
                <Button
                    v-if="canStart && visio.started_at && !visio.ended_at"
                    size="sm"
                    variant="outline"
                    :disabled="terminerProcessing"
                    @click="terminerSession"
                >
                    <Square class="mr-1.5 h-4 w-4" />
                    Terminer
                </Button>

                <!-- Révisionner : membres et enseignant sur sessions terminées avec enregistrement local -->
                <Button
                    v-if="
                        !estTemoin &&
                        visio.ended_at &&
                        visio.has_recording &&
                        visio.recording_is_local
                    "
                    size="sm"
                    variant="outline"
                    @click="showPlayer = !showPlayer"
                >
                    <PlayCircle class="mr-1.5 h-4 w-4" />
                    {{ showPlayer ? 'Masquer' : 'Révisionner' }}
                </Button>

                <!-- Upload enregistrement : enseignant, session terminée -->
                <Button
                    v-if="canManage && visio.ended_at"
                    size="sm"
                    variant="outline"
                    @click="showUploadDialog = true"
                >
                    <Upload class="mr-1.5 h-4 w-4" />
                    {{
                        visio.has_recording
                            ? "Remplacer l'enregistrement"
                            : "Ajouter l'enregistrement"
                    }}
                </Button>

                <template v-if="canManage">
                    <Button
                        size="sm"
                        variant="ghost"
                        class="h-8 w-8 p-0"
                        @click="showEditDialog = true"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        class="h-8 w-8 p-0 text-destructive hover:text-destructive"
                        :disabled="deleteForm.processing"
                        @click="supprimerVisio"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </template>
            </div>
        </div>

        <!-- Lecteur vidéo intégré — affiché uniquement si le témoin n'est pas l'utilisateur et le player est ouvert -->
        <div
            v-if="
                !estTemoin &&
                showPlayer &&
                visio.recording_is_local &&
                visio.recording_stream_url
            "
            class="mt-4"
        >
            <video
                controls
                preload="metadata"
                class="w-full rounded-md"
                :src="visio.recording_stream_url"
            >
                Votre navigateur ne supporte pas la lecture vidéo.
            </video>
        </div>
    </div>

    <!-- Dialog : Modifier la visio -->
    <Dialog v-model:open="showEditDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Modifier la visioconférence</DialogTitle>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitEdit">
                <div class="grid gap-2">
                    <Label for="edit-titre">Titre</Label>
                    <Input id="edit-titre" v-model="editForm.titre" required />
                    <p
                        v-if="editForm.errors.titre"
                        class="text-sm text-destructive"
                    >
                        {{ editForm.errors.titre }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="edit-scheduled"
                        >Date planifiée (optionnel)</Label
                    >
                    <Input
                        id="edit-scheduled"
                        v-model="editForm.scheduled_at"
                        type="datetime-local"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-recording"
                        >URL externe de l'enregistrement (optionnel)</Label
                    >
                    <Input
                        id="edit-recording"
                        v-model="editForm.recording_url"
                        type="url"
                        placeholder="https://..."
                    />
                    <p
                        v-if="editForm.errors.recording_url"
                        class="text-sm text-destructive"
                    >
                        {{ editForm.errors.recording_url }}
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showEditDialog = false"
                    >
                        Annuler
                    </Button>
                    <Button type="submit" :disabled="editForm.processing">
                        Enregistrer
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Dialog : Upload enregistrement -->
    <Dialog v-model:open="showUploadDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{
                        visio.has_recording
                            ? "Remplacer l'enregistrement"
                            : "Ajouter l'enregistrement"
                    }}
                </DialogTitle>
            </DialogHeader>
            <div class="space-y-4">
                <div class="grid gap-2">
                    <Label for="upload-recording"
                        >Fichier vidéo (mp4, mov, webm, avi — max 1 Go)</Label
                    >
                    <Input
                        id="upload-recording"
                        type="file"
                        accept="video/mp4,video/quicktime,video/webm,video/x-msvideo"
                        :disabled="uploadProcessing"
                        @change="handleFileChange"
                    />
                    <p v-if="uploadError" class="text-sm text-destructive">
                        {{ uploadError }}
                    </p>
                </div>
                <div v-if="uploadProcessing" class="space-y-1">
                    <p class="text-sm text-muted-foreground">
                        Envoi en cours… {{ uploadProgress }}%
                    </p>
                    <div class="h-2 overflow-hidden rounded-full bg-secondary">
                        <div
                            class="h-full rounded-full bg-primary transition-all duration-300"
                            :style="{ width: `${uploadProgress}%` }"
                        />
                    </div>
                </div>
            </div>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="uploadProcessing"
                    @click="showUploadDialog = false"
                >
                    Annuler
                </Button>
                <Button
                    type="button"
                    :disabled="!uploadFile || uploadProcessing"
                    @click="submitUpload"
                >
                    <Upload class="mr-1.5 h-4 w-4" />
                    Envoyer
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
