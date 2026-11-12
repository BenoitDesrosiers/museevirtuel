<?php

namespace App\Http\Controllers\Groupe;

use App\Concerns\ValidatesGroupeChain;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groupe\StoreGroupeRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\Thematique;
use App\Services\GroupeProps;
use App\Services\GroupeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GroupeController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Redirige vers la page de création de groupe.
     *
     * @throws HttpException si l'étudiant n'est pas inscrit à la classe
     */
    public function index(Cours $cours, Classe $classe): Response|RedirectResponse
    {
        return redirect()->route('groupes.create', [$cours, $classe]);
    }

    /**
     * Affiche le formulaire de création d'un groupe ou redirige vers le groupe
     * existant de l'étudiant.
     *
     * @throws HttpException si la classe n'appartient pas au cours
     * @throws AuthorizationException si l'utilisateur n'est pas autorisé dans la classe
     */
    public function create(Cours $cours, Classe $classe): Response|RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        $this->authorize('create', [Groupe::class, $classe]);

        $user = auth()->user();

        $monGroupe = $classe->groupes()
            ->whereHas('membres', fn ($q) => $q->where('users.id', $user->id))
            ->first();

        if ($monGroupe) {
            return redirect()->route('groupes.show', [$cours, $classe, $monGroupe]);
        }

        $dejaGroupes = $classe->etudiantsDejaGroupes();

        $autresEtudiants = $classe->etudiants()
            ->where('users.id', '!=', $user->id)
            ->whereNotIn('users.id', $dejaGroupes)
            ->get(['users.id', 'prenom', 'nom']);

        $thematiques = Thematique::visiblesPar($cours->enseignant)
            ->get(['id', 'nom', 'periode_historique']);

        return Inertia::render('Groupes/Create', [
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'autresEtudiants' => $autresEtudiants,
            'thematiques' => $thematiques,
        ]);
    }

    /**
     * Crée un nouveau groupe dans une classe et associe membres et thématiques.
     *
     * Accès et données vérifiés par StoreGroupeRequest ; règles de composition dans GroupeService.
     *
     * @throws ValidationException si l'étudiant est déjà dans un groupe ou si la composition est invalide
     */
    public function store(StoreGroupeRequest $request, Cours $cours, Classe $classe,
        GroupeService $groupeService): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $groupeService->creer(
                $request->user(),
                $cours,
                $classe,
                $validated['membres'] ?? [],
                $validated['thematiques'] ?? [],
            );
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // On renvoie vers la classe pour que l'utilisateur voie le nouveau groupe dans la liste.
        return redirect()
            ->route('classes.show', [$cours, $classe])
            ->with('success', __('groupe.created'));
    }

    /**
     * Affiche le détail d'un groupe avec ses membres, thématiques, notes et médias.
     *
     * Accessible aux membres, au témoin, à l'enseignant du cours et aux admins.
     *
     * @throws AuthorizationException
     */
    public function show(
        Cours $cours, Classe $classe,
        Groupe $groupe, GroupeProps $groupeProps): Response
    {
        $this->verifierChaine($cours, $classe, $groupe);
        $groupe->load('classe.cours');
        $this->authorize('view', $groupe);

        return Inertia::render('Groupes/Show',
            $groupeProps->pourAffichage($cours, $classe, $groupe, auth()->user()));
    }

    /**
     * Supprime un groupe entier.
     *
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe);
        $groupe->load('classe.cours');
        $this->authorize('delete', $groupe);

        $groupe->delete();

        return redirect()->route('groupes.index', [$cours, $classe])->with('success', __('groupe.deleted'));
    }
}
