<template>
    <div class="flex flex-col gap-3">
        <canvas
            ref="canvasRef"
            :width="width"
            :height="height"
            class="w-full cursor-crosshair touch-none rounded-md border border-gray-300 bg-white"
            style="max-width: 100%"
            @mousedown="startDrawing"
            @mousemove="draw"
            @mouseup="stopDrawing"
            @mouseleave="stopDrawing"
            @touchstart.prevent="startDrawingTouch"
            @touchmove.prevent="drawTouch"
            @touchend="stopDrawing"
        />

        <div class="flex gap-2">
            <button
                type="button"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100"
                @click="clear"
            >
                {{ $t('signature.clear') }}
            </button>
            <span
                v-if="isEmpty"
                class="self-center text-xs text-gray-400 italic"
            >
                {{ $t('signature.hint') }}
            </span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string | null;
        width?: number;
        height?: number;
    }>(),
    {
        modelValue: null,
        width: 600,
        height: 200,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const isDrawing = ref(false);
const isEmpty = ref(true);
let lastX = 0;
let lastY = 0;

function getContext(): CanvasRenderingContext2D | null {
    return canvasRef.value?.getContext('2d') ?? null;
}

function getCanvasCoords(e: MouseEvent): { x: number; y: number } {
    const rect = canvasRef.value!.getBoundingClientRect();
    // Ajuster pour la différence entre taille CSS et taille réelle du canvas
    const scaleX = props.width / rect.width;
    const scaleY = props.height / rect.height;

    return {
        x: (e.clientX - rect.left) * scaleX,
        y: (e.clientY - rect.top) * scaleY,
    };
}

function getTouchCoords(e: TouchEvent): { x: number; y: number } {
    const rect = canvasRef.value!.getBoundingClientRect();
    const touch = e.touches[0];
    const scaleX = props.width / rect.width;
    const scaleY = props.height / rect.height;

    return {
        x: (touch.clientX - rect.left) * scaleX,
        y: (touch.clientY - rect.top) * scaleY,
    };
}

function startDrawing(e: MouseEvent): void {
    const { x, y } = getCanvasCoords(e);
    isDrawing.value = true;
    lastX = x;
    lastY = y;
}

function startDrawingTouch(e: TouchEvent): void {
    const { x, y } = getTouchCoords(e);
    isDrawing.value = true;
    lastX = x;
    lastY = y;
}

function draw(e: MouseEvent): void {
    if (!isDrawing.value) {
        return;
    }

    const { x, y } = getCanvasCoords(e);
    drawLine(lastX, lastY, x, y);
    lastX = x;
    lastY = y;
}

function drawTouch(e: TouchEvent): void {
    if (!isDrawing.value) {
        return;
    }

    const { x, y } = getTouchCoords(e);
    drawLine(lastX, lastY, x, y);
    lastX = x;
    lastY = y;
}

function drawLine(x1: number, y1: number, x2: number, y2: number): void {
    const ctx = getContext();

    if (!ctx) {
        return;
    }

    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.strokeStyle = '#111827';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.stroke();
    isEmpty.value = false;
    emit('update:modelValue', canvasRef.value!.toDataURL('image/png'));
}

function stopDrawing(): void {
    isDrawing.value = false;
}

function clear(): void {
    const ctx = getContext();

    if (!ctx || !canvasRef.value) {
        return;
    }

    ctx.clearRect(0, 0, props.width, props.height);
    isEmpty.value = true;
    emit('update:modelValue', null);
}

onMounted(() => {
    // Charger une signature existante si fournie
    if (props.modelValue && canvasRef.value) {
        const img = new Image();
        img.onload = () => {
            getContext()?.drawImage(img, 0, 0);
            isEmpty.value = false;
        };
        img.src = props.modelValue;
    }
});

watch(
    () => props.modelValue,
    (val) => {
        if (!val) {
            const ctx = getContext();

            if (ctx && canvasRef.value) {
                ctx.clearRect(0, 0, props.width, props.height);
                isEmpty.value = true;
            }
        }
    },
);
</script>
