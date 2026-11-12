<?php

namespace App\Http\Controllers\Visio;

use App\Actions\StoreUploadedFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Visio\StoreVisioEnregistrementRequest;
use App\Models\Cours;
use App\Models\VisioConference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Enregistrement vidéo d'une visioconférence (dépôt et diffusion).
 */
class VisioEnregistrementController extends Controller
{
    /**
     * Enregistre le fichier vidéo d'une rencontre terminée.
     *
     * Remplace l'ancien fichier s'il en existe déjà un.
     * Accessible uniquement à l'enseignant du cours.
     * Le fichier est validé par StoreVisioEnregistrementRequest.
     *
     * @throws HttpException si la visio n'appartient pas au cours
     */
    public function store(StoreVisioEnregistrementRequest $request, Cours $cours, VisioConference $visio, StoreUploadedFile $fichiers): RedirectResponse
    {
        // Supprimer l'ancien fichier avant de le remplacer
        if ($visio->recording_path) {
            Storage::delete($visio->recording_path);
        }

        $fichier = $fichiers->executePrive($request->file('recording'), "visio-recordings/{$visio->id}", 'recording');

        $visio->update(['recording_path' => $fichier['file_path']]);

        return back()->with('success', __('visio.recording_saved'));
    }

    /**
     * Diffuse le fichier d'enregistrement en streaming sécurisé.
     *
     * Supporte les range requests pour permettre la navigation dans la vidéo.
     * Accessible à l'enseignant du cours et aux étudiants concernés par la visio :
     * les membres du groupe ciblé, ou de n'importe quel groupe si la visio est commune.
     *
     * @throws HttpException si fichier introuvable
     * @throws AuthorizationException
     */
    public function show(Cours $cours, VisioConference $visio): BinaryFileResponse
    {
        abort_if($visio->cours_id !== $cours->id, 404);
        $this->authorize('participer', $visio);
        abort_if(! $visio->recording_path, 404);

        $filePath = Storage::path($visio->recording_path);
        abort_if(! file_exists($filePath), 404);

        return response()->file($filePath);
    }
}
