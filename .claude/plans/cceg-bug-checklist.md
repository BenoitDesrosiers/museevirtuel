# CCEG — Liste de bugs et améliorations

Checklist pour suivre l'avancement des correctifs. Cocher `- [x]` quand c'est corrigé et vérifié.

**Profil de test :** enseignant  
**Dernière mise à jour :** 2026-09-03

---

## Page accueil cours

- [x] **Création de classe** — Rétroaction manquante pour le numéro de la classe ; le bouton « Créer » est caché s'il n'y a pas une longueur minimale requise *(corrigé : validation client FR, hint, bouton toujours actif)*
- [x] **Ajout de document** — Message de rétroaction en anglais : *The document field must be a file of type: pdf, doc, docx.* *(corrigé : messages FR via __() dans CoursDocumentController)*
- [x] **Section documents** — Le nom du document téléchargé n'est pas le même que le nom du document original *(corrigé : route download avec nom_original, UUID conservé sur disque)*
- [x] **Suppression d'un document** — Devrait être un modal plutôt qu'un `alert` *(corrigé : ConfirmationModal comme pour la suppression de classe)*
- [ ] **Suppression objectif** — Modal au lieu de `alert`
- [ ] **Ajout de référence biblio** — Doit-on suivre l'APA ? Si oui, le mentionner ou mettre les champs en conséquence
- [x] **Suppression de référence** — Modal de confirmation
- [ ] **Échéancier** — Possibilité de changer la semaine ?
- [ ] **Planifier / modifier une visioconférence** — Pas de petit calendrier ; difficile d'ajouter une date
- [x] **Suppression de visioconférence** — Modal pls *(corrigé : ConfirmationModal dans VisioSession.vue)*
- [ ] **Nom de classe** — Le nom d'une classe n'est pas le même que dans la barre verticale à gauche
- [ ] **Création de cours / groupes** — Le nombre minimal ne devrait pas bloquer la création d'un groupe si le nombre de personnes restantes est plus petit que le minimum

---

## Page nouveau type de projet

- [ ] **Date de remise** — Pas de petit calendrier ; difficile d'ajouter une date
- [ ] **Date de remise** — Message d'erreur en anglais : *The date remise field must be a valid date.*
- [ ] **Petits points** — Portent à confusion ; on pensait pouvoir les déplacer
- [ ] **Après création** — Les paramètres de remise ne sont pas sauvegardés correctement (enlevés)
- [ ] **Modification type existant** — Les paramètres de remise ne sont pas enregistrés correctement
- [ ] **Section évaluation** — La pondération est sauvegardée mais pas l'option d'évaluation sommative
- [ ] **Critères globaux** — La préférence liste vs tableau n'est pas conservée
- [ ] **Critères globaux** — Le bouton pour rendre tout positif ou négatif ne fonctionne pas

---

## Page type de projet

- [ ] **Alignement** — Le bouton est trop proche de la phrase qui le précède

---

## Barre d'options verticale

- [ ] **Organisation** — Types de projet et cours affichés de manière confuse, sans label clair

---

## Visualisation d'une classe

- [ ] **Création de groupe** — Comment créer un groupe ? Message « pas de groupe » mais pas de bouton pour en créer un
- [ ] **Création d'étudiant** — Aucune validation minimale (ex. DA : chiffres seulement)
- [ ] **Suppression d'étudiant** — Modal pls
- [ ] **Statut du cours** — Optionnel à la création d'un étudiant

---

## Global

- [ ] **Impersonate** — Permettre au prof d'usurper l'identité d'un étudiant

---

## Création de cours

- [x] **Messages d'erreur** — Les messages d'erreur étaient en anglais lors de la création de cours *(corrigé : validation client FR + messages serveur FR)*
- [x] **Champ Année** — Les flèches pour sélectionner l'année n'avaient pas de style appliqué *(corrigé : composant `NumberInput`)*
- [x] Après édition d'un cours, les champs n'était pas vide lors de la création.

### Vérifications supplémentaires (session 2026-09-03)

- [x] Validation client : toutes les erreurs requises s'affichent en même temps à la soumission
- [x] Taille équipe min. préremplie à **1**
- [x] Validation max ≥ min avec message sous **Taille équipe max.**
- [x] Chaque erreur apparaît sous le bon champ (alignement)
- [x] Messages serveur en français aussi dans **Modifier le cours**

---

## Création d'étudiant

- [ ] **Erreur SQL** — `FOREIGN KEY constraint failed` à l'insertion dans `classe_etudiant`  
  Ex. : cours créé avec id 5, le logiciel utilise l'id 6  
  ```
  SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed
  insert into "classe_etudiant" ("classe_id", "statut_cours", "user_id", ...)
  ```

---

## Création de groupe

- [ ] **Permission prof** — Permettre la création de groupe par le professeur

---

## Création d'un template de musée

- [ ] **Terminologie** — Changer le terme « template » pour « gabarit »

---

## Statistiques

| Section | Total | Fait |
|---------|------:|-----:|
| Page accueil cours | 12 | 5 |
| Nouveau type de projet | 8 | 0 |
| Page type de projet | 1 | 0 |
| Barre verticale | 1 | 0 |
| Visualisation classe | 4 | 0 |
| Global | 1 | 0 |
| Création de cours | 2 + 5 | 2 |
| Création étudiant | 1 | 0 |
| Création groupe | 1 | 0 |
| Template musée | 1 | 0 |
| **Total** | **37** | **7** |

> Mettre à jour le tableau au fur et à mesure des correctifs.
