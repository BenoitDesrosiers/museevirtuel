<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\ClasseController;
use App\Http\Controllers\ClasseEtudiantController;
use App\Http\Controllers\ConsentementVideoController;
use App\Http\Controllers\CoursController;
use App\Http\Controllers\CoursDocumentController;
use App\Http\Controllers\CoursLienEntrevueController;
use App\Http\Controllers\CoursObjectifController;
use App\Http\Controllers\EcheancierController;
use App\Http\Controllers\EnseignantController;
use App\Http\Controllers\EntrevueConceptController;
use App\Http\Controllers\EtablissementController;
use App\Http\Controllers\EtudiantController;
use App\Http\Controllers\GrilleCorrectionController;
use App\Http\Controllers\GroupeController;
use App\Http\Controllers\GroupeEchangeController;
use App\Http\Controllers\GroupeMediaController;
use App\Http\Controllers\GroupeTacheController;
use App\Http\Controllers\InscriptionTemoinController;
use App\Http\Controllers\PersonneAgeeController;
use App\Http\Controllers\ProjetRechercheController;
use App\Http\Controllers\ProjetSchemaVisuelController;
use App\Http\Controllers\ProjetSectionMediaController;
use App\Http\Controllers\QuestionBanqueController;
use App\Http\Controllers\ThematiqueController;
use App\Http\Controllers\TypeProjetController;
use App\Http\Controllers\TypeProjetTacheController;
use App\Http\Controllers\VisioConferenceController;
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

    Route::get('/inscription/temoin/engagements', [InscriptionTemoinController::class, 'showEngagements'])
        ->name('inscription.temoin.engagements');

    Route::post('/inscription/temoin/engagements', [InscriptionTemoinController::class, 'storeEngagements'])
        ->name('inscription.temoin.engagements.store');
});

// Redirection post-login selon le rôle
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();

        return match ($user->role) {
            'admin' => redirect()->route('administration.index'),
            'enseignant' => redirect()->route('enseignant.index'),
            'personne_agee' => redirect()->route('temoin.index'),
            default => redirect()->route('cours.index'),
        };
    })->name('dashboard');

    // ─── Personne âgée ────────────────────────────────────────────────────────
    Route::get('/temoin', [PersonneAgeeController::class, 'index'])
        ->middleware('role:personne_agee')
        ->name('temoin.index');
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

    // Approbation / déclin des témoins (personnes âgées) en attente
    Route::put('/administration/temoins/{user}/approuver', [AdministrationController::class, 'approuverTemoin'])
        ->name('administration.temoins.approuver');

    Route::put('/administration/temoins/{user}/decliner', [AdministrationController::class, 'declinerTemoin'])
        ->name('administration.temoins.decliner');

    // Gestion des établissements (cégeps)
    Route::get('/administration/etablissements/{etablissement}', [EtablissementController::class, 'show'])
        ->name('administration.etablissements.show');

    Route::post('/administration/etablissements', [EtablissementController::class, 'store'])
        ->name('administration.etablissements.store');

    Route::put('/administration/etablissements/{etablissement}', [EtablissementController::class, 'update'])
        ->name('administration.etablissements.update');

    Route::delete('/administration/etablissements/{etablissement}', [EtablissementController::class, 'destroy'])
        ->name('administration.etablissements.destroy');
});

// ─── Enseignant (+ Admin) ──────────────────────────────────────────────────────
Route::middleware(['auth', 'role:enseignant,admin'])->group(function () {
    Route::get('/enseignant', [EnseignantController::class, 'index'])
        ->name('enseignant.index');

    // Gestion des cours
    Route::post('/cours', [CoursController::class, 'store'])
        ->name('cours.store');

    Route::get('/cours/{cours}', [CoursController::class, 'show'])
        ->name('cours.show');

    Route::put('/cours/{cours}', [CoursController::class, 'update'])
        ->name('cours.update');

    Route::delete('/cours/{cours}', [CoursController::class, 'destroy'])
        ->name('cours.destroy');

    // Documents du cours
    Route::post('/cours/{cours}/documents', [CoursDocumentController::class, 'store'])
        ->name('cours.documents.store');

    Route::delete('/cours/{cours}/documents/{document}', [CoursDocumentController::class, 'destroy'])
        ->name('cours.documents.destroy');

    // Gestion des thématiques
    Route::post('/thematiques', [ThematiqueController::class, 'store'])
        ->name('thematiques.store');

    Route::put('/thematiques/{thematique}', [ThematiqueController::class, 'update'])
        ->name('thematiques.update');

    Route::delete('/thematiques/{thematique}', [ThematiqueController::class, 'destroy'])
        ->name('thematiques.destroy');

    // Gestion des classes (sections de cours) — enseignant/admin
    Route::get('/cours/{cours}/classes/{classe}/types-projets/{typeProjet}/apercu-notes', [ClasseController::class, 'apercuNotesClasse'])
        ->name('classes.apercu.notes');

    Route::post('/cours/{cours}/classes', [ClasseController::class, 'store'])
        ->name('classes.store');

    Route::put('/cours/{cours}/classes/{classe}', [ClasseController::class, 'update'])
        ->name('classes.update');

    Route::delete('/cours/{cours}/classes/{classe}', [ClasseController::class, 'destroy'])
        ->name('classes.destroy');

    // Gestion des étudiants dans une section (classe)
    Route::post('/cours/{cours}/classes/{classe}/etudiants', [ClasseEtudiantController::class, 'store'])
        ->name('classes.etudiants.store');

    Route::put('/cours/{cours}/classes/{classe}/etudiants/{etudiant}', [ClasseEtudiantController::class, 'update'])
        ->name('classes.etudiants.update');

    Route::delete('/cours/{cours}/classes/{classe}/etudiants/{etudiant}', [ClasseEtudiantController::class, 'destroy'])
        ->name('classes.etudiants.destroy');

    Route::post('/cours/{cours}/classes/{classe}/etudiants/import', [ClasseEtudiantController::class, 'import'])
        ->name('classes.etudiants.import');

    // Assignation d'un témoin à un groupe (enseignant/admin)
    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/temoin', [GroupeController::class, 'assignerTemoin'])
        ->name('groupes.temoin.update');

    // Suppression d'un groupe (enseignant ou admin)
    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}', [GroupeController::class, 'destroy'])
        ->name('groupes.destroy');

    // Fiche détail + approbation / déclin des témoins liés aux thématiques de l'enseignant
    Route::get('/enseignant/temoins/{user}', [EnseignantController::class, 'showTemoin'])
        ->name('enseignant.temoins.show');

    Route::put('/enseignant/temoins/{user}/approuver', [EnseignantController::class, 'approuverTemoin'])
        ->name('enseignant.temoins.approuver');

    Route::put('/enseignant/temoins/{user}/decliner', [EnseignantController::class, 'declinerTemoin'])
        ->name('enseignant.temoins.decliner');

    Route::put('/enseignant/temoins/{user}/desapprouver', [EnseignantController::class, 'desapprouverTemoin'])
        ->name('enseignant.temoins.desapprouver');

    // Objectifs pédagogiques du cours
    Route::post('/cours/{cours}/objectifs', [CoursObjectifController::class, 'store'])
        ->name('cours.objectifs.store');

    Route::patch('/cours/{cours}/objectifs/reorder', [CoursObjectifController::class, 'reorder'])
        ->name('cours.objectifs.reorder');

    Route::put('/cours/{cours}/objectifs/{objectif}', [CoursObjectifController::class, 'update'])
        ->name('cours.objectifs.update');

    Route::delete('/cours/{cours}/objectifs/{objectif}', [CoursObjectifController::class, 'destroy'])
        ->name('cours.objectifs.destroy');

    // Types de projet — imbriqués sous le cours
    Route::get('/cours/{cours}/types-projets', [TypeProjetController::class, 'index'])
        ->name('types-projets.index');

    Route::get('/cours/{cours}/types-projets/create', [TypeProjetController::class, 'create'])
        ->name('types-projets.create');

    Route::post('/cours/{cours}/types-projets', [TypeProjetController::class, 'store'])
        ->name('types-projets.store');

    Route::get('/cours/{cours}/types-projets/{typeProjet}/edit', [TypeProjetController::class, 'edit'])
        ->name('types-projets.edit');

    Route::put('/cours/{cours}/types-projets/{typeProjet}', [TypeProjetController::class, 'update'])
        ->name('types-projets.update');

    Route::patch('/cours/{cours}/types-projets/{typeProjet}/toggle-accessible', [TypeProjetController::class, 'toggleAccessible'])
        ->name('types-projets.toggle-accessible');

    Route::delete('/cours/{cours}/types-projets/{typeProjet}', [TypeProjetController::class, 'destroy'])
        ->name('types-projets.destroy');

    // Sections du type de projet (définies par le professeur)
    Route::post('/cours/{cours}/types-projets/{typeProjet}/sections', [TypeProjetController::class, 'storeSection'])
        ->name('types-projets.sections.store');

    Route::put('/cours/{cours}/types-projets/{typeProjet}/sections/reorder', [TypeProjetController::class, 'reorderSections'])
        ->name('types-projets.sections.reorder');

    Route::put('/cours/{cours}/types-projets/{typeProjet}/sections/{section}', [TypeProjetController::class, 'updateSection'])
        ->name('types-projets.sections.update');

    Route::delete('/cours/{cours}/types-projets/{typeProjet}/sections/{section}', [TypeProjetController::class, 'destroySection'])
        ->name('types-projets.sections.destroy');

    // Grille de correction rattachée à un type de projet
    Route::get('/cours/{cours}/types-projets/{typeProjet}/grille', [GrilleCorrectionController::class, 'edit'])
        ->name('types-projets.grille.edit');

    Route::post('/cours/{cours}/types-projets/{typeProjet}/grille', [GrilleCorrectionController::class, 'store'])
        ->name('types-projets.grille.store');

    Route::put('/cours/{cours}/types-projets/{typeProjet}/grille', [GrilleCorrectionController::class, 'update'])
        ->name('types-projets.grille.update');

    Route::delete('/cours/{cours}/types-projets/{typeProjet}/grille', [GrilleCorrectionController::class, 'destroy'])
        ->name('types-projets.grille.destroy');

    // Banque de questions (sections de type choix_questions) — gérées par l'enseignant
    Route::post('/cours/{cours}/types-projets/{typeProjet}/sections/{section}/questions', [QuestionBanqueController::class, 'store'])
        ->name('types-projets.sections.questions.store');

    Route::patch('/cours/{cours}/types-projets/{typeProjet}/sections/{section}/questions/reorder', [QuestionBanqueController::class, 'reorder'])
        ->name('types-projets.sections.questions.reorder');

    Route::put('/cours/{cours}/types-projets/{typeProjet}/sections/{section}/questions/{question}', [QuestionBanqueController::class, 'update'])
        ->name('types-projets.sections.questions.update');

    Route::delete('/cours/{cours}/types-projets/{typeProjet}/sections/{section}/questions/{question}', [QuestionBanqueController::class, 'destroy'])
        ->name('types-projets.sections.questions.destroy');

    // Tâches du type de projet (définies par l'enseignant, section type 'tache')
    Route::post('/cours/{cours}/types-projets/{typeProjet}/taches', [TypeProjetTacheController::class, 'store'])
        ->name('types-projets.taches.store');

    Route::patch('/cours/{cours}/types-projets/{typeProjet}/taches/reorder', [TypeProjetTacheController::class, 'reorder'])
        ->name('types-projets.taches.reorder');

    Route::put('/cours/{cours}/types-projets/{typeProjet}/taches/{tache}', [TypeProjetTacheController::class, 'update'])
        ->name('types-projets.taches.update');

    Route::delete('/cours/{cours}/types-projets/{typeProjet}/taches/{tache}', [TypeProjetTacheController::class, 'destroy'])
        ->name('types-projets.taches.destroy');

    // Liens d'entrevue du cours (définis par l'enseignant)
    Route::post('/cours/{cours}/liens-entrevue', [CoursLienEntrevueController::class, 'store'])
        ->name('cours.liens-entrevue.store');

    Route::patch('/cours/{cours}/liens-entrevue/reorder', [CoursLienEntrevueController::class, 'reorder'])
        ->name('cours.liens-entrevue.reorder');

    Route::put('/cours/{cours}/liens-entrevue/{lien}', [CoursLienEntrevueController::class, 'update'])
        ->name('cours.liens-entrevue.update');

    Route::delete('/cours/{cours}/liens-entrevue/{lien}', [CoursLienEntrevueController::class, 'destroy'])
        ->name('cours.liens-entrevue.destroy');

    // Échéancier du cours
    Route::post('/cours/{cours}/echeancier', [EcheancierController::class, 'store'])
        ->name('echeancier.store');

    Route::put('/cours/{cours}/echeancier/{etape}', [EcheancierController::class, 'update'])
        ->name('echeancier.update');

    Route::delete('/cours/{cours}/echeancier/{etape}', [EcheancierController::class, 'destroy'])
        ->name('echeancier.destroy');

    Route::delete('/cours/{cours}/echeancier', [EcheancierController::class, 'destroyAll'])
        ->name('echeancier.destroyAll');

    Route::patch('/cours/{cours}/echeancier/{etape}/toggle', [EcheancierController::class, 'toggleDone'])
        ->name('echeancier.toggle');

    // Visioconférences — créées et gérées par l'enseignant
    Route::post('/cours/{cours}/visio', [VisioConferenceController::class, 'store'])
        ->name('cours.visio.store');

    Route::put('/cours/{cours}/visio/{visio}', [VisioConferenceController::class, 'update'])
        ->name('cours.visio.update');

    Route::delete('/cours/{cours}/visio/{visio}', [VisioConferenceController::class, 'destroy'])
        ->name('cours.visio.destroy');
});

// ─── Étudiant ─────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:etudiant'])->group(function () {
    Route::get('/cours', [CoursController::class, 'index'])
        ->name('cours.index');

    Route::get('/etudiant', [EtudiantController::class, 'index'])
        ->name('etudiant.index');

    // Sections d'un cours dans lesquelles l'étudiant est inscrit
    Route::get('/cours/{cours}/classes', [ClasseController::class, 'indexForStudent'])
        ->name('classes.index');

    // Groupes dans une classe (section) — l'étudiant crée et consulte son groupe
    Route::get('/cours/{cours}/classes/{classe}/groupes', [GroupeController::class, 'index'])
        ->name('groupes.index');

    Route::post('/cours/{cours}/classes/{classe}/groupes', [GroupeController::class, 'store'])
        ->name('groupes.store');

    // Notes collaboratives du groupe
    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/notes', [GroupeController::class, 'storeNote'])
        ->name('groupes.notes.store');

    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}/notes/{note}', [GroupeController::class, 'destroyNote'])
        ->name('groupes.notes.destroy');

    // Progression personnelle de l'étudiant sur l'échéancier
    Route::patch('/cours/{cours}/echeancier/{etape}/toggle-etudiant', [EcheancierController::class, 'toggleEtudiant'])
        ->name('echeancier.toggleEtudiant');
});

// ─── Échanges groupe ↔ témoin + Consentement vidéo (tous les rôles auth concernés) ──
Route::middleware(['auth', 'role:etudiant,enseignant,admin,personne_agee'])->group(function () {
    Route::get('/cours/{cours}/classes/{classe}/groupes/{groupe}/echanges', [GroupeEchangeController::class, 'index'])
        ->name('groupes.echanges.index');

    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/echanges', [GroupeEchangeController::class, 'store'])
        ->name('groupes.echanges.store');

    // Consentement vidéo — membres du groupe + personne âgée
    Route::post('/cours/{cours}/groupes/{groupe}/projets/{typeProjet}/consentement', [ConsentementVideoController::class, 'store'])
        ->name('projets.consentement.store');
});

// ─── Corrections inline des notes (enseignant + admin) ────────────────────────
Route::middleware(['auth', 'role:enseignant,admin'])->group(function () {
    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/notes/{note}/corrections', [GroupeController::class, 'upsertNoteCorrection'])
        ->name('groupes.notes.corrections.upsert');

    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}/notes/{note}/corrections/{correction}', [GroupeController::class, 'destroyNoteCorrection'])
        ->name('groupes.notes.corrections.destroy');
});

// ─── Actions créateur du groupe ────────────────────────────────────────────────
Route::middleware(['auth', 'role:etudiant'])->group(function () {
    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/thematiques', [GroupeController::class, 'updateThematiques'])
        ->name('groupes.thematiques.update');

    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/membres', [GroupeController::class, 'updateMembres'])
        ->name('groupes.membres.update');
});

// ─── Classes et Groupes (étudiant + enseignant + admin) ───────────────────────
Route::middleware(['auth', 'role:etudiant,enseignant,admin'])->group(function () {
    // Détail d'une classe (section)
    Route::get('/cours/{cours}/classes/{classe}', [ClasseController::class, 'show'])
        ->name('classes.show');

    // Détail d'un groupe
    Route::get('/cours/{cours}/classes/{classe}/groupes/{groupe}', [GroupeController::class, 'show'])
        ->name('groupes.show');

    // Médias du groupe
    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/medias', [GroupeMediaController::class, 'store'])
        ->name('groupes.medias.store');

    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}/medias/{media}', [GroupeMediaController::class, 'destroy'])
        ->name('groupes.medias.destroy');

    // ─── Projets de recherche ─────────────────────────────────────────────────
    // Un projet par (groupe × TypeProjet) — index liste tous les TypeProjets accessibles
    Route::get('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets', [ProjetRechercheController::class, 'index'])
        ->name('projets.index');

    // Toutes les routes suivantes sont scoped par {typeProjet}
    Route::get('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/edit', [ProjetRechercheController::class, 'show'])
        ->name('projets.show');

    Route::get('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/apercu', [ProjetRechercheController::class, 'apercu'])
        ->name('projets.apercu');

    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}', [ProjetRechercheController::class, 'update'])
        ->name('projets.update');

    // Conclusion individuelle de l'étudiant authentifié
    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/conclusion', [ProjetRechercheController::class, 'updateConclusion'])
        ->name('projets.conclusion.update');

    // Commentaires de l'enseignant par champ (enseignant uniquement — vérifié dans le controller)
    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/commentaires', [ProjetRechercheController::class, 'upsertCommentaire'])
        ->name('projets.commentaires.upsert');

    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/commentaires/{commentaire}', [ProjetRechercheController::class, 'destroyCommentaire'])
        ->name('projets.commentaires.destroy');

    // Notes de la grille de correction (enseignant uniquement — vérifié dans le controller)
    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/notes', [ProjetRechercheController::class, 'upsertNote'])
        ->name('projets.notes.upsert');

    // Grille de correction personnalisée (enseignant uniquement — vérifié dans le controller)
    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/grille/notes', [ProjetRechercheController::class, 'upsertNoteGrille'])
        ->name('projets.grille.notes.upsert');

    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/grille/malus', [ProjetRechercheController::class, 'toggleMalusGrille'])
        ->name('projets.grille.malus.toggle');

    // Sections dynamiques — contenu rédigé par les étudiants
    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}', [ProjetRechercheController::class, 'updateSectionContenu'])
        ->name('projets.sections.update');

    // Paragraphes de section de type 'paragraphes' — CRUD + réordonnancement
    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/paragraphes', [ProjetRechercheController::class, 'storeSectionParagraphe'])
        ->name('projets.sections.paragraphes.store');

    Route::patch('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/paragraphes/reorder', [ProjetRechercheController::class, 'reorderSectionParagraphes'])
        ->name('projets.sections.paragraphes.reorder');

    Route::patch('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/paragraphes/{paragraphe}', [ProjetRechercheController::class, 'updateSectionParagraphe'])
        ->name('projets.sections.paragraphes.update');

    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/paragraphes/{paragraphe}', [ProjetRechercheController::class, 'destroySectionParagraphe'])
        ->name('projets.sections.paragraphes.destroy');

    // Paragraphes de développement — CRUD + réordonnancement
    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/developpements', [ProjetRechercheController::class, 'storeDeveloppement'])
        ->name('projets.developpements.store');

    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/developpements/{developpement}', [ProjetRechercheController::class, 'updateDeveloppement'])
        ->name('projets.developpements.update');

    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/developpements/{developpement}', [ProjetRechercheController::class, 'destroyDeveloppement'])
        ->name('projets.developpements.destroy');

    Route::patch('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/developpements/reorder', [ProjetRechercheController::class, 'reorderDeveloppements'])
        ->name('projets.developpements.reorder');

    // Annotations inline de l'enseignant par champ (enseignant uniquement — vérifié dans le controller)
    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/annotations', [ProjetRechercheController::class, 'upsertAnnotation'])
        ->name('projets.annotations.upsert');

    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/annotations/{annotation}', [ProjetRechercheController::class, 'destroyAnnotation'])
        ->name('projets.annotations.destroy');

    // Toggles prof — visibilité des corrections + verrouillage (enseignant uniquement — vérifié dans le controller)
    Route::patch('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/correction-visible', [ProjetRechercheController::class, 'toggleCorrectionVisible'])
        ->name('projets.correction-visible.toggle');

    Route::patch('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/verrouille', [ProjetRechercheController::class, 'toggleVerrouille'])
        ->name('projets.verrouille.toggle');

    // Remise de travail
    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/remettre', [ProjetRechercheController::class, 'remettreTravail'])
        ->name('projets.remettre');

    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/annuler-remise', [ProjetRechercheController::class, 'annulerRemise'])
        ->name('projets.annulerRemise');

    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/voter-remise', [ProjetRechercheController::class, 'voterRemise'])
        ->name('projets.voterRemise');

    // Renvois (endnotes) — accessibles aux membres du groupe
    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/renvois', [ProjetRechercheController::class, 'storeRenvoi'])
        ->name('projets.renvois.store');

    Route::patch('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/renvois/{renvoi}', [ProjetRechercheController::class, 'updateRenvoi'])
        ->name('projets.renvois.update');

    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/renvois/{renvoi}', [ProjetRechercheController::class, 'destroyRenvoi'])
        ->name('projets.renvois.destroy');

    // Commentaires d'enseignant sur les renvois (enseignant uniquement — vérifié dans le controller)
    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/renvois/{renvoi}/commentaires', [ProjetRechercheController::class, 'storeRenvoiCommentaire'])
        ->name('projets.renvois.commentaires.store');

    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/renvois/{renvoi}/commentaires/{renvoiCommentaire}', [ProjetRechercheController::class, 'destroyRenvoiCommentaire'])
        ->name('projets.renvois.commentaires.destroy');

    // Toggle mode édition enseignant (enseignant uniquement — vérifié dans le controller)
    Route::patch('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/mode-edition-enseignant', [ProjetRechercheController::class, 'toggleModeEditionEnseignant'])
        ->name('projets.mode-edition-enseignant.toggle');

    Route::get('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/pdf', [ProjetRechercheController::class, 'exportPdf'])
        ->name('projets.export.pdf');

    Route::get('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/word', [ProjetRechercheController::class, 'exportWord'])
        ->name('projets.export.word');

    Route::get('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/apercu-notes', [ProjetRechercheController::class, 'apercuNotes'])
        ->name('projets.apercu.notes');

    // Concepts d'entrevue — CRUD + réordonnancement + lignes
    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/concepts', [EntrevueConceptController::class, 'store'])
        ->name('projets.sections.concepts.store');

    Route::patch('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/concepts/reorder', [EntrevueConceptController::class, 'reorder'])
        ->name('projets.sections.concepts.reorder');

    Route::patch('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/concepts/{concept}', [EntrevueConceptController::class, 'update'])
        ->name('projets.sections.concepts.update');

    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/concepts/{concept}', [EntrevueConceptController::class, 'destroy'])
        ->name('projets.sections.concepts.destroy');

    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/concepts/{concept}/lignes', [EntrevueConceptController::class, 'storeLigne'])
        ->name('projets.sections.concepts.lignes.store');

    Route::patch('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/concepts/{concept}/lignes/{ligne}', [EntrevueConceptController::class, 'updateLigne'])
        ->name('projets.sections.concepts.lignes.update');

    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/concepts/{concept}/lignes/{ligne}', [EntrevueConceptController::class, 'destroyLigne'])
        ->name('projets.sections.concepts.lignes.destroy');

    // Médias de section (vidéo/audio) — upload ou URL
    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/medias', [ProjetSectionMediaController::class, 'store'])
        ->name('projets.sections.medias.store');

    Route::delete('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/medias/{media}', [ProjetSectionMediaController::class, 'destroy'])
        ->name('projets.sections.medias.destroy');

    // Schéma visuel DEP — sauvegarde JSON (upsert) + upload image
    Route::put('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/schema', [ProjetSchemaVisuelController::class, 'update'])
        ->name('projets.sections.schema.update');

    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/schema/images', [ProjetSchemaVisuelController::class, 'uploadImage'])
        ->name('projets.sections.schema.images');

    // Choix de questions — étudiant sélectionne ses questions dans la banque
    Route::post('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/sections/{section}/questions/choisir', [QuestionBanqueController::class, 'choisir'])
        ->name('projets.sections.questions.choisir');

    // Tâches du groupe — assignation d'un membre + toggle complété
    Route::patch('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/taches/{tache}/assigner', [GroupeTacheController::class, 'assigner'])
        ->name('groupes.taches.assigner');

    Route::patch('/cours/{cours}/classes/{classe}/groupes/{groupe}/projets/{typeProjet}/taches/{tache}/toggle', [GroupeTacheController::class, 'toggleCompleted'])
        ->name('groupes.taches.toggle');
});

require __DIR__.'/settings.php';
