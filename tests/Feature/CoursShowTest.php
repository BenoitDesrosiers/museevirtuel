<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\CoursDocument;
use App\Models\CoursObjectif;
use App\Models\CoursReference;
use App\Models\EcheancierEtape;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use App\Models\VisioConference;

test("la page d'un cours expose classes, ressources, types de projets et visios", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create(['nom_cours' => 'Histoire', 'code' => '330-CS', 'groupe' => '01', 'enseignant_id' => $enseignant->id]);

    $classe = Classe::create(['cours_id' => $cours->id]);
    $classe->etudiants()->attach(User::factory()->count(2)->create(['role' => 'etudiant'])->pluck('id'));

    $document = CoursDocument::create(['cours_id' => $cours->id, 'enseignant_id' => $enseignant->id, 'nom_original' => 'plan.pdf', 'file_path' => 'cours/plan.pdf', 'type' => 'pdf', 'taille' => 1024]);
    $etape = EcheancierEtape::create(['cours_id' => $cours->id, 'semaine' => 1, 'periode' => 2, 'etape' => 'Lire', 'is_done' => true, 'ordre' => 1]);
    $objectif = CoursObjectif::create(['cours_id' => $cours->id, 'contenu' => 'Comprendre', 'ordre' => 1]);
    $reference = CoursReference::create(['cours_id' => $cours->id, 'nom' => 'BAnQ', 'url' => 'https://banq.qc.ca', 'ordre' => 1]);

    $tpB = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'B projet', 'description' => 'Desc', 'accessible' => true]);
    $section = TypeProjetSection::create(['type_projet_id' => $tpB->id, 'label' => 'Intro', 'description' => 'D', 'ordre' => 1]);
    $tpA = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'A projet', 'accessible' => false]);

    VisioConference::create(['cours_id' => $cours->id, 'animateur_id' => $enseignant->id, 'jitsi_room' => 'salle', 'titre' => 'Rencontre', 'scheduled_at' => now()->addDay()]);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cours/Show')
            ->where('cours.id', $cours->id)
            ->where('cours.nom_cours', 'Histoire')
            ->has('classes', 1)
            ->where('classes.0.id', $classe->id)
            ->where('classes.0.etudiants_count', 2)
            ->where('classes.0.groupes_count', 0)
            ->has('documents', 1)
            ->where('documents.0.id', $document->id)
            ->where('documents.0.url', $document->url)
            ->where('echeancierEtapes', [
                ['id' => $etape->id, 'semaine' => 1, 'periode' => 2, 'etape' => 'Lire', 'is_done' => true, 'ordre' => 1],
            ])
            ->where('objectifs', [['id' => $objectif->id, 'contenu' => 'Comprendre', 'ordre' => 1]])
            ->where('references', [['id' => $reference->id, 'nom' => 'BAnQ', 'url' => 'https://banq.qc.ca', 'ordre' => 1]])
            // Triés par nom
            ->where('typesProjets', [
                ['id' => $tpA->id, 'nom' => 'A projet', 'description' => null, 'accessible' => false, 'sections' => []],
                ['id' => $tpB->id, 'nom' => 'B projet', 'description' => 'Desc', 'accessible' => true, 'sections' => [
                    ['id' => $section->id, 'label' => 'Intro', 'description' => 'D', 'ordre' => 1],
                ]],
            ])
            ->has('visioConferences', 1)
            ->where('visioConferences.0.titre', 'Rencontre')
        );
});

test("un autre enseignant ne peut pas voir la page d'un cours", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create(['nom_cours' => 'Histoire', 'code' => '330-CS', 'groupe' => '01', 'enseignant_id' => $enseignant->id]);

    $this->actingAs(User::factory()->create(['role' => 'enseignant']))
        ->get("/cours/{$cours->id}")
        ->assertForbidden();
});
