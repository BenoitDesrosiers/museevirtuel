<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Enums\StatutProjetRecherche;
use App\Helpers\HtmlHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Projet\Concerns\RendMuseeProjet;
use App\Http\Requests\Projet\UpdateProjetRechercheRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\MuseePublication;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\User;
use App\Services\ProjetRechercheProps;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Projet de recherche d'un groupe : liste, éditeur, aperçu, contenu et réglages de l'enseignant.
 */
class ProjetRechercheController extends Controller
{
    use RendMuseeProjet;
    use ValidatesGroupeChain;

    /**
     * Affiche toutes les cartes de projets disponibles pour ce groupe.
     *
     * Retourne un tableau de TypeProjets accessibles de l'enseignant du cours,
     * chacun accompagné du ProjetRecherche correspondant (ou null si non encore créé).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function index(Cours $cours, Classe $classe, Groupe $groupe): Response
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load(['membres', 'classe.cours']);
        $this->authorize('view', $groupe);

        $user = auth()->user();
        $estEnseignant = $user->isEnseignant()
            && $cours->enseignant_id === $user->id;

        // Charger les TypeProjets du cours — pas de tous les cours de l'enseignant
        $query = TypeProjet::where('cours_id', $cours->id);

        // Les étudiants ne voient que les types rendus accessibles par l'enseignant
        if (! $estEnseignant && ! $user->isAdmin()) {
            $query->where('accessible', true);
        }

        $typesProjets = $query->get();

        // Précharger tous les projets de ce groupe en une seule requête — évite le N+1
        $projetsParType = ProjetRecherche::where('groupe_id', $groupe->id)
            ->whereIn('type_projet_id', $typesProjets->pluck('id'))
            ->with(['typeProjet', 'conclusions', 'museePublication'])
            ->get()
            ->keyBy('type_projet_id');

        $projets = $typesProjets->map(function (TypeProjet $typeProjet) use ($groupe, $projetsParType): array {
            $projet = $projetsParType->get($typeProjet->id);

            $conclusionsParMembre = $projet ? $projet->conclusions->keyBy('user_id') : collect();

            $conclusions = $groupe->membres->map(function (User $membre) use ($conclusionsParMembre): array {
                $conclusion = $conclusionsParMembre->get($membre->id);

                return [
                    'etudiant' => $membre->only('id', 'prenom', 'nom'),
                    'a_redige' => $conclusion !== null && trim(strip_tags((string) ($conclusion->contenu ?? ''))) !== '',
                ];
            });

            return [
                'typeProjet' => array_merge(
                    $typeProjet->only('id', 'nom', 'description', 'accessible'),
                    ['type' => $typeProjet->type],
                ),
                'projet' => $projet
                    ? [
                        'id' => $projet->id,
                        'titre_projet' => $projet->titre_projet,
                        'completion' => $projet->completion(),
                        'statut' => $projet->synchroniserStatut()->value,
                        'statut_publication' => $typeProjet->isMusee()
                            ? ($projet->museePublication?->statut ?? MuseePublication::STATUT_BROUILLON)
                            : null,
                    ]
                    : null,
                'statut' => $projet?->statutActuel()->value
                    ?? StatutProjetRecherche::fromDates($typeProjet->date_remise, null)->value,
                'conclusions' => $conclusions,
            ];
        });

        return Inertia::render('Projets/Index', [
            'groupe' => $groupe->only('id', 'code', 'classe_id'),
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'projets' => $projets,
            'estEnseignant' => $estEnseignant,
        ]);
    }

    /**
     * Affiche le projet partagé avec l'éditeur de contenu et les conclusions individuelles.
     *
     * Crée le projet s'il n'existe pas encore (premier accès à l'éditeur).
     * Utilise un eager load des conclusions, commentaires et notes pour éviter le N+1.
     * Filtre les annotations de type "correction" pour les étudiants si correction_visible = false.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function show(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetRechercheProps $props): Response
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $groupe->load(['membres', 'thematiques', 'classe.cours.enseignant']);
        $cours->loadMissing('enseignant');
        $this->authorize('view', $groupe);

        $user = auth()->user();
        $estEnseignant = $user->isEnseignant()
            && $cours->enseignant_id === $user->id;

        // Guard accessibilité : si le type de projet n'est pas accessible, les étudiants ne peuvent pas accéder
        if (! $estEnseignant && ! $user->isAdmin()) {
            abort_if(! $typeProjet->accessible, 403, __('projet.type_inaccessible'));
        }

        // Exception assumée à pas d'écriture sur un GET : ouvrir l'éditeur
        // crée le projet partagé s'il n'existe pas encore, car la page en a besoin pour
        // s'afficher.
        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        // Les projets musée ont leur propre éditeur — on les rend séparément
        if ($typeProjet->isMusee()) {
            return $this->renderMuseeShow($cours, $classe, $groupe, $typeProjet, $projet, $estEnseignant);
        }

        return Inertia::render('Projets/Show', $props->pourEditeur($cours, $classe, $groupe, $typeProjet, $projet, $user, $estEnseignant));
    }

    /**
     * Affiche le projet en mode aperçu (lecture seule, sans annotations ni contrôles).
     *
     * Accessible aux membres du groupe et à l'enseignant du cours.
     * Rend les sections dynamiques des 3 types (texte, paragraphes, individuel).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function apercu(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetRechercheProps $props): Response
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $groupe->load(['membres', 'thematiques', 'classe.cours']);
        $this->authorize('view', $groupe);

        $user = auth()->user();
        $estEnseignant = $user->isEnseignant()
            && $cours->enseignant_id === $user->id;

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->with(['typeProjet.sections', 'sectionContenus', 'sectionParagraphes', 'conclusions', 'entrevueConcepts.lignes', 'renvois'])
            ->first();

        $sections = $projet
            ? collect($props->sections($projet, $groupe->membres))->map(fn (array $s) => [
                'id' => $s['id'],
                'label' => $s['label'],
                'description' => $s['description'],
                'ordre' => $s['ordre'],
                'type' => $s['type'],
                'contenu' => $s['type'] === 'texte'
                    ? HtmlHelper::stripAnnotationMarks($s['contenu'])
                    : null,
                'paragraphes' => $s['type'] === 'paragraphes'
                    ? collect($s['paragraphes'] ?? [])->map(fn (array $p) => [
                        'id' => $p['id'],
                        'ordre' => $p['ordre'],
                        'titre' => $p['titre'],
                        'contenu' => HtmlHelper::stripAnnotationMarks($p['contenu']),
                    ])->values()->all()
                    : null,
                'conclusionsParMembre' => $s['type'] === 'individuel'
                    ? collect($s['conclusionsParMembre'] ?? [])
                        ->filter(fn (array $c) => trim(strip_tags((string) ($c['contenu'] ?? ''))) !== '')
                        ->map(fn (array $c) => [
                            'userId' => $c['userId'],
                            'contenu' => HtmlHelper::stripAnnotationMarks($c['contenu']),
                        ])->values()->all()
                    : null,
                // Les concepts d'entrevue sont passés tels quels dans l'aperçu (pas d'annotations HTML à nettoyer)
                'concepts' => $s['type'] === 'entrevue' ? ($s['concepts'] ?? []) : null,
            ])->values()
            : collect();

        return Inertia::render('Projets/Apercu', [
            'groupe' => $groupe->only('id', 'numero', 'classe_id'),
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'typeProjet' => $typeProjet->only('id', 'nom'),
            'thematiques' => $groupe->thematiques->map->only('id', 'nom'),
            'membres' => $groupe->membres->map->only('id', 'prenom', 'nom')->values(),
            'projet' => $projet
                ? ['id' => $projet->id, 'titre_projet' => $projet->titre_projet]
                : null,
            'sections' => $sections,
            'renvois' => $projet
                ? $projet->renvois->map->only('id', 'numero', 'contenu')->values()
                : collect(),
            'estEnseignant' => $estEnseignant,
        ]);
    }

    /**
     * Met à jour le titre du projet et, le contenu manuel de la page titre
     * et de la table des matières (utilisés quand les flags de génération sont désactivés).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function update(UpdateProjetRechercheRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $validated = $request->validated();

        $existant = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->first();

        if ($existant !== null) {
            $this->authorize('editerContenu', $existant);
            $existant->update($validated);
            $projet = $existant;
        } else {
            // Le projet n'existe pas encore — seul un membre du groupe peut le créer
            $this->authorize('contribuer', [ProjetRecherche::class, $groupe]);
            $projet = ProjetRecherche::create([
                'groupe_id' => $groupe->id,
                'type_projet_id' => $typeProjet->id,
                ...$validated,
            ]);
        }

        return response()->json([
            'message' => __('reponse.saved'),
            'completion' => $projet->completion(),
        ]);
    }
}
