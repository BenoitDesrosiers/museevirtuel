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
- [x] **Suppression objectif** — Modal au lieu de `alert`
- [ ] **Ajout de référence biblio** — Doit-on suivre l'APA ? Si oui, le mentionner ou mettre les champs en conséquence
- [x] **Suppression de référence** — Modal de confirmation
- [x] **Échéancier** — Possibilité de changer la semaine ?
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

## Modification des infos d'un étudiant par l'enseignant

- Les validateurs s'affichent mal et sont toujours en anglais. Il faut changer le message d'erreur par défaut.
- Le statut de l'étudiant prend n'importe quelle valeur. Il faut implémenter un enum pour le statut.

## Voir les travaux rémis

Lorsque l'enseignant clique sur le bouton voir des travaux remis, il a un 404. Il faut corriger la page du return dans 
le contrôleur.

## Retour aux classes de l'étudiant à partir de la page créer un groupe

- Quand un étudiant est sur la page Créer un groupe, quand on appui sur le bouton 
"Retour à mes classes", on a l'erreur :
```
The GET method is not supported for route cours/1/classes. Supported methods: POST.
```
Il faut changer la methode de retour POST pour GET dans le fichier web.

## Il est possible pour un étudiant de créer un groupe sans thème

Lorsque l'étudiant veut créer un groupe, il peut choisir d'ajouter un ou des étudiant(s) et une thématique.
Il peut néanmoins créer un groupe avec ses informations à undefined.

Mettre des validateurs pour le nombre de personnes dans le groupe, avec aussi uniquement un thème.

## L'étudiant peut remettre un travail vide

Dans la page d'édition d'un projet, un étudiant peut remettre un travail, sans avoir rempli ni l'introduction, 
ni le développement, ni la conclusion.

On doit mettre des validations sur ces portions et un nombre minimal de mot (100 pour l'introduction, 300 pour 
le développement et 100 pour la conclusion).

## Aucune indication qui montre que la section d'un projet est terminée

Dans la page projets d'un groupe, on voit l'ensemble des projets. Si les étudiants ont par example fini le "projet 
de recherche, il n'y a aucun statut qui montre qu'il a déjà été remis."

## Mettre à jour la connexion à Zotero

Dans la page cours, il y a la section ajouter un connexteur Zotero. La procédure pour aller créer une clé API est 
obselète

## Validateurs sur les Visioconférences 

Dans la page d'un groupe d'étudiants, un étudiant peu planifier une visioconférence. 

- Il n'y a pas de validateur sur le titre : doit être une chaine de caractères.

## Modal pour terminer une visioconférence

Lors d'une visioconférence, quand on clique sur le bouton terminer, c'est une alerte du navigateur qui s'affiche, et le
message n'est pas explicite. 

À vérifier : si un étudiant qui participe, mais qui n'est pas le "owner" de la visioconférence peut aussi l'arrêter.

## Publier une nouvelle note

Dans la page du groupe, on a une section pour publier une nouvelle note, quand on remplit la note et qu'on clique sur 
le bouton "publier", on a une erreur 404. L'action du formulaire n'est surement pas la bonne page.

## Publier une vidéo

Dans la page du groupe d'étudiant, on peut publier une nouvelle vidéo. 
- Mais, après téléversé une video, il n'y a pas d'action pour pouvoir l'a retiré en cas d'erreur. 
- Aussi, le modal qui s'affiche ne supporte pas le scrolling, quand on téléverse une nouvelle vidéo, le bouton 
"Envoyer la vidéo" n'est plus visible.
- Quand on téléverse une vidéo qui dépasse le nombre max d'octet en POST (8388608) un warning s'affiche : il faudrait
valider la taille de la vidéo au niveau du dto par example.
- Peu importe la taille de la vidéo, il met toujours une erreur d'upload. Le message d'erreur n'est pas spécifique.

## Ajouter un document

Dans la page du groupe d'étudiant, on peut ajouter les documents uniquement de type text, mais pas des pdf.
Le message d'erreur n'est pas spécifique.

## Message de validation uniquement en anglais

Dans la page des paramètres/securité, si on clique sur le bouton "enregistrer le mot de passe", la validation 
s'affiche en anglais alors que la langue sélectionnée, c'est le français. 

Changez les messages d'erreur des validateurs pour s'adapter avec le i18n

## Changement de langue

Quand on change la langue de l'application, l'utilisateur doit encore rafraichir la page pour observer le changement de
langue. 

Il faut rafraichir le navigateur directement dans la fonction du contrôleur.

## Visibilité des boutons supprimé et modifié sur une image

Quand on a ajouté une image dans la section "photo", les boutons supprimé et modifié sont un peu transparent, donc peu
visible.

Il vaudrait mieux les placer en dessous de l'image pour plus de visibilité. 

## Modal du navigateur sur le bouton supprimé de l'image

Le modal qui s'affiche quand on clique sur le bouton supprimer de l'image est une alerte du navigateur.

Il faut le remplacer par un modal plus propre.

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
