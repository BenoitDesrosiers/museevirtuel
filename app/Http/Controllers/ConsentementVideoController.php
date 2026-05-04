<?php

namespace App\Http\Controllers;

use App\Models\ConsentementVideo;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        Request $request,
        Cours $cours,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        // Vérifier que l'utilisateur est membre du groupe ou personne âgée liée
        $user = auth()->user();
        $estMembre = $groupe->membres()->where('user_id', $user->id)->exists();
        $estPersonneAgee = $groupe->personne_agee_id === $user->id;

        abort_unless($estMembre || $estPersonneAgee, 403);

        $validated = $request->validate([
            'accepte' => ['required', 'boolean'],
            'signature' => ['nullable', 'string', 'max:65535'],
        ]);

        $type = in_array($user->role, ['personne_agee']) ? 'personne_agee' : 'etudiant';

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
