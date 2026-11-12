# Audit des contrôleurs — anomalies et plan de correction

## Contexte

Revue complète des 61 fichiers de app/Http/Controllers (≈10 400 lignes). Le dernier PR (#3) a supprimé des
contrôleurs morts. Objectif ici : remettre les contrôleurs en conformité avec CLAUDE.md (1 contrôleur = 1 ressource,
logique dans Services/Actions, FormRequest, Policies, docblocks) et corriger les failles découvertes en chemin.
Filet de sécurité existant : 6 Policies (app/Policies) et 49 fichiers de tests Feature.

---

## 1. Anomalies relevées

### 1.A Failles de sécurité / bugs (vérifiés dans le code)

| # | Emplacement | Problème |
|---|---|---|
| S1 | Cours/CoursDocumentController.php:85 | destroy ne vérifie pas $document->cours_id === $cours->id (IDOR, download le fait) |
| S2 | Classe/ClasseEtudiantController.php:76 | update modifie n'importe quel User sans vérifier son inscription dans la classe |
| S3 | SessionController.php:23 + routes/web.php:66 | ~~PATCH /login : tout utilisateur connecté peut se donner le rôle enseignant~~ **Écarté (2026-11-04)** : SessionController est conservé volontairement comme outil de test. |
| S4 | Projet/ProjetSectionMediaController.php:95 | destroy : n'importe quel enseignant/admin supprime le média de n'importe quel projet ; aucune vérif groupe/typeProjet |
| S5 | Projet/Concerns/AutoriseProjetRecherche.php:57, EntrevueConceptController.php:239 | TypeProjet↔Cours vérifié via enseignant_id au lieu de cours_id → TypeProjet d'un autre cours accepté |
| S6 | ProjetSchemaVisuelController, ConsentementVideoController | Aucune vérif TypeProjet↔Cours ; in_array($role,['enseignant','admin']) accepte tout enseignant |
| S7 | Groupe/GroupeTacheController.php:31,72, QuestionBanqueController::choisir:143 | Maillon typeProjet->cours_id manquant dans la chaîne |
| S8 | VisioConferenceController.php:115,144,212 | groupe_id de la visio ignoré → enregistrement visible par tout le cours |
| S9 | Musee/MuseeBloc\|Image\|Meta\|VideoSegment | Édition non bloquée côté serveur quand le musée est soumis/approuvé/verrouillé (seul le front calcule peutEditer) |
| S10 | Musee/MuseeBlocController.php:72-76,294… | exists: globaux sur image_id, groupe_video_id… → référence à des ressources d'autres groupes |
| S11 | Musee/MuseePublicController.php:159 | Tout utilisateur authentifié voit un musée non publié |
| S12 | Groupe/GroupeVideoController::jumeler:270 | Peut fusionner un brouillon d'un coéquipier invisible pour l'étudiant |
| S13 | AdministrationController.php:75 | Mot de passe initial codé en dur 'Enseignant' |
| S14 | InscriptionTemoinController.php:57 | Mot de passe en clair en session ; commentaire affirme un chiffrement absent (SESSION_ENCRYPT=false) |
| B1 | Groupe/GroupeVideoController.php:125 | auteur:id,name — colonne name inexistante (prenom,nom) |
| B2 | Projet/ProjetRemiseController.php:130 | Lit $projet->remises_multiples (colonne héritée) au lieu de TypeProjet |
| B3 | EntrevueConceptController::trouverProjet:252 | Ne charge pas typeProjet → peutEtreRemis() utilise les anciennes colonnes |
| B4 | Projet/ProjetMuseeController.php:103 | keyBy('critere_id') écrase correction groupe vs individuelle |
| B5 | Musee/MuseePublicationController::queue:372,385 | $groupe->nom (colonne supprimée) + N+1 sur MuseeMeta + eager museeImages inutilisé |
| B6 | Musee/MuseeVideoSegmentController.php:119 | update sans gt:debut_secondes (contrairement à store) |
| B7 | EtudiantReferenceController::syncZotero:83 | Renumérote l'ordre des références existantes à chaque synchro |
| B8 | Classe/ClasseEtudiantController.php:54 | Compte étudiant créé avant le test d'inscription → orphelin si rejet |
| B9 | EnseignantController.php:262 vs GroupeController.php:557 | Deux thematiquesVisibles() avec des règles différentes |
| B10 | ProjetRecherche::firstOrCreate (16 occurrences, ex. ProjetDeveloppementController:33) | Création avant autorisation, et écriture sur des GET (show, MuseeTemplate::edit, RendMuseeProjet) |

### 1.B Contrôleurs trop longs / méthodes trop longues

| Fichier | Lignes | Méthodes > 40 l. |
|---|---|---|
| Projet/ProjetRechercheController.php | 726 | show 184, construireSections 104, index 73, apercu 66, updateConclusion 45 |
| Groupe/GroupeController.php | 602 | show 147, store 74, updateMembres 67 |
| Groupe/GroupeVideoController.php | 435 | 13 méthodes publiques, show 43, store 41 |
| Classe/ClasseController.php | 426 | apercuNotesAccumulees 95, show 82, apercuNotesClasse 73 |
| Musee/MuseePublicationController.php | 395 | validerObligatoires 63, queue 42 (machine à états complète) |
| Musee/MuseeBlocController.php | 376 | validerContenu 89, store 61 |
| Musee/MuseePublicController.php | 307 | show 131 |
| Projet/ProjetRemiseController.php | 296 | contenusTextuelsManquants 119, voter 45 |
| Cours/CoursController.php | 295 | index 104 (tableau de bord étudiant), show 86 |
| TypeProjet/TypeProjetController.php | 278 | store 81, update 83 (16 règles dupliquées) |
| EnseignantController.php | 270 | index 94 |
| Projet/Concerns/RendMuseeProjet.php | 244 | renderMuseeShow 178 |

### 1.C Méthodes hors ressource (violent « 1 contrôleur = 1 ressource »)

- GroupeController : storeNote/destroyNote, upsertNoteCorrection/destroyNoteCorrection, assignerTemoin, updateMembres, updateThematiques
- GroupeVideoController : transcrire, modifierTranscription, importerTranscription, editer, jumeler, publier, stream, statut
- ClasseController : apercuNotesClasse, apercuNotesAccumulees
- EnseignantController : createCours/editCours (appartiennent à CoursController), showTemoin/approuverTemoin/declinerTemoin/desapprouverTemoin
- AdministrationController : storeEnseignant/updateEnseignant/destroyEnseignant, approuverTemoin/declinerTemoin (doublon divergent avec EnseignantController : approuve_par_id non renseigné côté admin)
- ProjetRechercheController : updateSectionContenu, updateConclusion, toggleVerrouille, toggleModeEditionEnseignant, apercu
- EntrevueConceptController : storeLigne/updateLigne/destroyLigne
- MuseePublicationController : soumettre/annulerSoumission/approuver/rejeter/toggle/queue
- MuseeTemplateController : updateCanevas/updateContraintes (écrivent TypeProjetSection, comme SectionController et TypeProjetController::update → 3 points d'écriture)
- EtudiantReferenceController : syncZotero/saveCredential/destroyCredential
- VisioConferenceController : startSession/endSession/storeRecording/streamRecording

### 1.D Duplication

- reorder : 13 copies quasi identiques (Cours ×3, QuestionBanque, EntrevueConcept, ProjetSectionParagraphe, ProjetDeveloppement, MuseeBloc, TypeProjetCritere, TypeProjetTache, Section…), boucle de N UPDATE sans transaction, règles divergentes.
- Renumérotation après suppression : ~13 copies ; max('ordre') + 1 ~15 fois, avec un départ à 0 (Echeancier) ou à 1 (les autres).
- ProjetSectionParagrapheController ≈ ProjetDeveloppementController (jumeaux).
- Calcul des notes : 2× dans ClasseController, 1× dans ProjetCorrectionController::apercuNotes (+ Show.vue).
- Sérialisation musée (blocs/images/meta) : 3 à 5 copies (RendMuseeProjet, ProjetMuseeController, MuseePublicController, MuseeImageController).
- Filtre de visibilité des vidéos : 3× (GroupeController:262, GroupeVideoController:47,134).
- Mapping visio → props : 4× (Cours ×2, Groupe, Enseignant).
- Blocs de garde copiés : ProjetSchemaVisuel, MuseeMeta, ProjetRemise, GroupeTache, EntrevueConcept (triplet ×6).
- Toggles verrouille / mode_edition_enseignant / visible identiques (ProjetRecherche ×2, ProjetCorrection).

### 1.E Incohérences de standard

- Validation : 44 contrôleurs utilisent $request->validate() inline ; seulement ~12 FormRequests. StoreClasseRequest/UpdateClasseRequest existent mais ne sont pas utilisés. Règles store/update dupliquées presque partout.
- Autorisation : 3 styles ($this->authorize, Gate::authorize, abort_if/abort_unless manuels) ; TypeProjetPolicy jamais utilisée ; aucune Policy Projet/Musée ; ability manageThematiques détournée pour l'édition de contenu ; role === 'admin' vs isAdmin() ; 404 vs 403 pour une incohérence de chaîne.
- Logique métier dans les contrôleurs : transactions (GroupeController, ProjetRemise, SessionController), Intervention Image (GroupeMediaController::editer), stockage de fichiers manuel (GroupeVideo, MuseeImage, MuseeMeta, Visio) au lieu de l'Action StoreUploadedFile (instanciée par new quand elle est utilisée), parsing HTML regex (ProjetAnnotation, AutoriseProjetRecherche).
- Opérations multiples sans transaction : TypeProjet::store/update, ProjetCritereCorrection, ProjetAnnotation, MuseePublication::approuver/rejeter, QuestionBanque::choisir, InscriptionTemoin.
- Types de retour : JSON vs back() mélangés pour des actions similaires (ex. MuseeImage : JSON/JSON/redirect ; ProjetSchemaVisuel : redirect/JSON).
- Messages : clés __('x.created'), phrases dans __(), concaténations dans __() (non traduisibles), chaînes en dur — parfois dans le même fichier.
- Nommage : mélange FR/EN (upsert/toggleCompleted vs cloner/assigner), toggleVisibleGroupe qui n'est pas un toggle, SectionController sans préfixe TypeProjet, variable $clé, $blocqueParStatut.
- Traits Projet/Concerns/* dans le dossier Controllers : logique d'autorisation (→ Policy) et de rendu (→ Service/Resource).
- Code mort : MuseePeriodeController + MuseeRegionController (non routés, clones), EtudiantController (simple redirect), SessionController (marqué « à supprimer »), import inutilisé ProjetRechercheController:26, en-têtes vides, commentaires « Il faudra retirer… en production » (Groupe, Classe, Visio, ProjetCorrection).
- Performance : requêtes en boucle (MuseePublication::validerObligatoires, MuseeBloc audio, InscriptionTemoin:48, SessionController:59), modèles entiers passés à Inertia ($cours, $groupe, $classe avec la liste des étudiants envoyée aux étudiants).
- Docblocks : pas de docblock de classe dans Musee/, TypeProjet/, plusieurs Projet/* ; @throws manquants ou faux (AdministrationController:86) ; docblocks faux (500 Mo vs 2 Go, « dispatche un Job » alors que dispatchSync).

---

## 2. Plan de résolution

Une branche + un PR par lot, dans cet ordre. Chaque lot : tests Pest d'abord (régression/faille), puis code, pint,
php artisan test --compact. Pas de signature Claude dans les commits/PR.

### Lot 1 — Failles et bugs (priorité absolue, petits diffs)

- S1, S2, S4, S6, S7, S12 : ajouter les vérifications d'appartenance manquantes. À terme, remplacer par Route::scopeBindings() sur les groupes de routes imbriqués de routes/web.php (aucune route ne l'utilise aujourd'hui) — commencer par les groupes cours/{cours}/classes/{classe}/groupes/{groupe}.
- S5 : cours_id au lieu de enseignant_id dans AutoriseProjetRecherche:57 et EntrevueConceptController:239.
- S3 : supprimer SessionController + route (ou restreindre à local via app()->environment()) — à confirmer.
- S8, S9, S10, S11 : contraindre groupe_id, appliquer bloqueEditionEtudiants()/verrouille côté écriture Musée, scoper les Rule::exists()->where(...).
- S13 : générer un mot de passe aléatoire + lien de réinitialisation (Fortify). S14 : ne pas stocker le mot de passe en session (hacher immédiatement ou repousser la création).
- B1 à B10 : corrections ponctuelles (une ligne chacune pour la plupart).
- Tests : un test Feature par faille (« un enseignant du cours A ne peut pas … du cours B »), dans les fichiers existants (CoursDocumentTest, ClasseEtudiantTest, ProjetSectionMediaTest, VisioConferenceTest, MuseeBlocTest…).

### Lot 2 — Code mort et nettoyage

- Supprimer MuseePeriodeController, MuseeRegionController, EtudiantController (→ Route::redirect), imports inutilisés, en-têtes vides, commentaires temporaires (après décision produit pour les « à retirer en production »).

### Lot 3 — Factorisation transverse (gros gain, faible risque)

- Ordonnancement : une Action app/Actions/ReordonnerElements.php (UPDATE en une transaction, ou CASE) + un trait de modèle HasOrdre (prochainOrdre(), renumeroter()) ; remplacer les 13 reorder et les renumérotations. Une ReorderRequest commune (ordre required array, ordre.* required integer distinct).
- Notes : app/Services/CalculNotesService.php utilisé par ClasseController (×2) et ProjetCorrectionController::apercuNotes.
- Sérialisation musée : app/Services/MuseeSerializer.php (ou API Resources MuseeBlocResource, MuseeImageResource, MuseeMetaResource) utilisé par RendMuseeProjet, ProjetMuseeController, MuseePublicController, MuseeImageController.
- Scopes Eloquent : GroupeVideo::scopeVisiblesPour(User), mapping visio → VisioConferenceResource, une seule thematiquesVisibles (scope Thematique::visiblesPour(User), règle à arbitrer — B9).
- Fichiers : généraliser l'Action StoreUploadedFile (injectée, pas new) à GroupeVideo, MuseeImage, MuseeMeta, Visio.

### Lot 4 — Autorisation unifiée

- Créer les Policies manquantes : ProjetRecherchePolicy (view, editContenu, toggleVerrou, corriger), MuseePublicationPolicy, VisioConferencePolicy, EntrevueConceptPolicy ; utiliser TypeProjetPolicy.
- Migrer Projet/Concerns/AutoriseProjetRecherche vers ProjetRecherchePolicy ; remplacer manageThematiques détourné.
- Uniformiser sur $this->authorize() / Gate::authorize() (un seul style), isAdmin()/isEnseignant() partout, 404 pour une chaîne incohérente.
- Déplacer firstOrCreate après l'autorisation ; pas d'écriture sur GET.

### Lot 5 — Validation via FormRequest

- Brancher StoreClasseRequest/UpdateClasseRequest existants.
- Créer les FormRequests pour les validations ≥ 3 règles ou dupliquées store/update (prioritaires : TypeProjet 17 règles, MuseeBloc ~33, MuseeTemplate, ProjetSchemaVisuel 15, InscriptionTemoin 11, ProjetAnnotation, GroupeVideo, VisioConference, ClasseEtudiant). Une classe Store/Update partageant les règles via un trait (pattern déjà présent dans app/Http/Requests/Concerns).

### Lot 6 — Découpage des contrôleurs (1 ressource = 1 contrôleur)

Exemples de découpage (routes renommées en dot notation + php artisan wayfinder:generate) :
- GroupeController → GroupeNoteController, GroupeNoteCorrectionController, GroupeMembreController, GroupeThematiqueController, GroupeTemoinController (single action).
- GroupeVideoController → GroupeVideoTranscriptionController, GroupeVideoEditionController (editer/jumeler, via Jobs asynchrones plutôt que dispatchSync), GroupeVideoPublicationController, GroupeVideoStreamController.
- EnseignantController/AdministrationController → TemoinApprobationController (une seule version), Admin/EnseignantController ; createCours/editCours → CoursController.
- ProjetRechercheController → ProjetSectionContenuController, ProjetConclusionController, ProjetVerrouController ; extraire construireSections/props de show vers app/Services/ProjetRechercheProps.php.
- MuseePublicationController → app/Services/MuseePublicationService.php (machine à états : soumettre/annuler/approuver/rejeter + transactions) + contrôleurs fins MuseeSoumissionController, MuseeApprobationController, MuseePublicationQueueController.
- MuseeBlocController : sortir validerContenu en FormRequest par type ; updateColonne/updateDimensions → MuseeBlocDispositionController.
- ClasseController::apercuNotes* → ClasseNotesApercuController ; CoursController::index → TableauBordEtudiantController.
- ProjetRemiseController::contenusTextuelsManquants → app/Services/VerificationCompletudeProjet.php (seuils de mots en config).
- EntrevueConceptController → EntrevueLigneController ; EtudiantReferenceController → ZoteroSyncController, ZoteroCredentialController ; Visio → VisioSessionController, VisioEnregistrementController.
- Fusionner ProjetSectionParagraphe et ProjetDeveloppement via une base commune ou un service partagé.

### Lot 7 — Homogénéisation finale

- Conventions de retour : JSON pour les appels axios/autosave, back()->with('success', __('cle.action')) pour les formulaires Inertia — documenter dans CLAUDE.md.
- Messages : uniquement des clés __('ressource.action', ['nom' => …]) dans lang/.
- Nommage français cohérent (toggle* → verbe explicite ; upsert conservé uniquement si sémantique réelle), renommer SectionController → TypeProjetSectionController.
- Docblocks de classe + @throws corrects ; réduire les props Inertia aux colonnes utiles (->only()).
- Ajouter un test arch() Pest : contrôleurs sans $request->validate, sans DB::, suffixe Controller.

---

## 3. Vérification

- Pour chaque lot : php artisan test --compact (49 fichiers Feature existants doivent rester verts) + nouveaux tests ciblés.
- ./vendor/bin/pint --test.
- Lots 6/7 : php artisan route:list (vérifier noms de routes), php artisan wayfinder:generate --no-interaction, npm run build / type-check pour détecter les imports @/actions cassés côté Vue.
- Vérification manuelle sur museevirtuel.test des parcours : création groupe, rédaction projet, éditeur musée, soumission/approbation, visio.
