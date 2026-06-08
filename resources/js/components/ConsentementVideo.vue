<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { CheckCircle, FileSignature } from 'lucide-vue-next';
import SignatureCanvas from '@/components/SignatureCanvas.vue';
import consentementRoutes from '@/routes/projets/consentement';

type RouteParams = {
    cours: number;
    groupe: number;
    typeProjet: number;
};

type ConsentementExistant = {
    accepte: boolean;
    signed_at: string | null;
} | null;

const props = defineProps<{
    params: RouteParams;
    consentement: ConsentementExistant;
}>();

const showModal = ref(false);
const signature = ref<string | null>(props.consentement?.accepte ? null : null);

const form = useForm({
    accepte: props.consentement?.accepte ?? false,
    signature: '' as string,
});

/**
 * Soumet le formulaire de consentement vidéo.
 */
function soumettre(): void {
    form.signature = signature.value ?? '';

    form.post(consentementRoutes.store.url(props.params), {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
        },
    });
}

/**
 * Formate une date ISO en format lisible.
 */
function formatDate(isoDate: string): string {
    return new Date(isoDate).toLocaleDateString(undefined, {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}
</script>

<template>
    <div>
        <!-- Badge statut -->
        <button
            type="button"
            class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-muted"
            @click="showModal = true"
        >
            <CheckCircle
                class="h-4 w-4"
                :class="
                    consentement?.accepte
                        ? 'text-green-600'
                        : 'text-muted-foreground'
                "
            />
            <span v-if="consentement?.accepte && consentement.signed_at">
                {{
                    $t('consentement.signed_on', {
                        date: formatDate(consentement.signed_at),
                    })
                }}
            </span>
            <span v-else class="text-muted-foreground">
                {{ $t('consentement.not_signed') }}
            </span>
            <FileSignature class="ml-1 h-3.5 w-3.5 text-muted-foreground" />
        </button>

        <!-- Modal -->
        <Teleport to="body">
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.self="showModal = false"
            >
                <div
                    class="w-full max-w-lg rounded-xl bg-background p-6 shadow-xl"
                >
                    <h2 class="mb-1 text-lg font-semibold">
                        {{ $t('consentement.title') }}
                    </h2>
                    <p class="mb-4 text-sm text-muted-foreground">
                        {{ $t('consentement.description') }}
                    </p>

                    <!-- Checkbox acceptation -->
                    <label class="mb-4 flex cursor-pointer items-center gap-3">
                        <input
                            v-model="form.accepte"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300"
                        />
                        <span class="text-sm">{{
                            $t('consentement.accept')
                        }}</span>
                    </label>

                    <!-- Signature canvas (affiché seulement si accepté) -->
                    <div v-if="form.accepte" class="mb-4">
                        <p class="mb-2 text-sm font-medium">Signature</p>
                        <SignatureCanvas v-model="signature" :height="160" />
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-md border px-4 py-2 text-sm hover:bg-muted"
                            @click="showModal = false"
                        >
                            Annuler
                        </button>
                        <button
                            type="button"
                            class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                            :disabled="
                                form.processing || (form.accepte && !signature)
                            "
                            @click="soumettre"
                        >
                            {{ $t('consentement.save') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
