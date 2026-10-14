<script setup lang="ts">
type Membre = {
    id: number;
    prenom: string;
    nom: string;
};

type Paragraphe = {
    id: number;
    ordre: number;
    titre: string | null;
    contenu: string | null;
};

type ConclusionMembre = {
    userId: number;
    contenu: string;
};

type Section = {
    id: number;
    label: string;
    description: string | null;
    ordre: number;
    type: 'texte' | 'paragraphes' | 'individuel';
    contenu: string | null;
    paragraphes: Paragraphe[] | null;
    conclusionsParMembre: ConclusionMembre[] | null;
};

defineProps<{
    section: Section;
    membres: Membre[];
}>();

function nomMembre(userId: number, membres: Membre[]): string {
    const membre = membres.find((item) => item.id === userId);

    return membre ? `${membre.prenom} ${membre.nom}` : '—';
}
</script>

<template>
    <section class="space-y-3">
        <h2 class="border-b pb-2 text-xl font-semibold">
            {{ section.label }}
        </h2>
        <p
            v-if="section.description"
            class="text-xs text-muted-foreground italic"
        >
            {{ section.description }}
        </p>

        <template v-if="section.type === 'texte'">
            <div
                v-if="section.contenu && section.contenu.trim()"
                class="prose prose-sm dark:prose-invert max-w-none"
                v-html="section.contenu"
            />
            <p v-else class="text-sm text-muted-foreground italic">
                {{ $t('apercu.section_not_written') }}
            </p>
        </template>

        <template v-else-if="section.type === 'paragraphes'">
            <template
                v-if="
                    section.paragraphes && section.paragraphes.length > 0
                "
            >
                <article
                    v-for="paragraph in section.paragraphes"
                    :key="paragraph.id"
                    class="space-y-2"
                >
                    <h3
                        v-if="paragraph.titre"
                        class="text-base font-semibold"
                    >
                        {{ paragraph.titre }}
                    </h3>
                    <div
                        v-if="paragraph.contenu && paragraph.contenu.trim()"
                        class="prose prose-sm dark:prose-invert max-w-none"
                        v-html="paragraph.contenu"
                    />
                </article>
            </template>
            <p v-else class="text-sm text-muted-foreground italic">
                {{ $t('apercu.no_paragraphs') }}
            </p>
        </template>

        <template v-else-if="section.type === 'individuel'">
            <template
                v-if="
                    section.conclusionsParMembre &&
                    section.conclusionsParMembre.length > 0
                "
            >
                <article
                    v-for="conclusion in section.conclusionsParMembre"
                    :key="conclusion.userId"
                    class="space-y-2"
                >
                    <h3
                        class="text-sm font-semibold text-muted-foreground"
                    >
                        {{ nomMembre(conclusion.userId, membres) }}
                    </h3>
                    <div
                        class="prose prose-sm dark:prose-invert max-w-none"
                        v-html="conclusion.contenu"
                    />
                </article>
            </template>
            <p v-else class="text-sm text-muted-foreground italic">
                {{ $t('apercu.no_conclusions') }}
            </p>
        </template>
    </section>
</template>
