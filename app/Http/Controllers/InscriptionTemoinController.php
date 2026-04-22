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
    /** Nombre d'engagements à accepter à l'étape 2 de l'inscription. */
    public const NB_ENGAGEMENTS = 6;

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
     * Valide les données de l'étape 1, les stocke en session et redirige vers l'étape 2.
     *
     * Le compte n'est pas encore créé à cette étape.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateStep1($request);

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

        // Stocker les données en session pour l'étape 2 (le mot de passe reste en clair ;
        // la session Laravel est chiffrée côté serveur et de courte durée)
        session(['inscription_temoin_step1' => $validated]);

        return redirect()->route('inscription.temoin.engagements');
    }

    /**
     * Affiche la page des engagements (étape 2 de l'inscription).
     *
     * Redirige vers l'étape 1 si la session est absente ou expirée.
     */
    public function showEngagements(): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfStep1Missing()) {
            return $redirect;
        }

        return Inertia::render('auth/InscriptionEngagements');
    }

    /**
     * Valide les engagements et la signature, puis crée le compte personne âgée.
     */
    public function storeEngagements(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfStep1Missing()) {
            return $redirect;
        }

        $step1 = session('inscription_temoin_step1');

        $validated = $request->validate([
            'engagements' => ['required', 'array', 'size:'.self::NB_ENGAGEMENTS],
            'engagements.*' => ['accepted'],
            'signature' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $user = $this->creerPersonneAgee($step1, $validated['signature']);

        session()->forget('inscription_temoin_step1');

        return redirect()->route('inscription.temoin')
            ->with('success', __('Votre demande a été envoyée. Un administrateur examinera votre inscription prochainement.'));
    }

    /**
     * Retourne une redirection vers l'étape 1 si la session est absente ou expirée, null sinon.
     */
    private function redirectIfStep1Missing(): ?RedirectResponse
    {
        if (! session()->has('inscription_temoin_step1')) {
            return redirect()->route('inscription.temoin')
                ->with('error', __('Votre session a expiré. Veuillez recommencer votre inscription.'));
        }

        return null;
    }

    /**
     * Valide les champs du formulaire d'étape 1.
     */
    private function validateStep1(Request $request): array
    {
        return $request->validate([
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
    }

    /**
     * Crée le compte personne âgée avec les données des deux étapes.
     *
     * @param  array<string, mixed>  $step1
     */
    private function creerPersonneAgee(array $step1, string $signature): User
    {
        $user = User::create([
            'prenom' => $step1['prenom'],
            'nom' => $step1['nom'],
            'email' => $step1['email'],
            'password' => $step1['password'],
            'role' => 'personne_agee',
            'statut' => 'en_attente',
            'description' => $step1['description'],
            'engagements_acceptes_le' => now(),
            'signature_electronique' => $signature,
        ]);

        // Sync toutes les thématiques choisies
        $tousLesIds = collect($step1['choix'])
            ->flatMap(fn ($c) => $c['thematique_ids'] ?? [])
            ->unique()
            ->all();

        if (! empty($tousLesIds)) {
            $user->thematiquesChoisies()->sync($tousLesIds);
        }

        // Insérer le pivot user_etablissement avec thème libre par cégep
        $pivots = collect($step1['choix'])->mapWithKeys(fn ($c) => [
            $c['etablissement_id'] => ['theme_libre' => $c['theme_libre'] ?? null],
        ])->all();

        $user->etablissementsChoisis()->sync($pivots);

        return $user;
    }
}
