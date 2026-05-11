<?php

namespace App\Http\Middleware;

use App\Models\Cours;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureCoursNonVerrouille
{
    /**
     * Bloque l'accès aux ressources d'un cours verrouillé pour les non-enseignants.
     *
     * Les admins et l'enseignant propriétaire du cours passent toujours.
     * Tout autre rôle reçoit un 403 si le cours est verrouillé.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cours = $request->route('cours');

        // Pas de paramètre {cours} dans la route — on laisse passer
        if (! $cours instanceof Cours) {
            return $next($request);
        }

        $user = $request->user();

        // Admin et enseignant propriétaire ont accès même sur cours verrouillé
        if ($user->isAdmin() || $cours->enseignant_id === $user->id) {
            return $next($request);
        }

        if ($cours->is_verrouille) {
            return Inertia::render('Cours/Verrouille', [
                'cours' => $cours->only('id', 'nom_cours', 'code'),
            ])->toResponse($request)->setStatusCode(403);
        }

        return $next($request);
    }
}
