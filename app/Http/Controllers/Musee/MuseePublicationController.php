<?php

namespace App\Http\Controllers\Musee;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\MuseeBloc;
use App\Models\MuseeMeta;
use App\Models\MuseePage;
use App\Models\MuseePublication;
use App\Models\ProjetCritereCorrection;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MuseePublicationController extends Controller
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Valide les relations et charge le ProjetRecherche du groupe.
     *
     * @throws HttpException
     */
    private function chargerProjet(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): ProjetRecherche {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_unless($typeProjet->isMusee(), 404);

        return ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();
    }

    /**
     * Récupère ou crée l'enregistrement MuseePublication pour un projet.
     */
    private function obtenirPublication(ProjetRecherche $projet): MuseePublication
    {
        return MuseePublication::firstOrCreate(
            ['projet_recherche_id' => $projet->id],
            ['est_copie_prof' => false, 'est_publie' => false, 'statut' => MuseePublication::STATUT_BROUILLON],
        );
    }

    /**
     * Coche ou décoche automatiquement le critère de publication du musée.
     *
     * @param  bool  $verifie  true → approuvé (points accordés), false → rejeté (critère décoché)
     */
    private function majCriterePublication(ProjetRecherche $projet, bool $verifie): void
    {
        $projet->load('typeProjet');
        $critere = $projet->typeProjet->critereMuseePublication();

        if ($critere === null) {
            return;
        }

        ProjetCritereCorrection::updateOrCreate(
            ['projet_id' => $projet->id, 'critere_id' => $critere->id, 'user_id' => null],
            ['verifie' => $verifie, 'points' => null],
        );
    }

    // ─── Validation du contenu obligatoire ───────────────────────────────────

    /**
     * Vérifie que toutes les sections et zones obligatoires sont remplies.
     *
     * Retourne un tableau d'erreurs (vide si tout est valide) :
     * - section_manquante : section obligatoire sans le nombre minimum de pages
     * - zone_manquante : zone obligatoire sans bloc dans une page donnée
     *
     * @return array<string, mixed>
     */
    private function validerObligatoires(ProjetRecherche $projet, TypeProjet $typeProjet): array
    {
        $erreurs = [];

        // Charger toutes les sections obligatoires du TypeProjet
        $sections = TypeProjetSection::where('type_projet_id', $typeProjet->id)
            ->where('est_obligatoire', true)
            ->get();

        // Pages existantes du projet, indexées par section_id
        $pagesParSection = MuseePage::where('projet_recherche_id', $projet->id)
            ->get()
            ->groupBy('section_id');

        foreach ($sections as $section) {
            $pages = $pagesParSection->get($section->id, collect());
            $count = $pages->count();

            // Vérifier le nombre minimum de pages pour la section
            if ($count < $section->min_occurrences) {
                $erreurs[] = [
                    'type' => 'section_manquante',
                    'section_id' => $section->id,
                    'label' => $section->label,
                    'message' => 'La section «'.$section->label.'» requiert au moins '.$section->min_occurrences.' page(s) (actuellement : '.$count.').',
                ];

                continue;
            }

            // Vérifier les zones obligatoires de chaque page de cette section
            $zonesObligatoires = collect($section->musee_canevas['zones'] ?? [])
                ->filter(fn (array $zone) => ! empty($zone['obligatoire']));

            if ($zonesObligatoires->isEmpty()) {
                continue;
            }

            foreach ($pages as $page) {
                // Blocs liés à cette page, indexés par zone_id
                $blocsParZone = MuseeBloc::where('musee_page_id', $page->id)
                    ->whereNotNull('zone_id')
                    ->pluck('zone_id')
                    ->flip(); // flip pour tester l'existence en O(1)

                foreach ($zonesObligatoires as $zone) {
                    if (! $blocsParZone->has($zone['id'])) {
                        $erreurs[] = [
                            'type' => 'zone_manquante',
                            'section_id' => $section->id,
                            'page_id' => $page->id,
                            'zone_id' => $zone['id'],
                            'zone_label' => $zone['label'] ?? $zone['id'],
                            'page_titre' => $page->titre,
                            'message' => 'La zone «'.($zone['label'] ?? $zone['id']).'» de la page «'.$page->titre.'» est obligatoire et doit être remplie.',
                        ];
                    }
                }
            }
        }

        return $erreurs;
    }

    // ─── Actions étudiant ─────────────────────────────────────────────────────

    /**
     * L'étudiant soumet son musée pour approbation par l'enseignant.
     *
     * Vérifie que toutes les sections et zones obligatoires sont remplies avant
     * de passer le statut de 'brouillon' ou 'rejete' à 'soumis'.
     * L'édition est bloquée jusqu'à la décision de l'enseignant.
     *
     * @throws HttpException
     */
    public function soumettre(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        $groupe->loadMissing('membres');
        abort_unless($groupe->membres->contains('id', auth()->id()), 403);

        $projet = $this->chargerProjet($cours, $classe, $groupe, $typeProjet);
        $publication = $this->obtenirPublication($projet);

        // Seuls les statuts 'brouillon' et 'rejete' peuvent être soumis
        abort_unless(
            in_array($publication->statut, [MuseePublication::STATUT_BROUILLON, MuseePublication::STATUT_REJETE]),
            422,
            'Ce musée a déjà été soumis ou approuvé.',
        );

        // Vérifier que toutes les sections et zones obligatoires sont complètes
        $erreurs = $this->validerObligatoires($projet, $typeProjet);

        if (! empty($erreurs)) {
            return back()->withErrors([
                'obligatoires' => array_column($erreurs, 'message'),
                'details' => $erreurs,
            ]);
        }

        $publication->update([
            'statut' => MuseePublication::STATUT_SOUMIS,
            'soumis_le' => now(),
            'raison_rejet' => null,
        ]);

        return back()->with('success', 'Musée soumis pour approbation. L\'enseignant sera notifié.');
    }

    /**
     * L'étudiant annule sa soumission pour reprendre l'édition.
     *
     * Uniquement possible si le statut est encore 'soumis' (pas encore traité).
     *
     * @throws HttpException
     */
    public function annulerSoumission(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        $groupe->loadMissing('membres');
        abort_unless($groupe->membres->contains('id', auth()->id()), 403);

        $projet = $this->chargerProjet($cours, $classe, $groupe, $typeProjet);
        $publication = $this->obtenirPublication($projet);

        abort_unless($publication->statut === MuseePublication::STATUT_SOUMIS, 422, 'La soumission ne peut pas être annulée.');

        $publication->update([
            'statut' => MuseePublication::STATUT_BROUILLON,
            'soumis_le' => null,
        ]);

        return back()->with('success', 'Soumission annulée. Vous pouvez continuer l\'édition.');
    }

    // ─── Actions enseignant ───────────────────────────────────────────────────

    /**
     * L'enseignant approuve le musée soumis.
     *
     * Passe le statut à 'approuve' et publie le musée dans la galerie publique.
     *
     * @throws HttpException
     */
    public function approuver(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        abort_unless($cours->enseignant_id === auth()->id(), 403);

        $projet = $this->chargerProjet($cours, $classe, $groupe, $typeProjet);
        $publication = $this->obtenirPublication($projet);

        abort_unless($publication->statut === MuseePublication::STATUT_SOUMIS, 422, 'Ce musée n\'est pas en attente d\'approbation.');

        $publication->update([
            'statut' => MuseePublication::STATUT_APPROUVE,
            'est_publie' => true,
            'publie_le' => now(),
            'publie_par' => auth()->id(),
            'raison_rejet' => null,
        ]);

        // Cocher automatiquement le critère de publication pour le groupe.
        $this->majCriterePublication($projet, true);

        return back()->with('success', 'Musée approuvé et publié dans la galerie.');
    }

    /**
     * L'enseignant rejette le musée soumis avec un commentaire.
     *
     * Le statut passe à 'rejete' et les étudiants peuvent reprendre l'édition.
     *
     * @throws HttpException
     */
    public function rejeter(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        abort_unless($cours->enseignant_id === auth()->id(), 403);

        $request->validate([
            'raison_rejet' => ['required', 'string', 'max:2000'],
        ]);

        $projet = $this->chargerProjet($cours, $classe, $groupe, $typeProjet);
        $publication = $this->obtenirPublication($projet);

        abort_unless($publication->statut === MuseePublication::STATUT_SOUMIS, 422, 'Ce musée n\'est pas en attente d\'approbation.');

        $publication->update([
            'statut' => MuseePublication::STATUT_REJETE,
            'est_publie' => false,
            'raison_rejet' => $request->input('raison_rejet'),
        ]);

        // Décocher le critère de publication — les points ne sont pas accordés tant que le musée n'est pas approuvé.
        $this->majCriterePublication($projet, false);

        return back()->with('success', 'Musée rejeté. Les étudiants peuvent apporter des corrections.');
    }

    /**
     * L'enseignant bascule la visibilité publique d'un musée approuvé.
     *
     * Permet de temporairement cacher un musée approuvé de la galerie
     * sans remettre en question le statut d'approbation.
     * Pour les musées non encore soumis, permet une publication directe.
     *
     * @throws HttpException
     */
    public function toggle(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        abort_unless($cours->enseignant_id === auth()->id(), 403);

        $projet = $this->chargerProjet($cours, $classe, $groupe, $typeProjet);
        $publication = $this->obtenirPublication($projet);

        // Ne pas interférer avec une soumission en attente
        abort_if(
            $publication->statut === MuseePublication::STATUT_SOUMIS,
            422,
            'Ce musée est en attente d\'approbation — utilisez Approuver ou Rejeter.',
        );

        $wasPublie = $publication->est_publie;

        $publication->update([
            'est_publie' => ! $wasPublie,
            'publie_le' => $wasPublie ? null : now(),
            'publie_par' => $wasPublie ? null : auth()->id(),
        ]);

        return back()->with('success', $wasPublie ? 'Musée retiré de la galerie.' : 'Musée publié dans la galerie.');
    }

    // ─── File d'approbation ───────────────────────────────────────────────────

    /**
     * Affiche la file d'approbation des musées soumis pour un TypeProjet.
     *
     * Liste tous les groupes ayant soumis leur musée et attend l'approbation
     * de l'enseignant. Accessible à l'enseignant du cours uniquement.
     *
     * @throws HttpException
     */
    public function queue(Cours $cours, TypeProjet $typeProjet): Response
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_unless($typeProjet->isMusee(), 404);

        // Tous les projets du TypeProjet, avec leur publication éventuelle
        $projets = ProjetRecherche::where('type_projet_id', $typeProjet->id)
            ->with([
                'groupe.classe',
                'groupe.membres',
                'museePublication',
                'museeImages' => fn ($q) => $q->limit(1),
            ])
            ->get()
            ->map(fn (ProjetRecherche $p) => [
                'id' => $p->id,
                'groupe' => [
                    'id' => $p->groupe->id,
                    'nom' => $p->groupe->nom ?? "Groupe {$p->groupe->id}",
                    'classe_id' => $p->groupe->classe_id,
                    'classe_nom' => $p->groupe->classe->nom ?? null,
                    'membres' => $p->groupe->membres->map(fn ($m) => $m->prenom.' '.$m->nom)->all(),
                ],
                // Les groupes sans soumission ont un statut virtuel 'brouillon'
                'publication' => [
                    'statut' => $p->museePublication?->statut ?? MuseePublication::STATUT_BROUILLON,
                    'soumis_le' => $p->museePublication?->soumis_le?->toISOString(),
                    'publie_le' => $p->museePublication?->publie_le?->toISOString(),
                    'raison_rejet' => $p->museePublication?->raison_rejet,
                    'est_publie' => (bool) $p->museePublication?->est_publie,
                ],
                'meta' => MuseeMeta::where('projet_recherche_id', $p->id)
                    ->first(['entete_titre', 'slug', 'intro_image_path', 'entete_image_path']),
            ]);

        return Inertia::render('Musee/Approbation/Index', [
            'cours' => $cours->only('id', 'nom_cours', 'code'),
            'typeProjet' => $typeProjet->only('id', 'nom'),
            'projets' => $projets,
        ]);
    }
}
