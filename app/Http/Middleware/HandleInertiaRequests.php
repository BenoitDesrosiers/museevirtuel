<?php

namespace App\Http\Middleware;

use App\Models\Groupe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'locale' => App::getLocale(),
            'availableLocales' => config('app.available_locales', ['fr', 'en']),
            'navData' => $this->buildNavData($request->user()),
        ];
    }

    /**
     * Construit les données de navigation hiérarchiques selon le rôle de l'utilisateur.
     *
     * @return array<string, mixed>|null
     */
    private function buildNavData(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return match ($user->role) {
            'enseignant', 'admin' => $this->navDataEnseignant($user),
            'etudiant' => $this->navDataEtudiant($user),
            default => null,
        };
    }

    /**
     * Construit la structure de navigation pour un enseignant (ou admin).
     * Charge ses cours avec leurs classes et les groupes de chaque classe.
     *
     * @return array<string, mixed>
     */
    private function navDataEnseignant(User $user): array
    {
        $cours = $user->cours()
            ->orderBy('nom_cours')
            ->with(['classes' => fn ($q) => $q
                ->orderBy('nom')
                ->with(['groupes' => fn ($q) => $q
                    ->orderBy('id')
                    ->select('id', 'classe_id', 'personne_agee_id')
                    ->with(['projets' => fn ($q) => $q
                        ->with('typeProjet:id,nom')
                        ->select('id', 'groupe_id', 'type_projet_id', 'titre_projet'),
                    ]),
                ])
                ->select('id', 'cours_id', 'nom', 'numero'),
            ])
            ->get(['id', 'nom_cours', 'code', 'groupe']);

        return [
            'cours' => $cours->map(fn ($c) => [
                'id' => $c->id,
                'nom' => $c->nom_cours,
                'code' => $c->code,
                'groupe' => $c->groupe,
                'classes' => $c->classes->map(fn ($cl) => [
                    'id' => $cl->id,
                    'nom' => $cl->nom,
                    'numero' => $cl->numero,
                    'groupes' => $cl->groupes->values()->map(fn ($g, $i) => [
                        'id' => $g->id,
                        'numero' => $i + 1,
                        'hasTemoin' => ! is_null($g->personne_agee_id),
                        'projets' => $g->projets->map(fn ($p) => [
                            'type_projet_id' => $p->type_projet_id,
                            'titre' => $p->typeProjet?->nom ?? '',
                        ])->values()->all(),
                    ])->values(),
                ])->values(),
            ])->values(),
        ];
    }

    /**
     * Construit la structure de navigation pour un étudiant.
     * Charge les cours auxquels il est inscrit via ses classes,
     * et uniquement les groupes dont il est membre.
     *
     * @return array<string, mixed>
     */
    private function navDataEtudiant(User $user): array
    {
        // IDs de tous les groupes dont l'étudiant est membre
        $studentGroupeIds = $user->groupesMembre()->pluck('groupes.id');

        $classes = $user->classesInscrites()
            ->with(['cours:id,nom_cours,code,groupe'])
            ->orderBy('classes.cours_id')
            ->get(['classes.id', 'classes.cours_id', 'classes.nom', 'classes.numero']);

        $coursMap = [];

        foreach ($classes as $classe) {
            // Charge tous les groupes de la classe (pour déterminer le numéro d'ordre)
            // puis filtre sur ceux de l'étudiant
            $groupes = Groupe::where('classe_id', $classe->id)
                ->orderBy('id')
                ->with(['projets' => fn ($q) => $q
                    ->with('typeProjet:id,nom')
                    ->select('id', 'groupe_id', 'type_projet_id', 'titre_projet'),
                ])
                ->get(['id', 'personne_agee_id'])
                ->values()
                ->map(fn ($g, $i) => [
                    'id' => $g->id,
                    'numero' => $i + 1,
                    'hasTemoin' => ! is_null($g->personne_agee_id),
                    'projets' => $g->projets->map(fn ($p) => [
                        'type_projet_id' => $p->type_projet_id,
                        'titre' => $p->typeProjet?->nom ?? '',
                    ])->values()->all(),
                ])
                ->filter(fn ($g) => $studentGroupeIds->contains($g['id']))
                ->values()
                ->all();

            $coursId = $classe->cours_id;

            if (! isset($coursMap[$coursId])) {
                $coursMap[$coursId] = [
                    'id' => $classe->cours->id,
                    'nom' => $classe->cours->nom_cours,
                    'code' => $classe->cours->code,
                    'groupe' => $classe->cours->groupe,
                    'classes' => [],
                ];
            }

            $coursMap[$coursId]['classes'][] = [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'numero' => $classe->numero,
                'groupes' => $groupes,
            ];
        }

        return ['cours' => array_values($coursMap)];
    }
}
