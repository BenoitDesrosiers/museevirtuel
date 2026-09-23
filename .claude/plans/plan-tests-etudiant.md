# Guide de tests pour les étudiants

Ce guide permet de vérifier les principales fonctions de l'application du point
de vue d'un étudiant et de signaler un problème de manière exploitable par
l'équipe de développement.

## Avant de commencer

1. Demander à un enseignant d'importer le fichier `etudiants-test.csv` dans
   une classe de test.
2. Utiliser l'un des comptes étudiants créés par l'import. Le mot de passe est
   `password`.

| No de DA | Nom | Prénom | Statut du cours | Courriel |
|---:|---|---|---|---|
| 240101 | Tremblay | Alexis | Actif | alexis.tremblay@etu.cegepdrummond.ca |
| 240102 | Gagnon | Camille | Actif | camille.gagnon@etu.cegepdrummond.ca |
| 240103 | Roy | Jordan | Actif | jordan.roy@etu.cegepdrummond.ca |
| 240104 | Bouchard | Maelle | Actif | maelle.bouchard@etu.cegepdrummond.ca |
| 240105 | Lefebvre | Sasha | Actif | sasha.lefebvre@etu.cegepdrummond.ca |
| 240106 | Morin | Noah | Actif | noah.morin@etu.cegepdrummond.ca |
| 240107 | Cote | Charlie | Actif | charlie.cote@etu.cegepdrummond.ca |
| 240108 | Bergeron | Emile | Actif | emile.bergeron@etu.cegepdrummond.ca |
| 240109 | Dubois | Ariane | Actif | ariane.dubois@etu.cegepdrummond.ca |
| 240110 | Fortin | Leo | Actif | leo.fortin@etu.cegepdrummond.ca |

3. Prévoir au moins deux étudiants dans la même classe de test et des fichiers
   non confidentiels pour les essais de médias.
4. Noter le navigateur et l'appareil utilisés. Tester idéalement dans un
   navigateur récent (Chrome, Edge, Firefox ou Safari).
5. Tester une action à la fois et noter immédiatement tout comportement
   inattendu.

> Ne pas téléverser de renseignements personnels, de travaux réels d'étudiants
> ni de documents confidentiels dans l'environnement de test.

## Méthode de test générale

Pour chaque fonction :

1. Ouvrir la page concernée.
2. Réaliser l'action.
3. Vérifier que le résultat affiché est exact.
4. Actualiser la page, puis revenir à la page au besoin, pour confirmer que
   les données ont été sauvegardées.
5. Essayer une donnée manquante ou invalide lorsque cela est pertinent.
6. Vérifier qu'un message clair s'affiche et que les données déjà saisies ne
   sont pas perdues inutilement.

Lorsqu'une suppression est testée, confirmer que l'application demande une
confirmation, puis vérifier qu'aucun lien brisé ni élément résiduel n'est
visible.

## Parcours de test étudiant

| # | Fonction |
|---:|---|
| 1 | Connexion et accès à ses cours |
| 2 | Consultation d'un cours et d'une classe |
| 3 | Création, consultation et gestion de son groupe |
| 4 | Échéancier et suivi de sa progression personnelle |
| 5 | Notes collaboratives de groupe |
| 6 | Échanges avec le groupe et le témoin |
| 7 | Consultation et rédaction des projets de recherche |
| 8 | Rédaction de sa conclusion individuelle |
| 9 | Téléversement et gestion des médias du groupe |
| 10 | Téléversement, édition et publication des vidéos du groupe |
| 11 | Consultation des notes et des rétroactions |
| 12 | Références personnelles et synchronisation Zotero |

## Comment signaler un bug

Un bug est un écart reproductible entre le comportement attendu et le
comportement observé. Signaler aussi les messages incompréhensibles, les
problèmes d'affichage, les lenteurs importantes et les problèmes
d'accessibilité.

Avant de le signaler :

1. Refaire l'action une fois pour confirmer le problème.
2. Vérifier si le problème est déjà inscrit dans la liste de suivi des bugs.
3. Ne pas inclure de données personnelles, mots de passe ni documents
   confidentiels dans le signalement ou les captures d'écran.
4. Transmettre le rapport au responsable du projet par le canal convenu
   (liste de suivi, dépôt GitHub, formulaire ou courriel).

Utiliser un rapport par problème et reprendre ce modèle :

```text
Titre : [Zone de l'application] Description courte du problème

Compte utilisé : Étudiant
Page ou fonction :
Date et heure :
Navigateur et appareil :

Étapes pour reproduire :
1.
2.
3.

Résultat attendu :

Résultat observé :

Fréquence : Toujours / Parfois / Une seule fois
Impact : Bloquant / Important / Mineur

Pièces jointes : Capture d'écran, vidéo ou message d'erreur, sans données confidentielles
```

### Choisir l'impact

| Impact | Quand l'utiliser |
|---|---|
| Bloquant | Impossible de poursuivre une tâche importante, par exemple accéder à son groupe ou remettre un travail. |
| Important | La fonction est utilisable, mais donne un résultat erroné, demande un contournement ou risque d'affecter les données. |
| Mineur | Problème de texte, d'alignement, de clarté ou d'affichage qui ne bloque pas le travail. |

### Exemple de signalement

```text
Titre : [Échéancier] La progression d'une étape n'est pas conservée

Compte utilisé : Étudiant
Page ou fonction : Échéancier du cours
Date et heure : 2026-09-14, 15 h 20
Navigateur et appareil : Chrome 140, Windows 11

Étapes pour reproduire :
1. Ouvrir l'échéancier d'un cours.
2. Marquer une étape comme terminée.
3. Actualiser la page.

Résultat attendu :
L'étape reste indiquée comme terminée.

Résultat observé :
L'étape redevient non terminée après l'actualisation.

Fréquence : Toujours
Impact : Important
```

## Gabarit pour demander une nouvelle fonctionnalité

Utiliser ce gabarit pour proposer une fonctionnalité qui n'existe pas encore.
Décrire le besoin et le résultat souhaité sans imposer de solution technique.

```text
Titre : [Nom court de la fonctionnalité à créer]

Besoin :
[Quel problème cette fonctionnalité résoudrait-elle ?]

Contexte :
[Dans quelle page, quel cours ou quelle situation ce besoin se présente-t-il ?]

Utilisateur concerné :
[Étudiant, enseignant, administrateur ou plusieurs rôles.]

Fonctionnalité souhaitée :
[Décrire ce que l'utilisateur devrait pouvoir faire.]

Résultat attendu :
[Ce qui devrait être visible, créé, modifié ou calculé.]

Priorité : Essentielle / Utile / Amélioration
```

### Exemple

```text
Titre : Recevoir un rappel des étapes à venir

Besoin :
Ne pas oublier une étape importante de l'échéancier.

Contexte :
Lorsqu'une étape d'un projet approche de sa date prévue.

Utilisateur concerné :
Étudiant

Fonctionnalité souhaitée :
Afficher un rappel dans l'application pour les étapes à réaliser au cours des
sept prochains jours.

Résultat attendu :
L'étudiant voit les étapes à venir de ses cours lorsqu'il ouvre l'application.

Priorité : Utile
```
