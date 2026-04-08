<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\ClasseController;
use App\Http\Controllers\ClasseDocumentController;
use App\Http\Controllers\ClasseEtudiantController;
use App\Http\Controllers\EcheancierController;
use App\Http\Controllers\EnseignantController;
use App\Http\Controllers\EtudiantController;
use App\Http\Controllers\GrilleCorrectionController;
use App\Http\Controllers\GroupeController;
use App\Http\Controllers\GroupeEchangeController;
use App\Http\Controllers\GroupeMediaController;
use App\Http\Controllers\InscriptionTemoinController;
use App\Http\Controllers\PersonneAgeeController;
use App\Http\Controllers\ProjetRechercheController;
use App\Http\Controllers\ThematiqueController;
use App\Http\Controllers\TypeProjetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

// ─── Inscription témoin (public) ───────────────────────────────────────────────
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/inscription/temoin', [InscriptionTemoinController::class, 'show'])
        ->name('inscription.temoin');

    Route::post('/inscription/temoin', [InscriptionTemoinController::class, 'store'])
        ->name('inscription.temoin.store');
});

// Redirection post-login selon le rôle
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();

        return match ($user->role) {
            'admin' => redirect()->route('administration.index'),
            'enseignant' => redirect()->route('enseignant.index'),
            'personne_agee' => redirect()->route('personne-agee.index'),
            default => redirect()->route('classes.index'),
        };
    })->name('dashboard');

    // ─── Personne âgée ────────────────────────────────────────────────────────
    Route::get('/personne-agee', [PersonneAgeeController::class, 'index'])
        ->middleware('role:personne_agee')
        ->name('personne-agee.index');
});

// ─── Admin ────────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/administration', [AdministrationController::class, 'index'])
        ->name('administration.index');

    Route::post('/administration/enseignants', [AdministrationController::class, 'storeEnseignant'])
        ->name('administration.enseignants.store');

    Route::put('/administration/enseignants/{enseignant}', [AdministrationController::class, 'updateEnseignant'])
        ->name('administration.enseignants.update');

    Route::delete('/administration/enseignants/{enseignant}', [AdministrationController::class, 'destroyEnseignant'])
        ->name('administration.enseignants.destroy');

    // Approbation des témoins (personnes âgées) en attente
    Route::put('/administration/temoins/{user}/approuver', [AdministrationController::class, 'approuverTemoin'])
        ->name('administration.temoins.approuver');
});

// ─── Enseignant (+ Admin) ──────────────────────────────────────────────────────
Route::middleware(['auth', 'role:enseignant,admin'])->group(function () {
    Route::get('/enseignant', [EnseignantController::class, 'index'])
        ->name('enseignant.index');

    // Gestion des classes
    Route::post('/classes', [ClasseController::class, 'store'])
        ->name('classes.store');

    Route::put('/classes/{classe}', [ClasseController::class, 'update'])
        ->name('classes.update');

    Route::delete('/classes/{classe}', [ClasseController::class, 'destroy'])
        ->name('classes.destroy');

    Route::get('/classes/{classe}', [ClasseController::class, 'show'])
        ->name('classes.show');

    // Gestion des étudiants dans une classe
    Route::post('/classes/{classe}/etudiants', [ClasseEtudiantController::class, 'store'])
        ->name('classes.etudiants.store');

    Route::put('/classes/{classe}/etudiants/{etudiant}', [ClasseEtudiantController::class, 'update'])
        ->name('classes.etudiants.update');

    Route::delete('/classes/{classe}/etudiants/{etudiant}', [ClasseEtudiantController::class, 'destroy'])
        ->name('classes.etudiants.destroy');

    Route::post('/classes/{classe}/import', [ClasseEtudiantController::class, 'import'])
        ->name('classes.etudiants.import');

    // Documents de classe
    Route::post('/classes/{classe}/documents', [ClasseDocumentController::class, 'store'])
        ->name('classes.documents.store');

    Route::delete('/classes/{classe}/documents/{document}', [ClasseDocumentController::class, 'destroy'])
        ->name('classes.documents.destroy');

    // Gestion des thématiques
    Route::post('/thematiques', [ThematiqueController::class, 'store'])
        ->name('thematiques.store');

    Route::put('/thematiques/{thematique}', [ThematiqueController::class, 'update'])
        ->name('thematiques.update');

    Route::delete('/thematiques/{thematique}', [ThematiqueController::class, 'destroy'])
        ->name('thematiques.destroy');

    // Suppression de groupe (enseignant de la classe ou admin — cascade projet)
    Route::delete('/classes/{classe}/groupes/{groupe}', [GroupeController::class, 'destroy'])
        ->name('groupes.destroy');

    // Assignation d'un témoin à un groupe (enseignant/admin)
    Route::put('/classes/{classe}/groupes/{groupe}/temoin', [GroupeController::class, 'assignerTemoin'])
        ->name('groupes.temoin.update');

    // Types de projet (enseignant/admin)
    Route::get('/types-projets', [TypeProjetController::class, 'index'])
        ->name('types-projets.index');

    Route::post('/types-projets', [TypeProjetController::class, 'store'])
        ->name('types-projets.store');

    Route::get('/types-projets/{typeProjet}/edit', [TypeProjetController::class, 'edit'])
        ->name('types-projets.edit');

    Route::put('/types-projets/{typeProjet}', [TypeProjetController::class, 'update'])
        ->name('types-projets.update');

    Route::patch('/types-projets/{typeProjet}/toggle-accessible', [TypeProjetController::class, 'toggleAccessible'])
        ->name('types-projets.toggle-accessible');

    Route::delete('/types-projets/{typeProjet}', [TypeProjetController::class, 'destroy'])
        ->name('types-projets.destroy');

    // Sections du type de projet (définies par le professeur)
    Route::post('/types-projets/{typeProjet}/sections', [TypeProjetController::class, 'storeSection'])
        ->name('types-projets.sections.store');

    Route::put('/types-projets/{typeProjet}/sections/reorder', [TypeProjetController::class, 'reorderSections'])
        ->name('types-projets.sections.reorder');

    Route::put('/types-projets/{typeProjet}/sections/{section}', [TypeProjetController::class, 'updateSection'])
        ->name('types-projets.sections.update');

    Route::delete('/types-projets/{typeProjet}/sections/{section}', [TypeProjetController::class, 'destroySection'])
        ->name('types-projets.sections.destroy');

    // Grille de correction rattachée à un type de projet
    Route::get('/types-projets/{typeProjet}/grille', [GrilleCorrectionController::class, 'edit'])
        ->name('types-projets.grille.edit');

    Route::post('/types-projets/{typeProjet}/grille', [GrilleCorrectionController::class, 'store'])
        ->name('types-projets.grille.store');

    Route::put('/types-projets/{typeProjet}/grille', [GrilleCorrectionController::class, 'update'])
        ->name('types-projets.grille.update');

    Route::delete('/types-projets/{typeProjet}/grille', [GrilleCorrectionController::class, 'destroy'])
        ->name('types-projets.grille.destroy');

    // Échéancier de classe
    Route::post('/classes/{classe}/echeancier', [EcheancierController::class, 'store'])
        ->name('echeancier.store');

    Route::put('/classes/{classe}/echeancier/{etape}', [EcheancierController::class, 'update'])
        ->name('echeancier.update');

    Route::delete('/classes/{classe}/echeancier/{etape}', [EcheancierController::class, 'destroy'])
        ->name('echeancier.destroy');

    Route::delete('/classes/{classe}/echeancier', [EcheancierController::class, 'destroyAll'])
        ->name('echeancier.destroyAll');

    Route::patch('/classes/{classe}/echeancier/{etape}/toggle', [EcheancierController::class, 'toggleDone'])
        ->name('echeancier.toggle');
});

// ─── Étudiant ─────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:etudiant'])->group(function () {
    Route::get('/classes', [ClasseController::class, 'index'])
        ->name('classes.index');

    Route::get('/etudiant', [EtudiantController::class, 'index'])
        ->name('etudiant.index');

    // Groupes
    Route::get('/classes/{classe}/groupes', [GroupeController::class, 'index'])
        ->name('groupes.index');

    Route::post('/classes/{classe}/groupes', [GroupeController::class, 'store'])
        ->name('groupes.store');

    Route::post('/groupes/{groupe}/notes', [GroupeController::class, 'storeNote'])
        ->name('groupes.notes.store');

    Route::delete('/groupes/{groupe}/notes/{note}', [GroupeController::class, 'destroyNote'])
        ->name('groupes.notes.destroy');

    // Progression personnelle de l'étudiant sur l'échéancier
    Route::patch('/classes/{classe}/echeancier/{etape}/toggle-etudiant', [EcheancierController::class, 'toggleEtudiant'])
        ->name('echeancier.toggleEtudiant');
});

// ─── Échanges groupe ↔ témoin (tous les rôles auth concernés) ─────────────────
Route::middleware(['auth', 'role:etudiant,enseignant,admin,personne_agee'])->group(function () {
    Route::get('/classes/{classe}/groupes/{groupe}/echanges', [GroupeEchangeController::class, 'index'])
        ->name('echanges.index');

    Route::post('/classes/{classe}/groupes/{groupe}/echanges', [GroupeEchangeController::class, 'store'])
        ->name('echanges.store');
});

// ─── Corrections inline des notes (enseignant + admin) ────────────────────────
Route::middleware(['auth', 'role:enseignant,admin'])->group(function () {
    Route::put('/groupes/{groupe}/notes/{note}/corrections', [GroupeController::class, 'upsertNoteCorrection'])
        ->name('groupes.notes.corrections.upsert');

    Route::delete('/groupes/{groupe}/notes/{note}/corrections/{correction}', [GroupeController::class, 'destroyNoteCorrection'])
        ->name('groupes.notes.corrections.destroy');
});

// ─── Actions créateur du groupe ───────────────────────────────────────────────
Route::middleware(['auth', 'role:etudiant'])->group(function () {
    Route::put('/classes/{classe}/groupes/{groupe}/thematiques', [GroupeController::class, 'updateThematiques'])
        ->name('groupes.thematiques.update');

    Route::put('/classes/{classe}/groupes/{groupe}/membres', [GroupeController::class, 'updateMembres'])
        ->name('groupes.membres.update');
});

// ─── Groupes (étudiant + enseignant + admin) ──────────────────────────────────
Route::middleware(['auth', 'role:etudiant,enseignant,admin'])->group(function () {
    Route::get('/classes/{classe}/groupes/{groupe}', [GroupeController::class, 'show'])
        ->name('groupes.show');

    // Médias du groupe
    Route::post('/classes/{classe}/groupes/{groupe}/medias', [GroupeMediaController::class, 'store'])
        ->name('groupes.medias.store');

    Route::delete('/classes/{classe}/groupes/{groupe}/medias/{media}', [GroupeMediaController::class, 'destroy'])
        ->name('groupes.medias.destroy');

    // ─── Projets de recherche ─────────────────────────────────────────────────
    // Un projet par (groupe × TypeProjet) — index liste tous les TypeProjets accessibles
    Route::get('/classes/{classe}/groupes/{groupe}/projets', [ProjetRechercheController::class, 'index'])
        ->name('projets.index');

    // Toutes les routes suivantes sont scoped par {typeProjet}
    Route::get('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/edit', [ProjetRechercheController::class, 'show'])
        ->name('projets.show');

    Route::get('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/apercu', [ProjetRechercheController::class, 'apercu'])
        ->name('projets.apercu');

    Route::put('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}', [ProjetRechercheController::class, 'update'])
        ->name('projets.update');

    // Conclusion individuelle de l'étudiant authentifié
    Route::put('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/conclusion', [ProjetRechercheController::class, 'updateConclusion'])
        ->name('projets.conclusion.update');

    // Commentaires de l'enseignant par champ (enseignant uniquement — vérifié dans le controller)
    Route::put('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/commentaires', [ProjetRechercheController::class, 'upsertCommentaire'])
        ->name('projets.commentaires.upsert');

    Route::delete('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/commentaires/{commentaire}', [ProjetRechercheController::class, 'destroyCommentaire'])
        ->name('projets.commentaires.destroy');

    // Notes de la grille de correction (enseignant uniquement — vérifié dans le controller)
    Route::put('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/notes', [ProjetRechercheController::class, 'upsertNote'])
        ->name('projets.notes.upsert');

    // Grille de correction personnalisée (enseignant uniquement — vérifié dans le controller)
    Route::put('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/grille/notes', [ProjetRechercheController::class, 'upsertNoteGrille'])
        ->name('projets.grille.notes.upsert');

    Route::put('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/grille/malus', [ProjetRechercheController::class, 'toggleMalusGrille'])
        ->name('projets.grille.malus.toggle');

    // Sections dynamiques — contenu rédigé par les étudiants
    Route::put('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}', [ProjetRechercheController::class, 'updateSectionContenu'])
        ->name('projets.sections.update');

    // Paragraphes de section de type 'paragraphes' — CRUD + réordonnancement
    Route::post('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/paragraphes', [ProjetRechercheController::class, 'storeSectionParagraphe'])
        ->name('projets.sections.paragraphes.store');

    Route::patch('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/paragraphes/reorder', [ProjetRechercheController::class, 'reorderSectionParagraphes'])
        ->name('projets.sections.paragraphes.reorder');

    Route::patch('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/paragraphes/{paragraphe}', [ProjetRechercheController::class, 'updateSectionParagraphe'])
        ->name('projets.sections.paragraphes.update');

    Route::delete('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/paragraphes/{paragraphe}', [ProjetRechercheController::class, 'destroySectionParagraphe'])
        ->name('projets.sections.paragraphes.destroy');

    // Paragraphes de développement — CRUD + réordonnancement
    Route::post('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/developpements', [ProjetRechercheController::class, 'storeDeveloppement'])
        ->name('projets.developpements.store');

    Route::put('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/developpements/{developpement}', [ProjetRechercheController::class, 'updateDeveloppement'])
        ->name('projets.developpements.update');

    Route::delete('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/developpements/{developpement}', [ProjetRechercheController::class, 'destroyDeveloppement'])
        ->name('projets.developpements.destroy');

    Route::patch('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/developpements/reorder', [ProjetRechercheController::class, 'reorderDeveloppements'])
        ->name('projets.developpements.reorder');

    // Annotations inline de l'enseignant par champ (enseignant uniquement — vérifié dans le controller)
    Route::put('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/annotations', [ProjetRechercheController::class, 'upsertAnnotation'])
        ->name('projets.annotations.upsert');

    Route::delete('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/annotations/{annotation}', [ProjetRechercheController::class, 'destroyAnnotation'])
        ->name('projets.annotations.destroy');

    // Toggles prof — visibilité des corrections + verrouillage (enseignant uniquement — vérifié dans le controller)
    Route::patch('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/correction-visible', [ProjetRechercheController::class, 'toggleCorrectionVisible'])
        ->name('projets.correction-visible.toggle');

    Route::patch('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/verrouille', [ProjetRechercheController::class, 'toggleVerrouille'])
        ->name('projets.verrouille.toggle');

    // Remise de travail
    Route::post('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/remettre', [ProjetRechercheController::class, 'remettreTravail'])
        ->name('projets.remettre');

    Route::patch('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/parametres-remise', [ProjetRechercheController::class, 'updateParametresRemise'])
        ->name('projets.parametres-remise.update');

    Route::delete('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/annuler-remise', [ProjetRechercheController::class, 'annulerRemise'])
        ->name('projets.annulerRemise');

    Route::post('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/voter-remise', [ProjetRechercheController::class, 'voterRemise'])
        ->name('projets.voterRemise');

    Route::get('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/pdf', [ProjetRechercheController::class, 'exportPdf'])
        ->name('projets.export.pdf');

    Route::get('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/word', [ProjetRechercheController::class, 'exportWord'])
        ->name('projets.export.word');

    Route::get('/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/xml-notes', [ProjetRechercheController::class, 'exportXmlNotes'])
        ->name('projets.export.xml');
});

require __DIR__.'/settings.php';
