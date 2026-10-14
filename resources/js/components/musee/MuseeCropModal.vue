<script setup lang="ts">
import Cropper from 'cropperjs'
import type { CropperCanvas, CropperImage, CropperSelection } from 'cropperjs'
import { onMounted, onUnmounted, ref } from 'vue'

export type CropData = { x: number; y: number; width: number; height: number }

const props = defineProps<{
    imageUrl: string
    imageId: number
}>()

const emit = defineEmits<{
    confirmed: [cropData: CropData]
    cancelled: []
}>()

const imageRef = ref<HTMLImageElement | null>(null)
let cropper: Cropper | null = null

onMounted(() => {
    if (!imageRef.value) return
    cropper = new Cropper(imageRef.value)
})

onUnmounted(() => {
    cropper?.destroy()
    cropper = null
})

/**
 * Calcule le point focal de la sélection en pourcentage de l'image naturelle.
 *
 * La sélection CropperJS v2 est en pixels CSS relatifs au canvas. On convertit
 * en coordonnées viewport puis on exprime le centre par rapport à l'élément image.
 */
function confirmer(): void {
    if (!cropper) return

    const canvas = cropper.getCropperCanvas() as CropperCanvas & Element | null
    const image = cropper.getCropperImage() as CropperImage & Element | null
    const selection = cropper.getCropperSelection() as CropperSelection | null

    if (!canvas || !image || !selection) {
        emit('cancelled')
        return
    }

    const canvasRect = canvas.getBoundingClientRect()
    const imageRect = image.getBoundingClientRect()

    // Centre de la sélection en coordonnées viewport
    const cx = canvasRect.left + selection.x + selection.width / 2
    const cy = canvasRect.top + selection.y + selection.height / 2

    // Pourcentage du point focal dans l'image affichée → utilisé par object-position
    const x = Math.round(((cx - imageRect.left) / imageRect.width) * 100)
    const y = Math.round(((cy - imageRect.top) / imageRect.height) * 100)

    emit('confirmed', {
        x: Math.max(0, Math.min(100, x)),
        y: Math.max(0, Math.min(100, y)),
        width: Math.round(selection.width),
        height: Math.round(selection.height),
    })
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">
        <div
            class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl"
        >
            <!-- En-tête -->
            <div class="flex items-center justify-between border-b px-5 py-3.5">
                <div>
                    <p class="text-sm font-semibold">Recadrer l'image</p>
                    <p class="text-xs text-gray-500">
                        Déplacez et redimensionnez la sélection pour définir le point focal.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    title="Annuler"
                    @click="emit('cancelled')"
                >
                    ✕
                </button>
            </div>

            <!-- Zone de crop -->
            <div class="flex-1 overflow-hidden bg-gray-900 p-4">
                <img
                    ref="imageRef"
                    :src="imageUrl"
                    class="block max-h-full max-w-full"
                    alt="Image à recadrer"
                    crossorigin="anonymous"
                />
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-2 border-t px-5 py-3.5">
                <button
                    type="button"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    @click="emit('cancelled')"
                >
                    Annuler
                </button>
                <button
                    type="button"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    @click="confirmer"
                >
                    Confirmer le recadrage
                </button>
            </div>
        </div>
    </div>
</template>
