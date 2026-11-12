<?php

namespace App\Http\Requests\Concerns;

trait HasCritereRules
{
    /**
     * Retourne les règles de validation communes aux critères (store et update).
     *
     * Notes :
     * - L'égalité de la somme de l'échelle avec `pointage` n'est PAS une erreur fatale ;
     *   c'est un avertissement visuel géré côté UI.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function critereRules(): array
    {
        return [
            'type' => ['required', 'string', 'in:positif,negatif'],
            'contenu_type' => ['required', 'string', 'in:texte,echelle'],
            'pointage' => ['required', 'numeric', 'min:0.1', 'max:99.99'],
            'contenu' => ['nullable', 'string', 'max:10000'],
            'echelle' => ['nullable', 'array'],
            'echelle.*.label' => ['required_if:contenu_type,echelle', 'string', 'max:255'],
            'echelle.*.points' => [
                'required_if:contenu_type,echelle',
                'numeric',
                'min:0',
                // Chaque niveau ne peut pas dépasser le pointage total du critère.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $pointage = (float) request()->input('pointage', 0);
                    if ((float) $value > $pointage) {
                        $fail(__('critere.echelle.points.depasse_pointage'));
                    }
                },
            ],
            'echelle.*.description' => ['nullable', 'string', 'max:1000'],
            'visible' => ['boolean'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
