<?php

namespace App\Http\Controllers\Groupe;

use App\Concerns\ValidatesGroupeChain;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groupe\StoreGroupeRequest;
use App\Http\Resources\VisioConferenceResource;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeVideo;
use App\Models\ProjetRecherche;
use App\Models\Thematique;
use App\Models\TypeProjet;
use App\Models\User;
use App\Models\VisioConference;
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
     * @throws AuthorizationException si l'étudiant n'est pas inscrit à la classe
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
    public function store(StoreGroupeRequest $request, Cours $cours, Classe $classe, GroupeService $groupeService): RedirectResponse
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

        return back()->with('success', __('groupe.created'));
    }

    /**
     * Affiche le détail d'un groupe avec ses membres, thématiques, notes et médias.
     *
     * Accessible aux membres, au témoin, à l'enseignant du cours et aux admins.
     *
     * @throws AuthorizationException
     */
    public function show(Cours $cours, Classe $classe, Groupe $groupe): Response
    {
        $this->verifierChaine($cours, $classe, $groupe);
        $groupe->load('classe.cours');
        $this->authorize('view', $groupe);

        $user = auth()->user();

        $estMembre = $groupe->membres()->where('users.id', $user->id)->exists();
        /**
         * Il faudra retiré la portion $estEnseignant = $user->isEnseignant()
         * && en production.
         */
        $estEnseignant = $user->isEnseignant()
            && $cours->enseignant_id === $user->id;
        $estTemoin = $groupe->personne_agee_id === $user->id;

        $groupe->load([
            'membres',
            'thematiques',
            'notes.auteur',
            'notes.corrections',
            'createur',
            'medias.auteur',
            'temoin',
        ]);

        $thematiquesDispo = Thematique::visiblesPar($cours->enseignant)
            ->get(['id', 'nom', 'periode_historique']);

        $membreIds = $groupe->membres->pluck('id');

        $etudiantsDispo = $classe->etudiants()
            ->whereNotIn('users.id', $membreIds)
            ->whereNotIn('users.id', $classe->etudiantsDejaGroupes($groupe->id))
            ->get(['users.id', 'prenom', 'nom']);

        $groupeThematiqueIds = $groupe->thematiques->pluck('id');

        $peutGererTemoin = $estEnseignant || $user->isAdmin();

        // Une seule requête : tous les témoins actifs avec leurs thématiques pour le filtre
        $tousLesTemoins = $peutGererTemoin
            ? User::where('role', 'personne_agee')
                ->where('statut', 'actif')
                ->with('thematiquesChoisies:id')
                ->orderBy('nom')
                ->get(['id', 'prenom', 'nom'])
            : collect();

        // Témoins suggérés : filtrage PHP sur la collection déjà chargée
        $temoinsDisponibles = ($peutGererTemoin && $groupeThematiqueIds->isNotEmpty())
            ? $tousLesTemoins->filter(fn ($t) => $t->thematiquesChoisies->pluck('id')->intersect($groupeThematiqueIds)->isNotEmpty()
            )->values()
            : $tousLesTemoins;

        $tousLesTemoins->each(fn ($t) => $t->makeHidden('thematiquesChoisies'));
        $temoinsDisponibles->each(fn ($t) => $t->makeHidden('thematiquesChoisies'));

        // Sessions visio pour ce groupe : ciblées au groupe ou ouvertes à tout le cours
        $visioConferences = VisioConference::where('cours_id', $cours->id)
            ->where(fn ($q) => $q
                ->whereNull('groupe_id')
                ->orWhere('groupe_id', $groupe->id)
            )
            ->with('animateur:id,prenom,nom')
            ->orderByDesc('created_at')
            ->get()
            ->pipe(fn ($visios) => VisioConferenceResource::collection($visios)->resolve());

        $videos = GroupeVideo::where('groupe_id', $groupe->id)
            ->VisiblesPour($user, $estEnseignant)
            ->with('auteur:id,prenom,nom')
            ->orderByDesc('created_at')
            ->get();

        // Projet musée du groupe — exposé uniquement si le cours a un TypeProjet de type 'musee'
        $typeProjetMusee = TypeProjet::where('cours_id', $cours->id)
            ->where('type', 'musee')
            ->first(['id', 'nom']);

        $projetMusee = null;
        if ($typeProjetMusee) {
            $projetMusee = ProjetRecherche::where('groupe_id', $groupe->id)
                ->where('type_projet_id', $typeProjetMusee->id)
                ->with('museePublication:projet_recherche_id,est_publie')
                ->first(['id', 'type_projet_id', 'groupe_id']);
        }

        return Inertia::render('Groupes/Show', [
            'groupe' => $groupe,
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'estMembre' => $estMembre,
            'estEnseignant' => $estEnseignant,
            'estTemoin' => $estTemoin,
            'estCreateur' => $groupe->created_by === $user->id,
            'thematiquesDispo' => $thematiquesDispo,
            'etudiantsDispo' => $etudiantsDispo,
            'temoinsDisponibles' => $temoinsDisponibles,
            'tousLesTemoins' => $tousLesTemoins,
            'visioConferences' => $visioConferences,
            'videos' => $videos,
            'peutTranscrireMedia' => $user->can('transcrireMedia', $groupe),
            'typeProjetMusee' => $typeProjetMusee?->only('id', 'nom'),
            'projetMusee' => $projetMusee ? [
                'type_projet_id' => $projetMusee->type_projet_id,
                'est_publie' => (bool) $projetMusee->museePublication?->est_publie,
            ] : null,
        ]);
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
