<?php

namespace App\Http\Controllers\Cours;

use App\Enums\TypeCours;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cours\StoreCoursRequest;
use App\Http\Requests\Cours\UpdateCoursRequest;
use App\Models\Cours;
use App\Services\AppliquerGabaritService;
use App\Services\CoursProps;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CoursController extends Controller
{
    /**
     * Affiche le formulaire de création d'un cours.
     */
    public function create(): Response
    {
        return Inertia::render('Enseignant/CoursEdition/Create');
    }

    /**
     * Affiche le formulaire de modification d'un cours appartenant à l'enseignant.
     */
    public function edit(Cours $cours): Response
    {
        $this->authorize('update', $cours);

        return Inertia::render('Enseignant/CoursEdition/Edit', ['cours' => $cours]);
    }

    /**
     * Affiche le détail d'un cours avec ses étudiants, classes et documents.
     *
     * Accessible aux enseignants propriétaires et aux admins (CoursPolicy::view).
     *
     * @throws AuthorizationException
     */
    public function show(Cours $cours, CoursProps $coursProps): Response
    {
        $this->authorize('view', $cours);

        return Inertia::render('Cours/Show', $coursProps->pourShow($cours));
    }

    /**
     * Enregistre un nouveau cours pour l'enseignant authentifié.
     *
     * Si le type est 'cours_complet' et que `utiliser_gabarit` est activé,
     * le gabarit standard est automatiquement appliqué au cours créé.
     *
     * La validation et l'autorisation sont déléguées à StoreCoursRequest.
     */
    public function store(StoreCoursRequest $request, AppliquerGabaritService $gabaritService): RedirectResponse
    {
        $validated = $request->validated();

        // On extrait le flag avant la création — il ne fait pas partie du modèle
        $utiliserGabarit = (bool) ($validated['utiliser_gabarit'] ?? false);
        unset($validated['utiliser_gabarit']);

        /** @var Cours $cours */
        $cours = auth()->user()->cours()->create($validated);

        if ($utiliserGabarit && $cours->type_cours === TypeCours::CoursComplet) {
            $gabaritService->appliquer($cours, 'cours_complet');
        }

        return to_route('enseignant.index')->with('success', __('cours.created'));
    }

    /**
     * Met à jour un cours existant.
     *
     * La validation et l'autorisation (CoursPolicy::update) sont déléguées à UpdateCoursRequest.
     */
    public function update(UpdateCoursRequest $request, Cours $cours): RedirectResponse
    {
        $cours->update($request->validated());

        return to_route('enseignant.index')->with('success', __('cours.updated'));
    }

    /**
     * Inverse le statut de verrouillage d'un cours.
     *
     * Un cours verrouillé n'est plus visible pour les étudiants.
     *
     * @throws AuthorizationException
     */
    public function toggleVerrouillage(Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);

        $verrouille = ! $cours->is_verrouille;
        $cours->update(['is_verrouille' => $verrouille]);

        $message = $verrouille ? __('cours.verrouille') : __('cours.deverrouille');

        return to_route('enseignant.index')->with('success', $message);
    }

    /**
     * Supprime un cours et toutes ses données associées.
     *
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours): RedirectResponse
    {
        $this->authorize('delete', $cours);

        $cours->delete();

        return to_route('enseignant.index')->with('success', __('cours.deleted'));
    }
}
