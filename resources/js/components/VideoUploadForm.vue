<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Film, Upload } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

type Props = {
    uploadUrl: string;
    onSuccess?: () => void;
};

const props = defineProps<Props>();

const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);

const form = useForm({
    titre: '',
    description: '',
    fichier: null as File | null,
});

function handleFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    form.fichier = file;

    // Prévisualisation locale sans upload prématuré.
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }

    previewUrl.value = URL.createObjectURL(file);
}

function submit() {
    form.post(props.uploadUrl, {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            previewUrl.value = null;

            if (fileInput.value) {
                fileInput.value.value = '';
            }

            props.onSuccess?.();
        },
    });
}
</script>

<template>
    <form class="flex flex-col gap-4" @submit.prevent="submit">
        <!-- Titre -->
        <div class="grid gap-1.5">
            <label class="text-sm font-medium" for="video-titre">Titre</label>
            <input
                id="video-titre"
                v-model="form.titre"
                type="text"
                maxlength="255"
                required
                placeholder="Titre de la vidéo…"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
            />
            <p v-if="form.errors.titre" class="text-xs text-destructive">
                {{ form.errors.titre }}
            </p>
        </div>

        <!-- Description (facultatif) -->
        <div class="grid gap-1.5">
            <label class="text-sm font-medium" for="video-description">
                Description
                <span class="font-normal text-muted-foreground"
                    >(optionnel)</span
                >
            </label>
            <textarea
                id="video-description"
                v-model="form.description"
                rows="2"
                maxlength="2000"
                placeholder="Décrivez brièvement votre vidéo…"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
            />
            <p
                v-if="form.errors.description"
                class="text-xs text-destructive"
            >
                {{ form.errors.description }}
            </p>
        </div>

        <!-- Sélecteur de fichier -->
        <input
            ref="fileInput"
            type="file"
            accept=".mp4,.webm,.mov,.avi,.mkv"
            class="hidden"
            @change="handleFileChange"
        />

        <!-- Zone de dépôt -->
        <div
            class="flex cursor-pointer flex-col items-center gap-3 rounded-lg border-2 border-dashed p-6 transition-colors hover:bg-muted/50"
            @click="fileInput?.click()"
        >
            <Film class="h-8 w-8 text-muted-foreground" />
            <div class="text-center">
                <p class="text-sm font-medium">
                    {{
                        form.fichier
                            ? form.fichier.name
                            : 'Cliquer pour choisir une vidéo'
                    }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    MP4, WebM, MOV, AVI, MKV — max 500 Mo
                </p>
            </div>
        </div>

        <p v-if="form.errors.fichier" class="text-xs text-destructive">
            {{ form.errors.fichier }}
        </p>

        <!-- Prévisualisation locale -->
        <video
            v-if="previewUrl"
            :src="previewUrl"
            controls
            class="max-h-48 w-full rounded-lg border bg-black"
        />

        <!-- Barre de progression -->
        <div
            v-if="form.progress"
            class="h-2 w-full overflow-hidden rounded-full bg-muted"
        >
            <div
                class="h-full bg-primary transition-all duration-300"
                :style="{ width: `${form.progress.percentage}%` }"
            />
        </div>

        <Button
            type="submit"
            size="sm"
            :disabled="form.processing || !form.fichier || !form.titre.trim()"
        >
            <Upload class="mr-2 h-4 w-4" />
            {{ form.processing ? 'Envoi en cours…' : 'Envoyer la vidéo' }}
        </Button>
    </form>
</template>
