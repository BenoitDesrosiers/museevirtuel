<?php

namespace App\Http\Controllers\Projet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projet\StoreConsentementVideoRequest;
use App\Models\ConsentementVideo;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class ConsentementVideoController extends Controller
{
    /**
     * Enregistre ou met à jour le consentement vidéo de l'utilisateur connecté.
     *
     * Le consentement est lié à un projet spécifique.
     * La signature numérique (base64 PNG) est stockée si fournie.
     */
    public function store(
        StoreConsentementVideoRequest $request,
        Cours $cours,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        $user = auth()->user();

        $validated = $request->validated();

        $type = $user->isPersonneAgee() ? 'personne_agee' : 'etudiant';

        ConsentementVideo::updateOrCreate(
            [
                'user_id' => $user->id,
                'projet_id' => $projet->id,
            ],
            [
                'type' => $type,
                'accepte' => $validated['accepte'],
                'signature' => $validated['signature'] ?? null,
                'signed_at' => $validated['accepte'] ? Carbon::now() : null,
            ]
        );

        return back()->with('success', __('consentement.saved'));
    }
}
