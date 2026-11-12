<?php

namespace App\Http\Controllers\Classe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classe\SaveClasseRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Services\ClasseProps;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ClasseController extends Controller
{
    /**
     * Affiche le détail d'une classe (section) avec ses groupes.
     *
     * Accessible aux étudiants inscrits, à l'enseignant et aux admins.
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    public function show(Cours $cours, Classe $classe, ClasseProps $classeProps): Response
    {
        abort_if($classe->cours_id !== $cours->id, 404);

        $classe->load('cours');
        $this->authorize('view', $classe);

        $user = auth()->user();
        // L'enseignant du cours et l'admin gèrent la classe : ils ont la même vue, avec la liste des inscrits
        $peutGererClasse = $user->can('update', $classe);

        return Inertia::render('Classes/Show',
            $classeProps->pourShow($cours, $classe, $user, $peutGererClasse));
    }

    /**
     * Crée une nouvelle classe (section) dans un cours.
     *
     * Réservé à l'enseignant du cours et aux admins.
     * Les données sont validées par SaveClasseRequest.
     *
     * @throws AuthorizationException
     */
    public function store(SaveClasseRequest $request, Cours $cours): RedirectResponse
    {
        $validated = $request->validated();

        Classe::create(array_merge(
            ['cours_id' => $cours->id, 'code' => $cours->code],
            array_filter($validated, fn ($v) => $v !== null)
        ));

        return back()->with('success', __('classe.created'));
    }

    /**
     * Met à jour une classe (section) : code et/ou nom.
     *
     * Réservé à l'enseignant du cours et aux admins.
     * Les données sont validées par SaveClasseRequest.
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    public function update(SaveClasseRequest $request, Cours $cours, Classe $classe): RedirectResponse
    {
        $validated = $request->validated();

        $classe->update(array_merge($validated, ['code' => $cours->code]));

        return back()->with('success', __('classe.updated'));
    }

    /**
     * Supprime une classe (section) — cascade sur les groupes et projets.
     *
     * Réservé à l'enseignant du cours et aux admins.
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    public function destroy(Cours $cours, Classe $classe): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);

        $classe->load('cours');
        $this->authorize('delete', $classe);

        $classe->delete();

        return redirect()->route('cours.show', $cours)->with('success', __('classe.deleted'));
    }
}
