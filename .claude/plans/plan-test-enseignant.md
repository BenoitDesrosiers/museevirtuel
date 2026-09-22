# Guide de tests pour les enseignants

Ce guide permet de vérifier les principales fonctions de l'application du point de vue
d'un enseignant et de signaler un problème de manière exploitable par l'équipe
de développement.

## Avant de commencer

1. Utiliser l'un des comptes enseignants de test suivants :

| Nom | Courriel | Mot de passe |
|---|---|---|
| Maryse Perron | maryse.perron@demo.com | password |
| Michel Landry | michel.landry@demo.com | password |
| Pierre-Olivier Fontaine | pierre-olivier.fontaine@demo.com | password |

2. Utiliser le fichier `etudiants-test`, présent dans [Tests utilisateurs](https://cegepdrummond.sharepoint.com/:f:/s/ZoneAVA-Votrehistoirenotrehistoire/IgCY9CKpOnA4TroDF16PrWhlAdUcBuJ5XHvLrbRPxh3TksE?e=skxFn6) pour ajouter des étudiants à vos classe.
3. Noter le navigateur et l'appareil utilisés. Tester idéalement dans un
   navigateur récent (Chrome, Edge, Firefox ou Safari).
4. Tester une action à la fois et noter immédiatement tout comportement
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

## Parcours de test enseignant

Suivre les étapes dans cet ordre pour tester la configuration d'un cours, le
travail des étudiants et leur évaluation.

|  # | Fonction              | Description                                                                  |
|---:|-----------------------|------------------------------------------------------------------------------|
|  1 | Création de cours     | Créer un cours et vérifier que ses informations sont enregistrées.           |
|  2 | Gestion du cours      | Modifier les paramètres du cours et vérifier les changements.                |
|  3 | Classes               | Ajouter une classe au cours et vérifier qu'elle apparaît dans la liste.      |
|  4 | Étudiants et groupes  | Importer le fichier `etudiants-test`, puis créer et modifier des groupes.    |
|  5 | Types de projets      | Créer un type de projet et définir ses principales options.                  |
|  6 | Critères d'évaluation | Ajouter des critères et vérifier leur pondération.                           |
|  7 | Projets de groupes    | Créer un projet pour un groupe et vérifier les informations affichées.       |
|  8 | Évaluation et notes   | Saisir une évaluation et vérifier le calcul ainsi que l'affichage des notes. |
|  9 | Échéancier            | Ajouter, modifier et supprimer des étapes ou des dates importantes.          |
| 10 | Documents de cours    | Téléverser, consulter et supprimer un document de cours.                     |
| 11 | Médias de classe      | Ajouter, consulter et supprimer un média lié à une classe.                   |
| 12 | Commentaires          | Ajouter, modifier et supprimer un commentaire sur un projet.                 |

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

Les bugs seront signalés dans le document Test_utilisateurs_sept_oct dans le dossier [Tests utilisateurs](https://cegepdrummond.sharepoint.com/:f:/r/sites/ZoneAVA-Votrehistoirenotrehistoire/Documents%20partages/Mus%C3%A9e%20virtuel/Tests%20utilisateurs?d=wa922f498703a4e38ba03175e8fad6865&csf=1&web=1&e=7wGcSx)

Utiliser un rapport par problème et reprendre ce modèle :

```text
Titre court illustrant le bogue:  Description courte du problème

Compte utilisé : Enseignant
Page ou fonction : 
Date : 
Navigateur et appareil : 
Fréquence : Toujours / Parfois / Une seule fois
Impact : Bloquant / Important / Mineur
Capture d'écran
```

### Choisir l'impact

| Impact | Quand l'utiliser |
|---|---|
| Bloquant | Impossible de poursuivre une tâche importante, par exemple créer un cours ou saisir une note. |
| Important | La fonction est utilisable, mais donne un résultat erroné, demande un contournement ou risque d'affecter les données. |
| Mineur | Problème de texte, d'alignement, de clarté ou d'affichage qui ne bloque pas le travail. |

### Exemple de signalement

```text
Titre court illustrant le bogue: Évaluation sommative non conservée

Compte utilisé : Enseignant
Page ou fonction : Création d'un type de projet
Date et heure : 2026-09-14
Navigateur et appareil : Chrome, Windows 11
Fréquence : Toujours
Impact : Important
Capture d'écran : Aucune
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
[Enseignant, étudiant, administrateur ou plusieurs rôles.]

Fonctionnalité souhaitée :
[Décrire ce que l'utilisateur devrait pouvoir faire.]

Priorité : Essentielle / Utile / Amélioration
```

### Exemple

```text
Titre : Dupliquer un type de projet

Besoin :
Éviter de recréer manuellement les mêmes critères et réglages pour des projets
semblables.

Contexte :
Lors de la création d'un nouveau type de projet dans un cours.

Utilisateur concerné :
Enseignant

Fonctionnalité souhaitée :
Ajouter une action « Dupliquer » sur un type de projet existant. L'enseignant
peut ensuite modifier le nom, la pondération ou les critères de la copie.

Priorité : Utile
```
