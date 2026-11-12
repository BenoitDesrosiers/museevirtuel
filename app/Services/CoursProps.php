<?php

namespace App\Services;

use App\Http\Resources\VisioConferenceResource;
use App\Models\Cours;
use App\Models\TypeProjet;
use Illuminate\Support\Collection;

/**
 * Construit les props Inertia de la page de gestion d'un cours (côté enseignant).
 */
class CoursProps
{
    /**
     * Retourne les props de la page Cours/Show : classes, ressources, types de projets et visios.
     *
     * @return array<string, mixed>
     */
    public function pourShow(Cours $cours): array
    {
        return [
            'cours' => $cours,
            'classes' => $cours->classes()
                ->withCount(['etudiants', 'groupes'])
                ->orderBy('numero')
                ->get(),
            'documents' => $cours->documents()->get(),
            'echeancierEtapes' => $this->echeancierEtapes($cours),
            'objectifs' => $this->objectifs($cours),
            'references' => $this->references($cours),
            'typesProjets' => $this->typesProjets($cours),
            'visioConferences' => $cours->visioConferences()
                ->with('animateur:id,prenom,nom')
                ->get()
                ->pipe(fn ($visios) => VisioConferenceResource::collection($visios)->resolve()),
        ];
    }

    /**
     * Retourne les étapes de l'échéancier du cours.
     */
    private function echeancierEtapes(Cours $cours): Collection
    {
        return $cours->echeancierEtapes()->get()->map(fn ($etape) => [
            'id' => $etape->id,
            'semaine' => $etape->semaine,
            'periode' => $etape->periode,
            'etape' => $etape->etape,
            'is_done' => $etape->is_done,
            'ordre' => $etape->ordre,
        ]);
    }

    /**
     * Retourne les objectifs du cours.
     */
    private function objectifs(Cours $cours): Collection
    {
        return $cours->objectifs()->get()->map(fn ($o) => [
            'id' => $o->id,
            'contenu' => $o->contenu,
            'ordre' => $o->ordre,
        ]);
    }

    /**
     * Retourne les références (liens) du cours.
     */
    private function references(Cours $cours): Collection
    {
        return $cours->references()->get()->map(fn ($r) => [
            'id' => $r->id,
            'nom' => $r->nom,
            'url' => $r->url,
            'ordre' => $r->ordre,
        ]);
    }

    /**
     * Retourne les types de projets du cours, triés par nom, avec leurs sections.
     */
    private function typesProjets(Cours $cours): Collection
    {
        return TypeProjet::where('cours_id', $cours->id)
            ->with(['sections'])
            ->orderBy('nom')
            ->get()
            ->map(fn (TypeProjet $tp) => [
                'id' => $tp->id,
                'nom' => $tp->nom,
                'description' => $tp->description,
                'accessible' => $tp->accessible,
                'sections' => $tp->sections->map(fn ($s) => [
                    'id' => $s->id,
                    'label' => $s->label,
                    'description' => $s->description,
                    'ordre' => $s->ordre,
                ])->values(),
            ])->values();
    }
}
