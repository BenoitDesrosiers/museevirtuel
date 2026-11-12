<?php

namespace App\Http\Controllers\Cours;

use App\Actions\StoreUploadedFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cours\StoreCoursDocumentRequest;
use App\Models\Cours;
use App\Models\CoursDocument;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CoursDocumentController extends Controller
{
    /**
     * Uploade un document et l'associe au cours.
     *
     * La validation (dont le MIME réel, pas uniquement l'extension client) est assurée par StoreCoursDocumentRequest.
     * L'autorisation délègue à CoursPolicy::update().
     *
     * @throws AuthorizationException
     */
    public function store(StoreCoursDocumentRequest $request, Cours $cours, StoreUploadedFile $fichiers): RedirectResponse
    {
        $meta = $fichiers->execute(
            $request->file('document'),
            "images/cours/{$cours->id}"
        );

        $cours->documents()->create([
            'enseignant_id' => auth()->id(),
            'type' => strtolower($request->file('document')->getClientOriginalExtension()),
            ...$meta,
        ]);

        return back()->with('success', __('document.added'));
    }

    /**
     * Télécharge un document avec son nom original, sans exposer le nom UUID sur disque.
     *
     * @throws AuthorizationException
     */
    public function download(Cours $cours, CoursDocument $document): BinaryFileResponse
    {
        abort_if($document->cours_id !== $cours->id, 404);
        $this->authorize('view', $cours);

        $path = public_path($document->file_path);
        abort_unless(is_file($path), 404);

        return response()->download($path, $document->nom_original);
    }

    /**
     * Supprime un document du cours et son fichier physique.
     *
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, CoursDocument $document): RedirectResponse
    {
        abort_if($document->cours_id !== $cours->id, 404);
        $this->authorize('update', $cours);

        $document->deleteWithFile();

        return back()->with('success', __('document.deleted'));
    }
}
