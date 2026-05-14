<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeNote;
use App\Models\GroupeNoteCorrection;
use App\Models\Thematique;
use App\Models\User;
use App\Models\VisioConference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GroupeController extends Controller
{
    /**
     * Redirige vers groupes.show si l'étudiant appartient déjà à un groupe,
     * sinon affiche la page de création de groupe.
     *
     * @throws HttpException si l'étudiant n'est pas inscrit à la classe
     */
    public function index(Cours $cours, Classe $classe): Response|RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);

        $user = auth()->user();

        abort_if(! $classe->etudiants()->where('users.id', $user->id)->exists(), 403);

        $monGroupe = $classe->groupes()
            ->whereHas('membres', fn ($q) => $q->where('users.id', $user->id))
            ->first();

        if ($monGroupe) {
            return redirect()->route('groupes.show', [$cours, $classe, $monGroupe]);
        }

        $dejaGroupes = $this->membresDejaGroupes($classe);

        $autresEtudiants = $classe->etudiants()
            ->where('users.id', '!=', $user->id)
            ->whereNotIn('users.id', $dejaGroupes)
            ->get(['users.id', 'prenom', 'nom']);

        $thematiques = $this->thematiquesVisibles($cours->enseignant)
            ->get(['id', 'nom', 'periode_historique']);

        return Inertia::render('Classes/Groupes', [
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'autresEtudiants' => $autresEtudiants,
            'thematiques' => $thematiques,
        ]);
    }

    /**
     * Crée un nouveau groupe dans une classe et associe membres et thématiques.
     *
     * @throws HttpException si l'étudiant n'est pas inscrit ou déjà dans un groupe
     */
    public function store(Request $request, Cours $cours, Classe $classe): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);

        $user = auth()->user();

        abort_if(! $classe->etudiants()->where('users.id', $user->id)->exists(), 403);

        $dejaDansGroupe = $classe->groupes()
            ->whereHas('membres', fn ($q) => $q->where('users.id', $user->id))
            ->exists();

        if ($dejaDansGroupe) {
            return back()->withErrors(['general' => __('groupe.already_member')]);
        }

        $validated = $request->validate([
            'membres' => ['array'],
            'membres.*' => ['integer', 'exists:users,id'],
            'thematiques' => ['array', 'max:3'],
            'thematiques.*' => ['integer', 'exists:thematiques,id'],
        ]);

        $membresInscrits = $classe->etudiants()
            ->whereIn('users.id', $validated['membres'] ?? [])
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        // Exclure les membres déjà dans un autre groupe de la même classe
        $membresInscrits = array_diff($membresInscrits, $this->membresDejaGroupes($classe));

        // Vérifier les contraintes de taille d'équipe du cours
        $totalMembres = count(array_unique(array_merge([(int) $user->id], $membresInscrits)));

        if ($cours->taille_equipe_min !== null && $totalMembres < $cours->taille_equipe_min) {
            return back()->withErrors(['membres' => "Le groupe doit avoir au moins {$cours->taille_equipe_min} membre(s) (vous inclus)."]);
        }

        if ($cours->taille_equipe_max !== null && $totalMembres > $cours->taille_equipe_max) {
            return back()->withErrors(['membres' => "Le groupe ne peut pas avoir plus de {$cours->taille_equipe_max} membre(s)."]);
        }

        $thematiquesValides = $this->thematiquesVisibles($cours->enseignant)
            ->whereIn('id', $validated['thematiques'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        DB::transaction(function () use ($user, $classe, $membresInscrits, $thematiquesValides) {
            $groupe = Groupe::create([
                'classe_id' => $classe->id,
                'created_by' => $user->id,
            ]);

            // Le créateur est toujours inclus même s'il ne s'est pas sélectionné lui-même
            $membres = array_unique(array_merge([(int) $user->id], $membresInscrits));
            $groupe->membres()->attach($membres);

            if (! empty($thematiquesValides)) {
                $groupe->thematiques()->attach($thematiquesValides);
            }
        });

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
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe.cours');
        $this->authorize('view', $groupe);

        $user = auth()->user();

        $estMembre = $groupe->membres()->where('users.id', $user->id)->exists();
        $estEnseignant = $cours->enseignant_id === $user->id;
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

        $thematiquesDispo = $this->thematiquesVisibles($cours->enseignant)
            ->get(['id', 'nom', 'periode_historique']);

        $membreIds = $groupe->membres->pluck('id');

        $etudiantsDispo = $classe->etudiants()
            ->whereNotIn('users.id', $membreIds)
            ->whereNotIn('users.id', $this->membresDejaGroupes($classe, $groupe->id))
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
            ->map(fn (VisioConference $v) => [
                'id' => $v->id,
                'cours_id' => $v->cours_id,
                'groupe_id' => $v->groupe_id,
                'jitsi_room' => $v->jitsi_room,
                'titre' => $v->titre,
                'scheduled_at' => $v->scheduled_at?->toIso8601String(),
                'started_at' => $v->started_at?->toIso8601String(),
                'ended_at' => $v->ended_at?->toIso8601String(),
                'recording_url' => $v->recording_url,
                // URL sécurisée pour lire l'enregistrement : stream interne si fichier local, URL externe sinon
                'recording_stream_url' => $v->recording_path
                    ? route('cours.visio.recording', [$cours, $v])
                    : $v->recording_url,
                'has_recording' => (bool) ($v->recording_path || $v->recording_url),
                // Vrai si le fichier est stocké localement (permet d'afficher un <video> plutôt qu'un lien)
                'recording_is_local' => (bool) $v->recording_path,
                'animateur' => [
                    'id' => $v->animateur->id,
                    'prenom' => $v->animateur->prenom,
                    'nom' => $v->animateur->nom,
                ],
            ]);

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
        ]);
    }

    /**
     * Ajoute ou retire des membres du groupe (créateur uniquement).
     *
     * @throws AuthorizationException
     */
    public function updateMembres(Request $request, Cours $cours, Classe $classe, Groupe $groupe): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe.cours');
        $this->authorize('manageMembers', $groupe);

        $validated = $request->validate([
            'ajouter' => ['array'],
            'ajouter.*' => ['integer', 'exists:users,id'],
            'retirer' => ['array'],
            'retirer.*' => ['integer', 'exists:users,id'],
        ]);

        $user = auth()->user();

        // Pré-calculer les changements avant transaction pour pouvoir valider la taille
        $aAjouter = [];
        if (! empty($validated['ajouter'])) {
            $aAjouter = $classe->etudiants()
                ->whereIn('users.id', $validated['ajouter'])
                ->pluck('users.id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            // Exclure les étudiants déjà dans un autre groupe de la même classe
            $aAjouter = array_values(array_diff($aAjouter, $this->membresDejaGroupes($classe, $groupe->id)));
        }

        $aRetirer = array_values(array_diff(
            array_map('intval', $validated['retirer'] ?? []),
            [(int) $user->id]
        ));

        // Vérifier les contraintes de taille d'équipe du cours
        if ($cours->taille_equipe_min !== null || $cours->taille_equipe_max !== null) {
            $membresCourants = $groupe->membres()->pluck('users.id')->map(fn ($id) => (int) $id)->toArray();
            $nouveauxMembres = array_values(array_unique(array_diff(
                array_merge($membresCourants, $aAjouter),
                $aRetirer,
            )));
            $totalMembres = count($nouveauxMembres);

            if ($cours->taille_equipe_min !== null && $totalMembres < $cours->taille_equipe_min) {
                return back()->withErrors(['membres' => "Le groupe doit avoir au moins {$cours->taille_equipe_min} membre(s)."]);
            }

            if ($cours->taille_equipe_max !== null && $totalMembres > $cours->taille_equipe_max) {
                return back()->withErrors(['membres' => "Le groupe ne peut pas avoir plus de {$cours->taille_equipe_max} membre(s)."]);
            }
        }

        DB::transaction(function () use ($aAjouter, $aRetirer, $groupe) {
            if (! empty($aAjouter)) {
                $groupe->membres()->syncWithoutDetaching($aAjouter);
            }

            if (! empty($aRetirer)) {
                $groupe->membres()->detach($aRetirer);
            }
        });

        return back()->with('success', __('groupe.members_updated'));
    }

    /**
     * Remplace complètement les thématiques du groupe (sync).
     *
     * @throws AuthorizationException
     */
    public function updateThematiques(Request $request, Cours $cours, Classe $classe, Groupe $groupe): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe.cours');
        $this->authorize('manageThematiques', $groupe);

        $validated = $request->validate([
            'thematiques' => ['array', 'max:3'],
            'thematiques.*' => ['integer', 'exists:thematiques,id'],
        ]);

        $thematiquesValides = $this->thematiquesVisibles($cours->enseignant)
            ->whereIn('id', $validated['thematiques'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        DB::transaction(function () use ($thematiquesValides, $groupe) {
            $groupe->thematiques()->sync($thematiquesValides);
        });

        return back()->with('success', __('groupe.thematiques_updated'));
    }

    /**
     * Ajoute une note collaborative au groupe.
     *
     * @throws AuthorizationException
     */
    public function storeNote(Request $request, Cours $cours, Classe $classe, Groupe $groupe): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe.cours');
        $this->authorize('addNote', $groupe);

        $validated = $request->validate([
            'contenu' => ['required', 'string', 'max:2000'],
        ]);

        GroupeNote::create([
            'groupe_id' => $groupe->id,
            'user_id' => auth()->id(),
            'contenu' => $validated['contenu'],
        ]);

        return back()->with('success', __('groupe.note_created'));
    }

    /**
     * Supprime une note du groupe.
     *
     * @throws HttpException si l'utilisateur n'est pas l'auteur
     */
    public function destroyNote(Cours $cours, Classe $classe, Groupe $groupe, GroupeNote $note): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($note->groupe_id !== $groupe->id, 404);
        abort_if($note->user_id !== auth()->id(), 403);

        $note->delete();

        return back()->with('success', __('groupe.note_deleted'));
    }

    /**
     * Crée ou met à jour une correction inline sur une note (enseignant uniquement).
     *
     * @throws HttpException si l'utilisateur n'est pas l'enseignant du cours
     */
    public function upsertNoteCorrection(Request $request, Cours $cours, Classe $classe, Groupe $groupe, GroupeNote $note): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->autoriserCorrectionNote($cours, $groupe, $note);

        $validated = $request->validate([
            'commentaire_id' => ['required', 'string', 'max:36'],
            'contenu' => ['required', 'string', 'max:1000'],
            'note_html' => ['required', 'string'],
        ]);

        $note->update(['contenu' => $validated['note_html']]);

        GroupeNoteCorrection::updateOrCreate(
            ['note_id' => $note->id, 'commentaire_id' => $validated['commentaire_id']],
            ['contenu' => $validated['contenu'], 'user_id' => auth()->id()]
        );

        return back()->with('success', 'Correction enregistrée.');
    }

    /**
     * Supprime une correction inline et met à jour le HTML de la note.
     *
     * @throws HttpException
     */
    public function destroyNoteCorrection(Request $request, Cours $cours, Classe $classe, Groupe $groupe, GroupeNote $note, GroupeNoteCorrection $correction): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->autoriserCorrectionNote($cours, $groupe, $note);

        abort_if($correction->note_id !== $note->id, 404);

        $validated = $request->validate([
            'note_html' => ['required', 'string'],
        ]);

        $note->update(['contenu' => $validated['note_html']]);
        $correction->delete();

        return back()->with('success', 'Correction supprimée.');
    }

    /**
     * Assigne ou désassigne un témoin (personne âgée) au groupe.
     *
     * @throws AuthorizationException
     */
    public function assignerTemoin(Request $request, Cours $cours, Classe $classe, Groupe $groupe): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe.cours');
        $this->authorize('assignerTemoin', $groupe);

        $validated = $request->validate([
            'personne_agee_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', 'personne_agee')->where('statut', 'actif'),
            ],
        ]);

        $groupe->update(['personne_agee_id' => $validated['personne_agee_id']]);

        return back()->with('success', 'Témoin mis à jour.');
    }

    /**
     * Supprime un groupe entier.
     *
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe.cours');
        $this->authorize('delete', $groupe);

        $groupe->delete();

        return redirect()->route('groupes.index', [$cours, $classe])->with('success', __('groupe.deleted'));
    }

    /**
     * Retourne un query builder pour les thématiques visibles par un enseignant :
     * les siennes + celles de son établissement (si applicable).
     *
     * Le distinct() évite les doublons quand une thématique satisfait les deux
     * conditions (enseignant_id et etablissement_id tous deux définis).
     */
    private function thematiquesVisibles(User $enseignant): Builder
    {
        return Thematique::distinct()->where(function ($q) use ($enseignant) {
            $q->where('enseignant_id', $enseignant->id);
            if ($enseignant->etablissement_id) {
                $q->orWhere('etablissement_id', $enseignant->etablissement_id);
            }
        });
    }

    /**
     * Retourne les IDs (int) des étudiants déjà dans un groupe de la classe.
     * Exclut optionnellement le groupe courant (utile pour show/updateMembres).
     *
     * @return array<int>
     */
    private function membresDejaGroupes(Classe $classe, ?int $excludeGroupeId = null): array
    {
        $query = $classe->groupes()->with('membres:id');

        if ($excludeGroupeId !== null) {
            $query->where('id', '!=', $excludeGroupeId);
        }

        return $query->get()
            ->flatMap(fn ($g) => $g->membres->pluck('id'))
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    /**
     * Vérifie que l'utilisateur courant peut corriger les notes de ce groupe.
     *
     * @throws HttpException
     */
    private function autoriserCorrectionNote(Cours $cours, Groupe $groupe, GroupeNote $note): void
    {
        abort_unless(
            $cours->enseignant_id === auth()->id() || auth()->user()->role === 'admin',
            403
        );

        abort_if($note->groupe_id !== $groupe->id, 404);
    }
}
