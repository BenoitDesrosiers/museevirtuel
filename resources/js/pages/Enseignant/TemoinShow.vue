<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, XCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { approuver, decliner, desapprouver } from '@/routes/enseignant/temoins';

type Temoin = {
    id: number;
    prenom: string;
    nom: string;
    email: string;
    description: string | null;
    provenance: string | null;
    theme_libre: string | null;
    statut: 'en_attente' | 'actif' | 'refuse';
    created_at: string;
    thematiques_choisies: { id: number; nom: string }[];
    engagements_acceptes_le: string | null;
    signature_electronique: string | null;
};

const props = defineProps<{ temoin: Temoin }>();
const { t } = useI18n();

const approuverForm = useForm({});
const declinerForm = useForm({});
const desapprouverForm = useForm({});

const backUrl = '/enseignant';

function approuverTemoin() {
    approuverForm.put(approuver.url(props.temoin.id));
}

function declinerTemoin() {
    if (!confirm(t('enseignant.index.confirm_decline_temoin', { prenom: props.temoin.prenom, nom: props.temoin.nom }))) {
        return;
    }
    declinerForm.put(decliner.url(props.temoin.id));
}

function desapprouverTemoin() {
    if (!confirm(t('enseignant.temoin_show.confirm_unapprove', { prenom: props.temoin.prenom, nom: props.temoin.nom }))) {
        return;
    }
    desapprouverForm.put(desapprouver.url(props.temoin.id));
}

const statusLabel: Record<Temoin['statut'], string> = {
    en_attente: 'enseignant.temoin_show.status_pending',
    actif: 'enseignant.temoin_show.status_active',
    refuse: 'enseignant.temoin_show.status_refused',
};

const statusVariant: Record<Temoin['statut'], 'default' | 'secondary' | 'destructive'> = {
    en_attente: 'secondary',
    actif: 'default',
    refuse: 'destructive',
};
</script>

<template>
    <AppLayout>
        <Head :title="$t('enseignant.temoin_show.page_title')" />

        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" as-child>
                    <a :href="backUrl">
                        <ArrowLeft class="mr-1 h-4 w-4" />
                        {{ $t('enseignant.temoin_show.back') }}
                    </a>
                </Button>
            </div>

            <Heading
                :title="`${temoin.prenom} ${temoin.nom}`"
                :description="temoin.email"
            />

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- ─── Infos principales ─────────────────────────────────── -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>{{ $t('enseignant.temoin_show.page_title') }}</CardTitle>
                            <Badge :variant="statusVariant[temoin.statut]">
                                {{ $t(statusLabel[temoin.statut]) }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="grid gap-6">
                        <!-- Description -->
                        <div>
                            <p class="text-muted-foreground mb-1 text-xs font-medium uppercase tracking-wide">
                                {{ $t('administration.index.temoins_header_description') }}
                            </p>
                            <p v-if="temoin.description" class="text-sm leading-relaxed">{{ temoin.description }}</p>
                            <p v-else class="text-muted-foreground text-sm italic">{{ $t('enseignant.temoin_show.no_description') }}</p>
                        </div>

                        <!-- Thème libre -->
                        <div>
                            <p class="text-muted-foreground mb-1 text-xs font-medium uppercase tracking-wide">
                                {{ $t('enseignant.temoin_show.free_theme') }}
                            </p>
                            <p v-if="temoin.theme_libre" class="text-sm leading-relaxed">{{ temoin.theme_libre }}</p>
                            <p v-else class="text-muted-foreground text-sm italic">{{ $t('enseignant.temoin_show.no_free_theme') }}</p>
                        </div>

                        <!-- Provenance -->
                        <div v-if="temoin.provenance">
                            <p class="text-muted-foreground mb-1 text-xs font-medium uppercase tracking-wide">
                                {{ $t('enseignant.temoin_show.provenance') }}
                            </p>
                            <p class="text-sm">{{ temoin.provenance }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- ─── Colonne latérale ──────────────────────────────────── -->
                <div class="flex flex-col gap-4">
                    <!-- Thématiques -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-sm">{{ $t('administration.index.temoins_header_theme') }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="temoin.thematiques_choisies.length" class="flex flex-wrap gap-2">
                                <Badge
                                    v-for="th in temoin.thematiques_choisies"
                                    :key="th.id"
                                    variant="outline"
                                >
                                    {{ th.nom }}
                                </Badge>
                            </div>
                            <p v-else class="text-muted-foreground text-sm">—</p>
                        </CardContent>
                    </Card>

                    <!-- Inscription -->
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                                {{ $t('enseignant.temoin_show.registered_on') }}
                            </p>
                            <p class="mt-1 text-sm">
                                {{ new Date(temoin.created_at).toLocaleDateString() }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Consentement -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-sm">{{ $t('enseignant.temoin_show.consent_title') }}</CardTitle>
                        </CardHeader>
                        <CardContent class="grid gap-3">
                            <template v-if="temoin.engagements_acceptes_le">
                                <div class="flex items-center gap-2 text-sm text-green-700 dark:text-green-400">
                                    <CheckCircle class="h-4 w-4 shrink-0" />
                                    <span>
                                        {{ $t('enseignant.temoin_show.consent_accepted') }}
                                        {{ new Date(temoin.engagements_acceptes_le).toLocaleDateString() }}
                                    </span>
                                </div>
                                <div v-if="temoin.signature_electronique">
                                    <p class="text-muted-foreground mb-1 text-xs font-medium uppercase tracking-wide">
                                        {{ $t('enseignant.temoin_show.signature') }}
                                    </p>
                                    <p
                                        class="rounded border border-dashed px-3 py-2 text-xl text-gray-700 dark:text-gray-300"
                                        style="font-family: 'Brush Script MT', cursive, serif;"
                                    >
                                        {{ temoin.signature_electronique }}
                                    </p>
                                </div>
                            </template>
                            <div v-else class="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
                                <XCircle class="h-4 w-4 shrink-0" />
                                <span>{{ $t('enseignant.temoin_show.consent_not_signed') }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Actions : en attente -->
                    <Card v-if="temoin.statut === 'en_attente'">
                        <CardContent class="flex flex-col gap-2 pt-6">
                            <Button
                                class="w-full text-green-600 hover:text-green-700"
                                variant="outline"
                                :disabled="approuverForm.processing || declinerForm.processing"
                                @click="approuverTemoin"
                            >
                                <CheckCircle class="mr-2 h-4 w-4" />
                                {{ $t('enseignant.temoin_show.approve_btn') }}
                            </Button>
                            <Button
                                class="text-destructive hover:text-destructive w-full"
                                variant="outline"
                                :disabled="approuverForm.processing || declinerForm.processing"
                                @click="declinerTemoin"
                            >
                                <XCircle class="mr-2 h-4 w-4" />
                                {{ $t('enseignant.temoin_show.decline_btn') }}
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Actions : approuvé -->
                    <Card v-if="temoin.statut === 'actif'">
                        <CardContent class="pt-6">
                            <Button
                                class="text-destructive hover:text-destructive w-full"
                                variant="outline"
                                :disabled="desapprouverForm.processing"
                                @click="desapprouverTemoin"
                            >
                                <XCircle class="mr-2 h-4 w-4" />
                                {{ $t('enseignant.temoin_show.unapprove_btn') }}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
