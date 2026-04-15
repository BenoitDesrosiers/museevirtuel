<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\EcheancierEtudiantProgress;
use App\Models\Groupe;
use App\Models\GroupeNote;
use App\Models\GroupeNoteCorrection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
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
     * Affiche la page de gestion des groupes d'un étudiant dans une classe (section).
     *
     * @throws HttpException si l'étudiant n'est pas inscrit à la classe
     */
    public function index(Cours $cours, Classe $classe): Response
    {
        abort_if($classe->cours_id !== $cours->id, 404);

        $user = auth()->user();

        abort_if(! $classe->etudiants()->where('users.id', $user->id)->exists(), 403);

        $monGroupe = $classe->groupes()
            ->whereHas('membres', fn ($q) => $q->where('users.id', $user->id))
            ->with(['membres', 'thematiques', 'createur'])
            ->first();

        $autresEtudiants = $classe->etudiants()
            ->where('users.id', '!=', $user->id)
            ->get(['users.id', 'prenom', 'nom']);

        $thematiques = $cours->enseignant->thematiques()->get(['id', 'nom', 'periode_historique']);

        $documents = $cours->documents()->get();

        $echeancierEtapes = $cours->echeancierEtapes()
            ->orderBy('semaine')
            ->orderBy('ordre')
            ->get()
            ->map(fn ($etape) => [
                'id' => $etape->id,
                'semaine' => $etape->semaine,
                'etape' => $etape->etape,
                'is_done_etudiant' => EcheancierEtudiantProgress::where('echeancier_etape_id', $etape->id)
                    ->where('user_id', $user->id)
                    ->value('is_done') ?? false,
            ]);

        return Inertia::render('Classes/Groupes', [
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'monGroupe' => $monGroupe,
            'autresEtudiants' => $autresEtudiants,
            'thematiques' => $thematiques,
            'documents' => $documents,
            'echeancierEtapes' => $echeancierEtapes,
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

        $thematiquesValides = $cours->enseignant
            ->thematiques()
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

        $groupe->load([
            'membres',
            'thematiques',
            'notes.auteur',
            'notes.corrections',
            'createur',
            'medias.auteur',
            'temoin',
        ]);

        $thematiquesDispo = $cours->enseignant
            ->thematiques()
            ->get(['id', 'nom', 'periode_historique']);

        $membreIds = $groupe->membres->pluck('id');
        $etudiantsDispo = $classe->etudiants()
            ->whereNotIn('users.id', $membreIds)
            ->get(['users.id', 'prenom', 'nom']);

        $groupeThematiqueIds = $groupe->thematiques->pluck('id');

        $temoinsDisponibles = $estEnseignant || $user->isAdmin()
            ? User::where('role', 'personne_agee')
                ->where('statut', 'actif')
                ->when($groupeThematiqueIds->isNotEmpty(), function ($query) use ($groupeThematiqueIds) {
                    $query->whereHas('thematiquesChoisies', function ($q) use ($groupeThematiqueIds) {
                        $q->whereIn('thematiques.id', $groupeThematiqueIds);
                    });
                })
                ->get(['id', 'prenom', 'nom'])
            : collect();

        return Inertia::render('Groupes/Show', [
            'groupe' => $groupe,
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'estMembre' => $estMembre,
            'estEnseignant' => $estEnseignant,
            'estCreateur' => $groupe->created_by === $user->id,
            'thematiquesDispo' => $thematiquesDispo,
            'etudiantsDispo' => $etudiantsDispo,
            'temoinsDisponibles' => $temoinsDisponibles,
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

        DB::transaction(function () use ($validated, $user, $classe, $groupe) {
            if (! empty($validated['ajouter'])) {
                $aAjouter = $classe->etudiants()
                    ->whereIn('users.id', $validated['ajouter'])
                    ->pluck('users.id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                if (! empty($aAjouter)) {
                    $groupe->membres()->syncWithoutDetaching($aAjouter);
                }
            }

            $aRetirer = array_diff(
                array_map('intval', $validated['retirer'] ?? []),
                [(int) $user->id]
            );

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

        $thematiquesValides = $cours->enseignant
            ->thematiques()
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
