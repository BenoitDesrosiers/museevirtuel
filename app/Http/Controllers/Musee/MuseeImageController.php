<?php

namespace App\Http\Controllers\Musee;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\MuseeImage;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MuseeImageController extends Controller
{
    /**
     * Upload une image pour un projet musée et retourne ses métadonnées en JSON.
     *
     * L'image est stockée dans `public/musee/images/` et un enregistrement
     * `MuseeImage` est créé pour conserver les métadonnées (alt, légende, taille).
     *
     * @throws HttpException
     */
    public function store(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): JsonResponse {
        $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $groupe);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,webp,gif', 'max:8192'],
            'alt' => ['nullable', 'string', 'max:255'],
            'legende' => ['nullable', 'string', 'max:500'],
        ]);

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        $file = $request->file('image');
        $path = $file->store('musee/images', 'public');

        // Les données de crop sont optionnelles — transmises en JSON si le modal de crop est utilisé
        $cropData = null;
        if ($request->filled('crop_data')) {
            $decoded = json_decode($request->input('crop_data'), true);
            $cropData = is_array($decoded) ? $decoded : null;
        }

        $image = MuseeImage::create([
            'projet_recherche_id' => $projet->id,
            'path' => 'storage/'.$path,
            'alt' => $request->input('alt', ''),
            'legende' => $request->input('legende', ''),
            'mime_type' => $file->getMimeType(),
            'taille' => $file->getSize(),
            'crop_data' => $cropData,
        ]);

        return response()->json([
            'id' => $image->id,
            'url' => $image->url,
            'alt' => $image->alt,
            'legende' => $image->legende,
            'crop_data' => $image->crop_data,
        ], 201);
    }

    /**
     * Met à jour les métadonnées d'une image (alt, légende, crop_data).
     *
     * Retourne l'image mise à jour en JSON pour que le frontend puisse
     * rafraîchir la bibliothèque sans recharger la page.
     *
     * @throws HttpException
     */
    public function update(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        MuseeImage $museeImage,
    ): JsonResponse {
        $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $groupe);

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        abort_if($museeImage->projet_recherche_id !== $projet->id, 404);

        $validated = $request->validate([
            'alt' => ['nullable', 'string', 'max:255'],
            'legende' => ['nullable', 'string', 'max:500'],
            'crop_data' => ['nullable', 'array'],
            'crop_data.x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'crop_data.y' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'crop_data.width' => ['nullable', 'numeric'],
            'crop_data.height' => ['nullable', 'numeric'],
        ]);

        $museeImage->update($validated);

        return response()->json([
            'id' => $museeImage->id,
            'url' => $museeImage->url,
            'alt' => $museeImage->alt,
            'legende' => $museeImage->legende,
            'crop_data' => $museeImage->crop_data,
        ]);
    }

    /**
     * Supprime une image et son fichier du disque.
     *
     * @throws HttpException
     */
    public function destroy(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        MuseeImage $museeImage,
    ): RedirectResponse {
        $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $groupe);

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        abort_if($museeImage->projet_recherche_id !== $projet->id, 404);

        // Supprimer le fichier physique — on retire le préfixe 'storage/' pour obtenir le chemin relatif
        $relativePath = preg_replace('#^storage/#', '', $museeImage->path);
        Storage::disk('public')->delete($relativePath);

        $museeImage->delete();

        return back()->with('success', 'Image supprimée.');
    }

    /**
     * Vérifie que l'utilisateur est membre du groupe ou enseignant du cours,
     * et que le TypeProjet est bien un musée appartenant au cours.
     *
     * @throws HttpException
     */
    private function verifierAcces(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        Groupe $groupeRef,
    ): void {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_unless($typeProjet->isMusee(), 404);

        $groupeRef->loadMissing('membres');
        abort_unless(
            $groupeRef->membres->contains('id', auth()->id()) || $cours->enseignant_id === auth()->id(),
            403,
        );
    }
}
