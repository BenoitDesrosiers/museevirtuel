<?php

namespace App\Http\Controllers;

use App\Actions\StoreUploadedFile;
use App\Models\Cours;
use App\Models\CoursDocument;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $request->validate([
            'document' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx'],
        ]);

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
