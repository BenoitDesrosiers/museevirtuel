import type { Directive, DirectiveBinding } from 'vue';

/**
 * Directive v-tooltip — affiche un texte descriptif au-dessus du curseur.
 *
 * Utilise un singleton DOM positionné en `position: fixed` qui suit
 * la souris, évitant les problèmes de z-index et de portail reka-ui.
 *
 * @example
 * <button v-tooltip="'Supprimer ce paragraphe'">
 *   <Trash2 />
 * </button>
 */

let el: HTMLElement | null = null;
let hideTimer: ReturnType<typeof setTimeout> | null = null;

/** Crée ou retourne l'élément tooltip singleton. */
function getOrCreateEl(): HTMLElement {
    if (!el) {
        el = document.createElement('div');
        el.setAttribute('role', 'tooltip');
        el.style.cssText = [
            'position: fixed',
            'z-index: 9999',
            'pointer-events: none',
            'display: none',
            'max-width: 260px',
            'padding: 6px 12px',
            'border-radius: 6px',
            'font-size: 12px',
            'line-height: 1.4',
            'white-space: normal',
            'word-break: break-word',
            // Reprend les tokens CSS de l'app (bg-foreground / text-background)
            'background-color: var(--foreground)',
            'color: var(--background)',
            'box-shadow: 0 2px 8px rgba(0,0,0,.18)',
        ].join(';');
        document.body.appendChild(el);
    }
    return el;
}

/** Positionne le tooltip 12 px au-dessus du curseur et décalé à droite. */
function updatePosition(event: MouseEvent): void {
    const tooltip = getOrCreateEl();
    const offset = 14;

    let x = event.clientX + offset;
    let y = event.clientY - tooltip.offsetHeight - offset;

    // Evite le débordement à droite
    if (x + tooltip.offsetWidth > window.innerWidth - 8) {
        x = event.clientX - tooltip.offsetWidth - offset;
    }

    // Evite le débordement en haut
    if (y < 8) {
        y = event.clientY + offset;
    }

    tooltip.style.left = `${x}px`;
    tooltip.style.top = `${y}px`;
}

function show(text: string, event: MouseEvent): void {
    if (hideTimer) {
        clearTimeout(hideTimer);
        hideTimer = null;
    }
    const tooltip = getOrCreateEl();
    tooltip.textContent = text;
    tooltip.style.display = 'block';
    updatePosition(event);
}

function hide(): void {
    if (el) {
        el.style.display = 'none';
    }
}

export const vTooltip: Directive<HTMLElement, string> = {
    mounted(target: HTMLElement, binding: DirectiveBinding<string>) {
        const text = binding.value;

        target.addEventListener('mouseenter', (e) =>
            show(text, e as MouseEvent),
        );
        target.addEventListener('mousemove', (e) =>
            updatePosition(e as MouseEvent),
        );
        target.addEventListener('mouseleave', hide);
        target.addEventListener('focus', (e) =>
            show(text, e as unknown as MouseEvent),
        );
        target.addEventListener('blur', hide);
    },

    updated(target: HTMLElement, binding: DirectiveBinding<string>) {
        // Met à jour le texte si la valeur de la directive change
        if (
            binding.value !== binding.oldValue &&
            el &&
            el.style.display !== 'none'
        ) {
            el.textContent = binding.value;
        }
    },

    unmounted() {
        hide();
    },
};
