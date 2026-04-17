<?php

namespace App\Http\Controllers;

use App\Models\Etablissement;
use App\Models\Thematique;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InscriptionTemoinController extends Controller
{
    /**
     * Affiche le formulaire d'inscription public pour les témoins (personnes âgées).
     */
    public function show(): Response
    {
        return Inertia::render('auth/InscriptionTemoin', [
            'etablissements' => Etablissement::with(['thematiques' => fn ($q) => $q->orderBy('nom')->select('id', 'nom', 'etablissement_id')])
                ->orderBy('nom')
                ->get(['id', 'nom', 'ville']),
        ]);
    }

    /**
     * Enregistre une demande d'inscription de témoin en attente d'approbation.
     *
     * Le témoin peut choisir plusieurs cégeps, avec pour chacun des thématiques
     * et/ou un thème libre propre à ce cégep.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'choix' => ['required', 'array', 'min:1'],
            'choix.*.etablissement_id' => ['required', 'integer', 'exists:etablissements,id'],
            'choix.*.thematique_ids' => ['nullable', 'array'],
            'choix.*.thematique_ids.*' => ['integer', 'exists:thematiques,id'],
            'choix.*.theme_libre' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        // Valider la cohérence de chaque choix : thème présent + thématiques appartenant au bon cégep
        foreach ($validated['choix'] as $index => $choix) {
            if (empty($choix['thematique_ids']) && empty($choix['theme_libre'])) {
                return back()->withErrors([
                    "choix.{$index}.theme_libre" => __('Veuillez choisir au moins une thématique ou saisir un thème libre pour ce cégep.'),
                ])->withInput();
            }

            foreach ($choix['thematique_ids'] ?? [] as $thematiqueId) {
                if (! Thematique::where('id', $thematiqueId)->where('etablissement_id', $choix['etablissement_id'])->exists()) {
                    return back()->withErrors([
                        "choix.{$index}.thematique_ids" => __('Une thématique sélectionnée n\'appartient pas à ce cégep.'),
                    ])->withInput();
                }
            }
        }

        $user = User::create([
            'prenom' => $validated['prenom'],
            'nom' => $validated['nom'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'personne_agee',
            'statut' => 'en_attente',
            'description' => $validated['description'],
        ]);

        // Sync toutes les thématiques choisies
        $tousLesIds = collect($validated['choix'])
            ->flatMap(fn ($c) => $c['thematique_ids'] ?? [])
            ->unique()
            ->all();

        if (! empty($tousLesIds)) {
            $user->thematiquesChoisies()->sync($tousLesIds);
        }

        // Insérer le pivot user_etablissement avec thème libre par cégep
        $pivots = collect($validated['choix'])->mapWithKeys(fn ($c) => [
            $c['etablissement_id'] => ['theme_libre' => $c['theme_libre'] ?? null],
        ])->all();

        $user->etablissementsChoisis()->sync($pivots);

        return redirect()->route('inscription.temoin')
            ->with('success', __('Votre demande a été envoyée. Un administrateur examinera votre inscription prochainement.'));
    }
}
