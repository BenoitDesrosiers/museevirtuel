<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import type { ButtonVariants } from "@/components/ui/button"
import { Button, buttonVariants } from "@/components/ui/button"
import { cn } from "@/lib/utils"

/**
 * Bouton avec tooltip cursor-following intégré.
 *
 * Utilise la directive v-tooltip (singleton DOM positionné en fixed)
 * plutôt que le portail reka-ui, pour que le texte suive la souris.
 *
 * Deux modes de rendu :
 * - asChild=false (défaut) : rend un <button> natif stylisé.
 * - asChild=true : délègue au premier enfant du slot (Link, <a>…).
 *
 * @example Bouton icône
 * <BoutonTooltip texte="Supprimer ce paragraphe" variant="ghost" size="icon" @click="fn">
 *   <Trash2 />
 * </BoutonTooltip>
 *
 * @example Navigation
 * <BoutonTooltip texte="Retour à l'éditeur" variant="ghost" size="sm" as-child>
 *   <Link :href="url"><ArrowLeft /> Retour</Link>
 * </BoutonTooltip>
 */
interface Props {
  /** Texte affiché dans le tooltip au survol (suit le curseur) */
  texte: string
  /** Variante visuelle du bouton */
  variant?: ButtonVariants["variant"]
  /** Taille du bouton */
  size?: ButtonVariants["size"]
  /** Classes CSS supplémentaires appliquées au bouton */
  class?: HTMLAttributes["class"]
  /** Désactive le bouton (ignoré si asChild=true) */
  disabled?: boolean
  /**
   * Délègue le rendu au premier enfant natif du slot (ex. <Link>, <a>).
   * Utile pour les boutons de navigation Inertia.
   */
  asChild?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: "ghost",
  size: "icon",
  disabled: false,
  asChild: false,
})
</script>

<template>
  <!-- Mode navigation : Button délègue à l'enfant (Link → <a>) -->
  <Button
    v-if="asChild"
    v-tooltip="texte"
    :variant="variant"
    :size="size"
    :class="props.class"
    :aria-label="texte"
    as-child
  >
    <slot />
  </Button>

  <!-- Mode bouton standard : <button> natif stylisé avec tooltip -->
  <button
    v-else
    v-tooltip="texte"
    :class="cn(buttonVariants({ variant, size }), props.class)"
    :disabled="disabled"
    :aria-label="texte"
  >
    <slot />
  </button>
</template>
