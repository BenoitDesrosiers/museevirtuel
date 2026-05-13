<?php

namespace App\Http\Controllers;

use App\Models\ProjetRecherche;
use App\Models\Thematique;
use App\Models\User;
use App\Models\VisioConference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnseignantController extends Controller
{
    /**
     * Affiche le tableau de bord de l'enseignant avec ses classes, thématiques,
     * les travaux récemment remis et les témoins en attente liés à ses thématiques.
     */
    public function index(): Response
    {
        $user = auth()->user();

        $cours = $user->cours()
            ->withCount('classes')
            ->orderBy('nom_cours')
            ->get();

        $thematiques = $this->thematiquesVisibles($user)
            ->with('enseignant:id,prenom,nom')
            ->orderBy('nom')
            ->get();

        // Les 10 travaux les plus récemment remis parmi les groupes de ce prof
        $travauxRemis = ProjetRecherche::whereNotNull('remis_le')
            ->whereHas('groupe.classe.cours', fn ($q) => $q->where('enseignant_id', $user->id))
            ->with(['groupe' => fn ($q) => $q->with(['membres', 'classe.cours'])])
            ->orderByDesc('remis_le')
            ->limit(10)
            ->get()
            ->map(fn (ProjetRecherche $projet) => [
                'id' => $projet->id,
                'titre_projet' => $projet->titre_projet,
                'remis_le' => $projet->remis_le->toIso8601String(),
                'classe' => [
                    'id' => $projet->groupe->id,
                    'numero' => $projet->groupe->numero,
                    'cours_id' => $projet->groupe->classe->cours_id,
                ],
                'membres' => $projet->groupe->membres
                    ->map->only('id', 'prenom', 'nom')
                    ->values(),
            ]);

        // Témoins en attente liés à au moins une thématique du cégep (ou de l'enseignant si sans cégep)
        $thematiqueIds = $this->thematiquesVisibles($user)->pluck('id');

        $temoinsEnAttente = $this->queryTemoinsParThematiques($thematiqueIds)
            ->enAttente()
            ->get(['id', 'prenom', 'nom', 'email', 'description', 'created_at']);

        // Témoins approuvés par cet enseignant spécifiquement
        $temoinsApprouves = User::where('role', 'personne_agee')
            ->where('approuve_par_id', $user->id)
            ->actif()
            ->with('thematiquesChoisies:id,nom')
            ->orderBy('created_at')
            ->get(['id', 'prenom', 'nom', 'email', 'description', 'created_at']);

        // Prochaines visioconférences planifiées dans les cours de cet enseignant
        $prochainesVisios = VisioConference::whereHas('cours', fn ($q) => $q->where('enseignant_id', $user->id))
            ->whereNull('ended_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->with(['cours:id,nom_cours,code,groupe', 'groupe', 'animateur:id,prenom,nom'])
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get()
            ->map(fn (VisioConference $v) => [
                'id' => $v->id,
                'titre' => $v->titre,
                'scheduled_at' => $v->scheduled_at->toIso8601String(),
                'started_at' => $v->started_at?->toIso8601String(),
                'jitsi_room' => $v->jitsi_room,
                'cours' => [
                    'id' => $v->cours->id,
                    'nom_cours' => $v->cours->nom_cours,
                    'code' => $v->cours->code,
                    'groupe' => $v->cours->groupe,
                ],
                'groupe_numero' => $v->groupe?->numero,
                'animateur' => ['prenom' => $v->animateur->prenom, 'nom' => $v->animateur->nom],
            ]);

        return Inertia::render('Enseignant/Index', [
            'cours' => $cours,
            'thematiques' => $thematiques,
            'travauxRemis' => $travauxRemis,
            'temoinsEnAttente' => $temoinsEnAttente,
            'temoinsApprouves' => $temoinsApprouves,
            'prochainesVisios' => $prochainesVisios,
        ]);
    }

    /**
     * Affiche la fiche détaillée d'un témoin lié aux thématiques de l'enseignant connecté.
     *
     * @throws HttpException si le témoin n'est pas une personne âgée ou n'est pas lié à cet enseignant
     */
    public function showTemoin(User $user): Response
    {
        abort_if($user->role !== 'personne_agee', 403);

        $thematiqueIds = $this->thematiquesVisibles(auth()->user())->pluck('id');

        abort_if(
            $user->thematiquesChoisies()->whereIn('thematiques.id', $thematiqueIds)->doesntExist(),
            403
        );

        $user->load(['thematiquesChoisies:id,nom', 'etablissementsChoisis']);

        return Inertia::render('Enseignant/TemoinShow', [
            'temoin' => [
                'id' => $user->id,
                'prenom' => $user->prenom,
                'nom' => $user->nom,
                'email' => $user->email,
                'description' => $user->description,
                'provenance' => $user->provenance,
                'theme_libre' => $user->themeLibre(),
                'statut' => $user->statut,
                'created_at' => $user->created_at->toIso8601String(),
                'thematiques_choisies' => $user->thematiquesChoisies->map->only('id', 'nom')->values(),
                'engagements_acceptes_le' => $user->engagements_acceptes_le?->toIso8601String(),
                'signature_electronique' => $user->signature_electronique,
            ],
        ]);
    }

    /**
     * Approuve l'inscription d'un témoin lié aux thématiques de l'enseignant connecté.
     *
     * Vérifie que le témoin est bien associé à une thématique de l'enseignant
     * avant d'activer le compte.
     *
     * @throws HttpException si le témoin n'est pas lié à cet enseignant ou n'est pas en attente
     */
    public function approuverTemoin(User $user): RedirectResponse
    {
        $this->autoriserActionTemoin($user);

        $user->statut = 'actif';
        $user->approuve_par_id = auth()->id();
        $user->email_verified_at = now();
        $user->save();

        return back()->with('success', __('Le compte de '.$user->prenom.' '.$user->nom.' a été approuvé.'));
    }

    /**
     * Refuse l'inscription d'un témoin lié aux thématiques de l'enseignant connecté.
     *
     * @throws HttpException si le témoin n'est pas lié à cet enseignant ou n'est pas en attente
     */
    public function declinerTemoin(User $user): RedirectResponse
    {
        $this->autoriserActionTemoin($user);

        $user->statut = 'refuse';
        $user->save();

        return back()->with('success', __('La demande de '.$user->prenom.' '.$user->nom.' a été déclinée.'));
    }

    /**
     * Révoque l'approbation d'un témoin actif lié aux thématiques de l'enseignant connecté.
     *
     * Remet le statut à « en_attente » et retire la vérification de l'email.
     *
     * @throws HttpException si le témoin n'est pas actif ou n'est pas lié à cet enseignant
     */
    public function desapprouverTemoin(User $user): RedirectResponse
    {
        abort_if(
            $user->role !== 'personne_agee' || $user->statut !== 'actif' || $user->approuve_par_id !== auth()->id(),
            403
        );

        $user->statut = 'en_attente';
        $user->approuve_par_id = null;
        $user->email_verified_at = null;
        $user->save();

        return back()->with('success', __('Le compte de '.$user->prenom.' '.$user->nom.' a été remis en attente.'));
    }

    /**
     * Vérifie que l'enseignant connecté est autorisé à agir sur ce témoin.
     *
     * @throws HttpException si le témoin n'est pas une PA en attente ou sans thématique commune
     */
    private function autoriserActionTemoin(User $user): void
    {
        abort_if($user->role !== 'personne_agee' || ! $user->estEnAttente(), 403);

        $thematiqueIds = $this->thematiquesVisibles(auth()->user())->pluck('id');

        abort_if(
            $user->thematiquesChoisies()->whereIn('thematiques.id', $thematiqueIds)->doesntExist(),
            403
        );
    }

    /**
     * Retourne une base de requête pour les témoins liés aux thématiques données.
     */
    private function queryTemoinsParThematiques(Collection $thematiqueIds): Builder
    {
        return User::where('role', 'personne_agee')
            ->whereHas('thematiquesChoisies', fn ($q) => $q->whereIn('thematiques.id', $thematiqueIds))
            ->with('thematiquesChoisies:id,nom')
            ->orderBy('created_at');
    }

    /**
     * Retourne les thématiques visibles par l'enseignant.
     * Si l'enseignant appartient à un établissement, toutes les thématiques du cégep sont visibles.
     * Sinon, seulement les siennes.
     */
    private function thematiquesVisibles(User $user): Builder
    {
        if ($user->etablissement_id) {
            return Thematique::parEtablissement($user->etablissement_id);
        }

        return Thematique::where('enseignant_id', $user->id);
    }
}
