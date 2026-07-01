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
            'pointage' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
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
                        $fail('Le pointage d\'un niveau ne peut pas dépasser le pointage total du critère.');
                    }
                },
            ],
            'echelle.*.description' => ['nullable', 'string', 'max:1000'],
            'visible' => ['boolean'],
        ];
    }

    /**
     * Retourne les messages d'erreur de validation en français pour les critères.
     *
     * @return array<string, string>
     */
    protected function critereMessages(): array
    {
        return [
            'type.required' => 'Le type du critère est obligatoire.',
            'type.in' => 'Le type doit être « Bonus » ou « Pénalité ».',
            'contenu_type.required' => 'Le mode de saisie est obligatoire.',
            'contenu_type.in' => 'Le mode de saisie doit être « Texte » ou « Échelle ».',
            'pointage.required' => 'Le pointage est obligatoire.',
            'pointage.numeric' => 'Le pointage doit être un nombre valide.',
            'pointage.min' => 'Le pointage doit être supérieur à 0.',
            'pointage.max' => 'Le pointage ne peut pas dépasser 999,99.',
            'contenu.max' => 'La description ne peut pas dépasser 10 000 caractères.',
            'echelle.array' => "L'échelle doit être une liste de niveaux.",
            'echelle.*.label.required_if' => "L'étiquette de chaque niveau de l'échelle est obligatoire.",
            'echelle.*.label.string' => "L'étiquette d'un niveau doit être un texte.",
            'echelle.*.label.max' => "L'étiquette d'un niveau ne peut pas dépasser 255 caractères.",
            'echelle.*.points.required_if' => 'Le pointage de chaque niveau est obligatoire.',
            'echelle.*.points.numeric' => 'Le pointage d\'un niveau doit être un nombre valide.',
            'echelle.*.points.min' => 'Le pointage d\'un niveau doit être positif ou nul.',
            'echelle.*.description.max' => "La description d'un niveau ne peut pas dépasser 1 000 caractères.",
        ];
    }
}
