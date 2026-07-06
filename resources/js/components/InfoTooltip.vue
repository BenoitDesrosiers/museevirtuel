<script setup lang="ts">
import { Info } from 'lucide-vue-next';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * Bouton icône « i » avec tooltip reka-ui intégré.
 *
 * Remplace le patron répété TooltipProvider > Tooltip > Info dans les formulaires.
 *
 * @example Simple
 * <InfoTooltip :texte="t('criteres.tooltip_visible')" />
 *
 * @example Largeur et icône personnalisées
 * <InfoTooltip
 *     :texte="t('criteres.tooltip_global_vs_section')"
 *     content-class="max-w-72"
 *     icon-class="h-3.5 w-3.5"
 * />
 */
const props = defineProps<{
    /** Texte affiché dans le tooltip au survol. */
    texte: string;
    /** Classe Tailwind appliquée à TooltipContent. Par défaut : « max-w-64 ». */
    contentClass?: string;
    /** Classe Tailwind appliquée à l'icône Info. Par défaut : « h-3 w-3 ». */
    iconClass?: string;
}>();
</script>

<template>
    <TooltipProvider>
        <Tooltip>
            <TooltipTrigger as-child>
                <button type="button" class="text-muted-foreground hover:text-foreground">
                    <Info :class="props.iconClass ?? 'h-3 w-3'" />
                </button>
            </TooltipTrigger>
            <TooltipContent :class="props.contentClass ?? 'max-w-64'">
                {{ props.texte }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
