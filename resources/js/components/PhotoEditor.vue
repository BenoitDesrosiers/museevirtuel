<script setup lang="ts">
/**
 * Éditeur de photo en ligne : rogner, pivoter, retourner.
 *
 * Stratégie :
 * - Rotation / flip  → boutons instantanés (POST direct au serveur).
 * - Rognage          → drag souris pour dessiner la zone à conserver.
 *   getBoundingClientRect() sur l'<img> convertit les coordonnées affichées
 *   en pixels naturels envoyés au serveur (Intervention Image applique le crop).
 */
import { useForm } from '@inertiajs/vue3';
import { FlipHorizontal2, FlipVertical2, RotateCcw, RotateCw } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

type Props = {
    /** URL publique de la photo à éditer. */
    photoUrl: string;
    /** URL du endpoint POST /…/editer. */
    editUrl: string;
    /** Appelé après un succès pour fermer le dialog parent. */
    onSuccess?: () => void;
};

const props = defineProps<Props>();

// ─── Dimensions naturelles de l'image ────────────────────────────────────────
const imgNaturalW = ref(0);
const imgNaturalH = ref(0);
const imgEl = ref<HTMLImageElement | null>(null);

function onImageLoad() {
    if (!imgEl.value) return;
    imgNaturalW.value = imgEl.value.naturalWidth;
    imgNaturalH.value = imgEl.value.naturalHeight;

    // Initialise le crop sur toute la surface.
    cropForm.x = 0;
    cropForm.y = 0;
    cropForm.width = imgEl.value.naturalWidth;
    cropForm.height = imgEl.value.naturalHeight;
}

onMounted(() => {
    if (imgEl.value?.complete) onImageLoad();
});

// ─── Onglet actif ─────────────────────────────────────────────────────────────
type Onglet = 'crop' | 'rotate' | 'flip';
const onglet = ref<Onglet>('rotate');

// ─── Formulaire de rotation / flip ───────────────────────────────────────────
const transformForm = useForm({
    operation: '' as 'rotate' | 'flip',
    angle: null as number | null,
    direction: null as 'horizontal' | 'vertical' | null,
});

function appliquerRotation(angle: 90 | 180 | 270) {
    transformForm.operation = 'rotate';
    transformForm.angle = angle;
    transformForm.direction = null;
    soumettre(transformForm);
}

function appliquerFlip(direction: 'horizontal' | 'vertical') {
    transformForm.operation = 'flip';
    transformForm.direction = direction;
    transformForm.angle = null;
    soumettre(transformForm);
}

// ─── Formulaire de rognage ────────────────────────────────────────────────────
const cropForm = useForm({
    operation: 'crop' as const,
    x: 0,
    y: 0,
    width: 0,
    height: 0,
});

/** Aperçu CSS : position/taille de la région gardée, en pourcentage de l'image affichée. */
const cropPreviewStyle = computed(() => {
    if (!imgNaturalW.value || !imgNaturalH.value) return {};
    return {
        left: `${(cropForm.x / imgNaturalW.value) * 100}%`,
        top: `${(cropForm.y / imgNaturalH.value) * 100}%`,
        width: `${(cropForm.width / imgNaturalW.value) * 100}%`,
        height: `${(cropForm.height / imgNaturalH.value) * 100}%`,
    };
});

// ─── Drag souris pour définir la zone de rognage ─────────────────────────────
const isDragging = ref(false);
const dragOriginX = ref(0);
const dragOriginY = ref(0);

/**
 * Convertit les coordonnées viewport d'un PointerEvent en pixels naturels
 * de l'image originale, bornées à [0, naturalWidth/Height].
 */
function versCoordNaturelles(e: PointerEvent): { x: number; y: number } {
    if (!imgEl.value) return { x: 0, y: 0 };

    const rect = imgEl.value.getBoundingClientRect();
    const scaleX = imgNaturalW.value / rect.width;
    const scaleY = imgNaturalH.value / rect.height;

    return {
        x: Math.max(0, Math.min((e.clientX - rect.left) * scaleX, imgNaturalW.value)),
        y: Math.max(0, Math.min((e.clientY - rect.top) * scaleY, imgNaturalH.value)),
    };
}

function onPointerDown(e: PointerEvent) {
    if (onglet.value !== 'crop' || !imgNaturalW.value) return;

    e.preventDefault();
    // Capture pour recevoir pointermove/up même si la souris sort de l'élément.
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);

    const { x, y } = versCoordNaturelles(e);
    dragOriginX.value = x;
    dragOriginY.value = y;
    cropForm.x = Math.round(x);
    cropForm.y = Math.round(y);
    cropForm.width = 0;
    cropForm.height = 0;
    isDragging.value = true;
}

function onPointerMove(e: PointerEvent) {
    if (!isDragging.value) return;

    const { x, y } = versCoordNaturelles(e);
    cropForm.x = Math.round(Math.min(x, dragOriginX.value));
    cropForm.y = Math.round(Math.min(y, dragOriginY.value));
    cropForm.width = Math.round(Math.abs(x - dragOriginX.value));
    cropForm.height = Math.round(Math.abs(y - dragOriginY.value));
}

function onPointerUp() {
    if (!isDragging.value) return;
    isDragging.value = false;
    // Garantit une sélection d'au moins 1px.
    if (cropForm.width < 1) cropForm.width = 1;
    if (cropForm.height < 1) cropForm.height = 1;
}

function appliquerCrop() {
    soumettre(cropForm);
}

// ─── Soumission générique ─────────────────────────────────────────────────────
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function soumettre(form: any) {
    form.post(props.editUrl, {
        preserveScroll: true,
        onSuccess: () => props.onSuccess?.(),
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Image avec overlay de rognage -->
        <div class="flex justify-center rounded-lg border bg-muted p-1">
            <div
                class="relative inline-block select-none overflow-hidden rounded"
                :class="onglet === 'crop' && imgNaturalW > 0 ? 'cursor-crosshair' : ''"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
            >
                <img
                    ref="imgEl"
                    :src="photoUrl"
                    alt="Photo à éditer"
                    class="block max-h-[300px] max-w-full"
                    draggable="false"
                    @load="onImageLoad"
                />

                <!-- Overlay de rognage (visible uniquement sur l'onglet crop) -->
                <div
                    v-if="onglet === 'crop' && imgNaturalW > 0"
                    class="pointer-events-none absolute inset-0"
                >
                    <!-- Zone exclue (assombrie) -->
                    <div class="absolute inset-0 bg-black/50" />
                    <!-- Zone conservée (découpe transparente) -->
                    <div
                        class="absolute border-2 border-white bg-transparent shadow-[0_0_0_9999px_transparent]"
                        :style="cropPreviewStyle"
                    />
                </div>
            </div>
        </div>

        <!-- Onglets -->
        <div class="flex gap-1 rounded-lg border p-1">
            <button
                v-for="tab in [
                    { key: 'rotate', label: 'Pivoter' },
                    { key: 'flip', label: 'Retourner' },
                    { key: 'crop', label: 'Rogner' },
                ] as { key: Onglet; label: string }[]"
                :key="tab.key"
                type="button"
                class="flex-1 rounded px-3 py-1.5 text-sm font-medium transition-colors"
                :class="
                    onglet === tab.key
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-muted'
                "
                @click="onglet = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- ─── Onglet Pivoter ──────────────────────────────────────────────── -->
        <div v-if="onglet === 'rotate'" class="grid grid-cols-3 gap-2">
            <Button variant="outline" :disabled="transformForm.processing" @click="appliquerRotation(90)">
                <RotateCcw class="mr-2 h-4 w-4" />
                90°
            </Button>
            <Button variant="outline" :disabled="transformForm.processing" @click="appliquerRotation(180)">
                180°
            </Button>
            <Button variant="outline" :disabled="transformForm.processing" @click="appliquerRotation(270)">
                <RotateCw class="mr-2 h-4 w-4" />
                270°
            </Button>
        </div>

        <!-- ─── Onglet Retourner ────────────────────────────────────────────── -->
        <div v-if="onglet === 'flip'" class="grid grid-cols-2 gap-2">
            <Button variant="outline" :disabled="transformForm.processing" @click="appliquerFlip('horizontal')">
                <FlipHorizontal2 class="mr-2 h-4 w-4" />
                Horizontal
            </Button>
            <Button variant="outline" :disabled="transformForm.processing" @click="appliquerFlip('vertical')">
                <FlipVertical2 class="mr-2 h-4 w-4" />
                Vertical
            </Button>
        </div>

        <!-- ─── Onglet Rogner ───────────────────────────────────────────────── -->
        <div v-if="onglet === 'crop'" class="flex flex-col gap-3">
            <p v-if="imgNaturalW === 0" class="text-sm text-muted-foreground">
                Chargement des dimensions…
            </p>

            <p v-else class="rounded bg-muted px-3 py-2 text-xs text-muted-foreground">
                Glissez sur l'image pour définir la zone à conserver.
                <span v-if="cropForm.width > 0 && cropForm.height > 0" class="font-medium text-foreground">
                    {{ cropForm.width }} × {{ cropForm.height }} px
                </span>
            </p>

            <p class="rounded bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                Cette action est permanente et remplace la photo originale.
            </p>

            <Button
                :disabled="cropForm.processing || imgNaturalW === 0 || cropForm.width < 1 || cropForm.height < 1"
                @click="appliquerCrop"
            >
                {{ cropForm.processing ? 'Application…' : 'Appliquer le rognage' }}
            </Button>
        </div>
    </div>
</template>
