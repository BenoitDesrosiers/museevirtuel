<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Send } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Auth } from '@/types/auth';

type User = {
    id: number;
    prenom: string;
    nom: string;
};

type Classe = {
    id: number;
    nom_cours: string;
    code: string;
};

type Groupe = {
    id: number;
    numero: number;
    temoin: User | null;
    membres: User[];
};

type Echange = {
    id: number;
    auteur_id: number;
    contenu: string;
    created_at: string;
    auteur: User;
};

type Props = {
    classe: Classe;
    groupe: Groupe;
    echanges: Echange[];
};

const props = defineProps<Props>();

const page = usePage();
const userId = computed(() => (page.props.auth as Auth).user.id);

const estTemoin = computed(() => props.groupe.temoin?.id === userId.value);

function estMessageTemoin(echange: Echange): boolean {
    return echange.auteur_id === props.groupe.temoin?.id;
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-CA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

// ─── Formulaire envoi ─────────────────────────────────────────────────────────
const form = useForm({ contenu: '' });
const threadEl = ref<HTMLDivElement | null>(null);

function scrollToBottom() {
    nextTick(() => {
        if (threadEl.value) {
            threadEl.value.scrollTop = threadEl.value.scrollHeight;
        }
    });
}

onMounted(() => scrollToBottom());

function submit() {
    form.post(`/classes/${props.classe.id}/groupes/${props.groupe.id}/echanges`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            scrollToBottom();
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="$t('echanges.page_title', { n: groupe.numero })" />

        <div class="flex flex-col gap-6 p-6">
            <!-- Retour -->
            <div>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="`/classes/${classe.id}/groupes/${groupe.id}`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ $t('echanges.back') }}
                    </Link>
                </Button>
            </div>

            <Heading
                :title="$t('echanges.page_title', { n: groupe.numero })"
                :description="`${classe.code} · ${classe.nom_cours}`"
            />

            <!-- Témoin info -->
            <Card v-if="groupe.temoin">
                <CardContent class="py-3">
                    <div class="flex items-center gap-3">
                        <span class="bg-primary/10 text-primary flex h-8 w-8 items-center justify-center rounded-full text-xs font-medium">
                            {{ groupe.temoin.prenom[0] }}{{ groupe.temoin.nom[0] }}
                        </span>
                        <div>
                            <p class="text-sm font-medium">{{ groupe.temoin.prenom }} {{ groupe.temoin.nom }}</p>
                            <p class="text-muted-foreground text-xs">{{ $t('echanges.witness_of_group') }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
            <div v-else class="text-muted-foreground rounded-lg border border-dashed p-4 text-center text-sm">
                {{ $t('echanges.no_witness') }}
            </div>

            <!-- Thread d'échanges -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ $t('echanges.messages_title', { n: echanges.length }) }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-2">
                    <!-- Fil de discussion -->
                    <div
                        ref="threadEl"
                        class="flex max-h-[32rem] flex-col gap-3 overflow-y-auto pr-1"
                    >
                        <div
                            v-if="echanges.length === 0"
                            class="text-muted-foreground py-6 text-center text-sm"
                        >
                            {{ $t('echanges.no_messages') }}
                        </div>

                        <div
                            v-for="echange in echanges"
                            :key="echange.id"
                            class="flex flex-col gap-1"
                            :class="estMessageTemoin(echange) ? 'items-start' : 'items-end'"
                        >
                            <!-- Bulle -->
                            <div
                                class="max-w-[75%] rounded-2xl px-4 py-2.5 text-sm"
                                :class="estMessageTemoin(echange)
                                    ? 'bg-muted text-foreground rounded-tl-sm'
                                    : 'bg-primary text-primary-foreground rounded-tr-sm'"
                            >
                                {{ echange.contenu }}
                            </div>
                            <!-- Méta -->
                            <p class="text-muted-foreground text-xs">
                                {{ echange.auteur.prenom }} {{ echange.auteur.nom }}
                                · {{ formatDate(echange.created_at) }}
                            </p>
                        </div>
                    </div>

                    <!-- Formulaire envoi -->
                    <div
                        v-if="groupe.temoin"
                        class="mt-4 border-t pt-4"
                    >
                        <form class="flex flex-col gap-2" @submit.prevent="submit">
                            <Textarea
                                v-model="form.contenu"
                                rows="3"
                                maxlength="3000"
                                :placeholder="estTemoin ? $t('echanges.placeholder_temoin') : $t('echanges.placeholder_etudiant')"
                                class="resize-none"
                            />
                            <p v-if="form.errors.contenu" class="text-destructive text-sm">
                                {{ form.errors.contenu }}
                            </p>
                            <div class="flex justify-end">
                                <Button
                                    type="submit"
                                    size="sm"
                                    :disabled="form.processing || !form.contenu.trim()"
                                >
                                    <Send class="mr-2 h-4 w-4" />
                                    {{ $t('echanges.send') }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
