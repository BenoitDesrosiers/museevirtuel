<?php

namespace App\Http\Controllers\Temoin;

use App\Actions\InscrireTemoin;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\InscriptionTemoin\StoreInscriptionTemoinEngagementsRequest;
use App\Http\Requests\InscriptionTemoin\StoreInscriptionTemoinRequest;
use App\Models\Etablissement;
use App\Models\Thematique;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inscription publique d'un témoin (personne âgée) en deux étapes : identité et choix, puis engagements.
 */
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
     * Les données sont validées par StoreInscriptionTemoinRequest.
     */
    public function store(StoreInscriptionTemoinRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Valider la cohérence de chaque choix : thème présent + thématiques appartenant au bon cégep
        foreach ($validated['choix'] as $index => $choix) {
            if (empty($choix['thematique_ids']) && empty($choix['theme_libre'])) {
                return back()->withErrors([
                    "choix.{$index}.theme_libre" => __('inscription_temoin.theme_requis'),
                ])->withInput();
            }

            foreach ($choix['thematique_ids'] ?? [] as $thematiqueId) {
                if (! Thematique::where('id', $thematiqueId)->where('etablissement_id', $choix['etablissement_id'])->exists()) {
                    return back()->withErrors([
                        "choix.{$index}.thematique_ids" => __('inscription_temoin.thematique_hors_cegep'),
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
     * Crée le compte personne âgée à partir de l'étape 1 et des engagements signés.
     *
     * Les engagements et la signature sont validés par StoreInscriptionTemoinEngagementsRequest.
     */
    public function storeEngagements(
        StoreInscriptionTemoinEngagementsRequest $request,
        InscrireTemoin $inscrireTemoinAction): RedirectResponse
    {
        if ($redirect = $this->redirectIfStep1Missing()) {
            return $redirect;
        }

        try {
            $inscrireTemoinAction->execute(session('inscription_temoin_step1'), $request->validated('signature'));
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        session()->forget('inscription_temoin_step1');

        return redirect()->route('inscription.temoin')
            ->with('success', __('inscription_temoin.demande_envoyee'));
    }

    /**
     * Retourne une redirection vers l'étape 1 si la session est absente ou expirée, null sinon.
     */
    private function redirectIfStep1Missing(): ?RedirectResponse
    {
        if (! session()->has('inscription_temoin_step1')) {
            return redirect()->route('inscription.temoin')
                ->with('error', __('inscription_temoin.session_expiree'));
        }

        return null;
    }
}
