<?php

namespace App\Http\Controllers;

use App\Models\Classe;
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
     * Affiche la page de gestion des groupes d'un étudiant dans une classe.
     *
     * @throws HttpException si l'étudiant n'est pas inscrit
     */
    public function index(Classe $classe): Response
    {
        $user = auth()->user();

        // Vérification d'inscription à la classe — s'applique au modèle Classe, pas Groupe
        abort_if(! $classe->etudiants()->where('users.id', $user->id)->exists(), 403);

        $monGroupe = $classe->groupes()
            ->whereHas('membres', fn ($q) => $q->where('users.id', $user->id))
            ->with(['membres', 'thematiques', 'createur'])
            ->first();

        $autresEtudiants = $classe->etudiants()
            ->where('users.id', '!=', $user->id)
            ->get(['users.id', 'prenom', 'nom']);

        $thematiques = $classe->enseignant->thematiques()->get(['id', 'nom', 'periode_historique']);

        $documents = $classe->documents()->get();

        // Charger les étapes avec la progression personnelle de l'étudiant connecté
        $echeancierEtapes = $classe->echeancierEtapes()
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
            'classe' => $classe->only('id', 'nom_cours', 'code', 'groupe'),
            'monGroupe' => $monGroupe,
            'autresEtudiants' => $autresEtudiants,
            'thematiques' => $thematiques,
            'documents' => $documents,
            'echeancierEtapes' => $echeancierEtapes,
        ]);
    }

    /**
     * Crée un nouveau groupe et associe les membres et thématiques dans une transaction atomique.
     *
     * Les IDs des membres sont filtrés pour ne garder que les étudiants réellement
     * inscrits dans la classe. Les thématiques sont filtrées pour n'accepter que
     * celles appartenant à l'enseignant de la classe. Sans transaction, une erreur
     * sur attach() laisserait un groupe orphelin en base de données.
     *
     * @throws HttpException si l'étudiant n'est pas inscrit ou déjà dans un groupe
     */
    public function store(Request $request, Classe $classe): RedirectResponse
    {
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

        // Garder seulement les membres réellement inscrits dans cette classe
        $membresInscrits = $classe->etudiants()
            ->whereIn('users.id', $validated['membres'] ?? [])
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        // Garder seulement les thématiques appartenant à l'enseignant de la classe
        $thematiquesValides = $classe->enseignant
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
     * Accessible aux membres du groupe, à l'enseignant de la classe et aux admins.
     *
     * @throws AuthorizationException
     */
    public function show(Classe $classe, Groupe $groupe): Response
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe');
        $this->authorize('view', $groupe);

        $user = auth()->user();

        $estMembre = $groupe->membres()->where('users.id', $user->id)->exists();
        $estEnseignant = $groupe->classe->enseignant_id === $user->id;

        $groupe->load([
            'membres',
            'thematiques',
            'notes.auteur',
            'notes.corrections',
            'createur',
            'medias.auteur',
            'temoin',
        ]);

        $thematiquesDispo = $groupe->classe->enseignant
            ->thematiques()
            ->get(['id', 'nom', 'periode_historique']);

        $membreIds = $groupe->membres->pluck('id');
        $etudiantsDispo = $groupe->classe->etudiants()
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
     * Le créateur ne peut pas se retirer lui-même. Seuls les étudiants inscrits
     * dans la classe peuvent être ajoutés. L'opération est atomique.
     *
     * @throws AuthorizationException
     */
    public function updateMembres(Request $request, Classe $classe, Groupe $groupe): RedirectResponse
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe');
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

            // Le créateur ne peut jamais être retiré de son propre groupe
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
     * Accessible à tout membre du groupe. Maximum 3 thématiques. Seules les
     * thématiques appartenant à l'enseignant de la classe sont acceptées.
     *
     * @throws AuthorizationException
     */
    public function updateThematiques(Request $request, Classe $classe, Groupe $groupe): RedirectResponse
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe');
        $this->authorize('manageThematiques', $groupe);

        $validated = $request->validate([
            'thematiques' => ['array', 'max:3'],
            'thematiques.*' => ['integer', 'exists:thematiques,id'],
        ]);

        // Filtrer aux thématiques de l'enseignant de la classe uniquement
        $thematiquesValides = $groupe->classe->enseignant
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
     * Accessible aux membres du groupe uniquement.
     *
     * @throws AuthorizationException
     */
    public function storeNote(Request $request, Groupe $groupe): RedirectResponse
    {
        $groupe->load('classe');
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
     * Seul l'auteur de la note peut la supprimer.
     *
     * @throws HttpException si l'utilisateur n'est pas l'auteur
     */
    public function destroyNote(Groupe $groupe, GroupeNote $note): RedirectResponse
    {
        abort_if($note->user_id !== auth()->id(), 403);

        $note->delete();

        return back()->with('success', __('groupe.note_deleted'));
    }

    /**
     * Crée ou met à jour une correction inline sur une note (enseignant uniquement).
     *
     * Met à jour simultanément le contenu HTML de la note (avec la marque TipTap)
     * et persiste le texte de la correction via un upsert sur le commentaire_id.
     *
     * @throws HttpException si l'utilisateur n'est pas l'enseignant de la classe
     */
    public function upsertNoteCorrection(Request $request, Groupe $groupe, GroupeNote $note): RedirectResponse
    {
        $this->autoriserCorrectionNote($groupe, $note);

        $validated = $request->validate([
            'commentaire_id' => ['required', 'string', 'max:36'],
            'contenu' => ['required', 'string', 'max:1000'],
            'note_html' => ['required', 'string'],
        ]);

        // Mise à jour du contenu HTML de la note (avec la marque insérée)
        $note->update(['contenu' => $validated['note_html']]);

        // Upsert de la correction par commentaire_id (clé naturelle de la marque)
        GroupeNoteCorrection::updateOrCreate(
            ['note_id' => $note->id, 'commentaire_id' => $validated['commentaire_id']],
            ['contenu' => $validated['contenu'], 'user_id' => auth()->id()]
        );

        return back()->with('success', 'Correction enregistrée.');
    }

    /**
     * Supprime une correction inline et met à jour le HTML de la note pour retirer la marque.
     *
     * @throws HttpException si l'utilisateur n'est pas l'enseignant de la classe ou si la note ne correspond pas
     */
    public function destroyNoteCorrection(Request $request, Groupe $groupe, GroupeNote $note, GroupeNoteCorrection $correction): RedirectResponse
    {
        $this->autoriserCorrectionNote($groupe, $note);

        abort_if($correction->note_id !== $note->id, 404);

        $validated = $request->validate([
            'note_html' => ['required', 'string'],
        ]);

        // Mise à jour du HTML sans la marque supprimée
        $note->update(['contenu' => $validated['note_html']]);

        $correction->delete();

        return back()->with('success', 'Correction supprimée.');
    }

    /**
     * Vérifie que l'utilisateur courant peut corriger les notes de ce groupe
     * et que la note appartient bien au groupe.
     *
     * @throws HttpException
     */
    private function autoriserCorrectionNote(Groupe $groupe, GroupeNote $note): void
    {
        $groupe->loadMissing('classe');

        abort_unless(
            $groupe->classe->enseignant_id === auth()->id() || auth()->user()->role === 'admin',
            403
        );

        abort_if($note->groupe_id !== $groupe->id, 404);
    }

    /**
     * Assigne ou désassigne un témoin (personne âgée) au groupe.
     *
     * Réservé à l'enseignant de la classe et aux admins.
     * Passer personne_agee_id = null pour désassigner.
     *
     * @throws AuthorizationException
     */
    public function assignerTemoin(Request $request, Classe $classe, Groupe $groupe): RedirectResponse
    {
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load('classe');
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
     * Seul le créateur peut supprimer. La suppression cascade sur les notes,
     * médias et les entrées des tables pivot via les FK avec cascadeOnDelete.
     *
     * @throws AuthorizationException
     */
    public function destroy(Classe $classe, Groupe $groupe): RedirectResponse
    {
        $groupe->load('classe');
        $this->authorize('delete', $groupe);

        $groupe->delete();

        return redirect()->route('groupes.index', $classe)->with('success', __('groupe.deleted'));
    }
}
