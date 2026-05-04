<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Pencil, Trash2, Video, VideoOff } from 'lucide-vue-next';
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
    animateur: { id: number; prenom: string; nom: string };
};

const props = defineProps<{
    visio: VisioConference;
    canManage: boolean;
}>();

const actif = ref(false);

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
</script>

<template>
    <div class="rounded-lg border bg-card p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ visio.titre }}</span>
                    <Badge :variant="statutVariant">{{ statut }}</Badge>
                    <Badge v-if="visio.groupe_id" variant="outline" class="text-xs">
                        Groupe ciblé
                    </Badge>
                </div>
                <p v-if="visio.scheduled_at" class="mt-1 text-sm text-muted-foreground">
                    Prévu : {{ formatDate(visio.scheduled_at) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Animateur : {{ visio.animateur.prenom }} {{ visio.animateur.nom }}
                </p>
                <p v-if="visio.recording_url" class="mt-1">
                    <a
                        :href="visio.recording_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm text-primary underline-offset-2 hover:underline"
                    >
                        Enregistrement disponible
                    </a>
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                <Button
                    size="sm"
                    :variant="actif ? 'destructive' : 'default'"
                    @click="actif = !actif"
                >
                    <VideoOff v-if="actif" class="mr-1.5 h-4 w-4" />
                    <Video v-else class="mr-1.5 h-4 w-4" />
                    {{ actif ? 'Quitter' : 'Rejoindre' }}
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

        <!-- Iframe Jitsi -->
        <div v-if="actif" class="mt-4">
            <p class="mb-2 text-xs text-muted-foreground">
                L'enregistrement n'est pas garanti sur meet.jit.si public.
            </p>
            <iframe
                :src="jitsiUrl"
                allow="camera; microphone; display-capture; fullscreen"
                class="h-[600px] w-full rounded-md border"
                title="Visioconférence Jitsi"
            />
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
                    <p v-if="editForm.errors.titre" class="text-sm text-destructive">
                        {{ editForm.errors.titre }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="edit-scheduled">Date planifiée (optionnel)</Label>
                    <Input
                        id="edit-scheduled"
                        v-model="editForm.scheduled_at"
                        type="datetime-local"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-recording">URL de l'enregistrement (optionnel)</Label>
                    <Input
                        id="edit-recording"
                        v-model="editForm.recording_url"
                        type="url"
                        placeholder="https://..."
                    />
                    <p v-if="editForm.errors.recording_url" class="text-sm text-destructive">
                        {{ editForm.errors.recording_url }}
                    </p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showEditDialog = false">
                        Annuler
                    </Button>
                    <Button type="submit" :disabled="editForm.processing">
                        Enregistrer
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
