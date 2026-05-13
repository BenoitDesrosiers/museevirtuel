<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\EcheancierEtudiantProgress;
use App\Models\ProjetGrilleNote;
use App\Models\ProjetNote;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ClasseController extends Controller
{
    /**
     * Liste les sections (classes) d'un cours dans lesquelles l'étudiant est inscrit.
     *
     * Réservé aux étudiants.
     */
    public function indexForStudent(Cours $cours): Response
    {
        $user = auth()->user();

        $classes = $cours->classes()
            ->whereHas('etudiants', fn ($q) => $q->where('users.id', $user->id))
            ->withCount('groupes')
            ->orderBy('numero')
            ->get(['id', 'numero', 'code', 'nom', 'jour_semaine', 'plage_horaire', 'cours_id']);

        $etapes = $cours->echeancierEtapes()->get();

        $progressions = EcheancierEtudiantProgress::whereIn('echeancier_etape_id', $etapes->pluck('id'))
            ->where('user_id', $user->id)
            ->pluck('is_done', 'echeancier_etape_id');

        $echeancierEtapes = $etapes->map(fn ($etape) => [
            'id' => $etape->id,
            'semaine' => $etape->semaine,
            'etape' => $etape->etape,
            'is_done' => $etape->is_done,
            'ordre' => $etape->ordre,
            'etudiant_done' => (bool) ($progressions[$etape->id] ?? false),
        ]);

        return Inertia::render('Cours/Classes', [
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'classes' => $classes,
            'echeancierEtapes' => $echeancierEtapes,
        ]);
    }

    /**
     * Affiche le détail d'une classe (section) avec ses groupes.
     *
     * Accessible aux étudiants inscrits, à l'enseignant et aux admins.
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    public function show(Cours $cours, Classe $classe): Response
    {
        abort_if($classe->cours_id !== $cours->id, 404);

        $classe->load('cours');
        $this->authorize('view', $classe);

        $user = auth()->user();
        $estEnseignant = $cours->enseignant_id === $user->id;

        $classe->load([
            'groupes.membres',
            'groupes.thematiques',
            'groupes.temoin',
            'etudiants' => fn ($query) => $query->withPivot('statut_cours'),
        ]);

        // TypeProjets du cours — pour les boutons d'aperçu notes (enseignant seulement)
        $typesProjets = $estEnseignant
            ? $cours->typesProjets()->get(['id', 'nom', 'ponderation', 'is_sommatif'])
            : collect();

        // Échéancier, documents et objectifs — pour les étudiants uniquement
        $echeancierEtapes = collect();
        $documents = collect();
        $objectifs = collect();

        if (! $estEnseignant) {
            $etapes = $cours->echeancierEtapes()->get();
            $progressions = EcheancierEtudiantProgress::whereIn('echeancier_etape_id', $etapes->pluck('id'))
                ->where('user_id', $user->id)
                ->pluck('is_done', 'echeancier_etape_id');

            $echeancierEtapes = $etapes->map(fn ($etape) => [
                'id' => $etape->id,
                'semaine' => $etape->semaine,
                'etape' => $etape->etape,
                'is_done' => $etape->is_done,
                'ordre' => $etape->ordre,
                'etudiant_done' => (bool) ($progressions[$etape->id] ?? false),
            ]);

            $documents = $cours->documents()->get()->map(fn ($doc) => [
                'id' => $doc->id,
                'nom_original' => $doc->nom_original,
                'url' => $doc->url,
                'type' => $doc->type,
                'taille' => $doc->taille,
            ]);

            $objectifs = $cours->objectifs()->orderBy('ordre')->get()->map(fn ($o) => [
                'id' => $o->id,
                'contenu' => $o->contenu,
                'ordre' => $o->ordre,
            ]);
        }

        return Inertia::render('Classes/Show', [
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'classe' => $classe,
            'estEnseignant' => $estEnseignant,
            'typesProjets' => $typesProjets,
            'echeancierEtapes' => $echeancierEtapes,
            'documents' => $documents,
            'objectifs' => $objectifs,
        ]);
    }

    /**
     * Affiche la page d'aperçu des notes de toute la classe pour un TypeProjet donné.
     *
     * Agrège les notes de tous les groupes de la classe : une ligne par étudiant
     * avec son numéro DA et sa note finale (format "DA NOTE").
     * Accessible aux enseignants et admins uniquement.
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    public function apercuNotesClasse(Cours $cours, Classe $classe, TypeProjet $typeProjet): Response
    {
        $this->autoriserEnseignantOuAdmin($cours, $classe);
        $classe->load('groupes.membres');

        // Déterminer si ce TypeProjet utilise une grille personnalisée
        $typeProjet->loadMissing('grille');
        $useGrillePerso = $typeProjet->grille !== null;

        // Charger tous les projets de cette classe pour ce TypeProjet en une seule requête — évite N+1
        $relationsNotes = $useGrillePerso
            ? ['notesGrille.critere', 'malusAppliques.malus']
            : ['notes'];

        $projets = ProjetRecherche::whereIn('groupe_id', $classe->groupes->pluck('id'))
            ->where('type_projet_id', $typeProjet->id)
            ->with($relationsNotes)
            ->get()
            ->keyBy('groupe_id');

        $lignes = collect();

        foreach ($classe->groupes as $groupe) {
            $projet = $projets->get($groupe->id);

            foreach ($groupe->membres as $membre) {
                $note = $projet
                    ? ($useGrillePerso
                        ? ProjetGrilleNote::noteFinale($projet, $membre)
                        : ProjetNote::noteFinale($projet, $membre))
                    : null;
                $lignes->push([
                    'da' => preg_replace('/\D/', '', (string) $membre->no_da),
                    'note' => $note !== null ? (string) $note : null,
                ]);
            }
        }

        return Inertia::render('Classes/ApercuNotes', [
            'cours' => $cours->only('id', 'nom_cours'),
            'classe' => ['id' => $classe->id, 'numero' => $classe->numero, 'nom' => $classe->nom],
            'typeProjet' => ['id' => $typeProjet->id, 'nom' => $typeProjet->nom],
            'lignes' => $lignes->values(),
        ]);
    }

    /**
     * Affiche la page d'aperçu des notes accumulées (pondérées) de toute la classe.
     *
     * Agrège les notes finales de chaque étudiant sur tous les TypeProjets ayant
     * une pondération définie, et calcule le total pondéré : Σ(noteFinale × pondération / 100).
     * Accessible aux enseignants et admins uniquement.
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    public function apercuNotesAccumulees(Cours $cours, Classe $classe): Response
    {
        $this->autoriserEnseignantOuAdmin($cours, $classe);
        $classe->load('groupes.membres');

        // TypeProjets sommatifs du cours ayant une pondération définie.
        // Les projets non-sommatifs (is_sommatif = false) n'ont pas de poids sur la note finale.
        $typesProjets = $cours->typesProjets()
            ->where('is_sommatif', true)
            ->whereNotNull('ponderation')
            ->with('grille')
            ->orderBy('nom')
            ->get();

        // Flag par TypeProjet calculé une seule fois — évite le double accès à $tp->grille
        $useGrilleParId = $typesProjets->mapWithKeys(
            fn ($tp) => [$tp->id => $tp->grille !== null]
        )->all();

        // Charger tous les projets de la classe en une seule requête par TypeProjet — évite N+1
        $projetsPar = [];
        foreach ($typesProjets as $tp) {
            $relations = $useGrilleParId[$tp->id]
                ? ['notesGrille.critere', 'malusAppliques.malus']
                : ['notes'];

            $projetsPar[$tp->id] = ProjetRecherche::whereIn('groupe_id', $classe->groupes->pluck('id'))
                ->where('type_projet_id', $tp->id)
                ->with($relations)
                ->get()
                ->keyBy('groupe_id');
        }

        $lignes = collect();

        foreach ($classe->groupes as $groupe) {
            foreach ($groupe->membres as $membre) {
                $notesParType = [];
                $total = 0.0;

                foreach ($typesProjets as $tp) {
                    $useGrillePerso = $useGrilleParId[$tp->id];
                    $projet = $projetsPar[$tp->id]->get($groupe->id);

                    $note = $projet
                        ? ($useGrillePerso
                            ? ProjetGrilleNote::noteFinale($projet, $membre)
                            : ProjetNote::noteFinale($projet, $membre))
                        : null;

                    $notesParType[$tp->id] = $note !== null ? (float) $note : null;

                    // Contribution pondérée : 0 si pas de note
                    $total += ($note !== null ? (float) $note : 0.0) * ((float) $tp->ponderation / 100);
                }

                $lignes->push([
                    'da' => preg_replace('/\D/', '', (string) $membre->no_da),
                    'prenom' => $membre->prenom,
                    'nom' => $membre->nom,
                    'notes_par_type' => $notesParType,
                    'total' => round($total, 2),
                ]);
            }
        }

        $somme = $typesProjets->sum('ponderation');

        return Inertia::render('Classes/ApercuNotesAccumulees', [
            'cours' => $cours->only('id', 'nom_cours'),
            'classe' => ['id' => $classe->id, 'numero' => $classe->numero, 'nom' => $classe->nom],
            'typesProjets' => $typesProjets->map(fn ($tp) => [
                'id' => $tp->id,
                'nom' => $tp->nom,
                'ponderation' => (float) $tp->ponderation,
            ])->values(),
            'lignes' => $lignes->values(),
            'sommePonderations' => (float) $somme,
        ]);
    }

    /**
     * Crée une nouvelle classe (section) dans un cours.
     *
     * Réservé à l'enseignant du cours et aux admins.
     *
     * @throws AuthorizationException
     */
    public function store(Request $request, Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);

        $validated = $request->validate([
            'numero' => ['required', 'string', 'size:5', 'regex:/^\d{5}$/', Rule::unique('classes')->where(fn ($q) => $q->where('cours_id', $cours->id))],
            'nom' => ['nullable', 'string', 'max:100'],
            'jour_semaine' => ['nullable', 'string', 'max:20'],
            'plage_horaire' => ['nullable', 'string', 'max:50'],
        ]);

        Classe::create(array_merge(
            ['cours_id' => $cours->id, 'code' => $cours->code],
            array_filter($validated, fn ($v) => $v !== null)
        ));

        return back()->with('success', 'Classe créée.');
    }

    /**
     * Met à jour une classe (section) : code et/ou nom.
     *
     * Réservé à l'enseignant du cours et aux admins.
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    public function update(Request $request, Cours $cours, Classe $classe): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        $this->authorize('update', $classe);

        $validated = $request->validate([
            'numero' => ['required', 'string', 'size:5', 'regex:/^\d{5}$/', Rule::unique('classes')->where(fn ($q) => $q->where('cours_id', $cours->id))->ignore($classe->id)],
            'nom' => ['nullable', 'string', 'max:100'],
            'jour_semaine' => ['nullable', 'string', 'max:20'],
            'plage_horaire' => ['nullable', 'string', 'max:50'],
        ]);

        $classe->update(array_merge($validated, ['code' => $cours->code]));

        return back()->with('success', 'Classe mise à jour.');
    }

    /**
     * Valide que la classe appartient au cours, que l'utilisateur a accès via la Policy,
     * et qu'il est l'enseignant du cours ou un admin.
     *
     * Factorisé ici car ce triplet apparaît dans toutes les méthodes réservées
     * aux enseignants (apercuNotesClasse, apercuNotesAccumulees, etc.).
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    private function autoriserEnseignantOuAdmin(Cours $cours, Classe $classe): void
    {
        abort_if($classe->cours_id !== $cours->id, 404);

        $classe->load('cours');
        $this->authorize('view', $classe);

        $user = auth()->user();
        abort_unless(
            $user->role === 'admin' || $cours->enseignant_id === $user->id,
            403,
        );
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

        return redirect()->route('cours.show', $cours)->with('success', 'Classe supprimée.');
    }
}
