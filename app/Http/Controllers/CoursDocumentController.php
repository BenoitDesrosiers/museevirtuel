<?php

namespace App\Http\Controllers;

use App\Actions\StoreUploadedFile;
use App\Models\Cours;
use App\Models\CoursDocument;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CoursDocumentController extends Controller
{
    /**
     * Uploade un document et l'associe au cours.
     *
     * La validation MIME réelle (pas uniquement l'extension client) est assurée par Laravel.
     * L'autorisation délègue à CoursPolicy::update().
     *
     * @throws AuthorizationException
     */
    public function store(Request $request, Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);

        $request->validate(
            [
                'document' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx'],
            ],
            $this->messagesValidationDocument(),
        );

        $meta = (new StoreUploadedFile)->execute(
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
     * Retourne les messages d'erreur de validation en français pour un document.
     *
     * @return array<string, string>
     */
    private function messagesValidationDocument(): array
    {
        return [
            'document.required' => __('document.required'),
            'document.file' => __('document.format_error'),
            'document.mimes' => __('document.format_error'),
            'document.max' => __('document.max'),
        ];
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
        $this->authorize('update', $cours);

        $document->deleteWithFile();

        return back()->with('success', __('document.deleted'));
    }
}
