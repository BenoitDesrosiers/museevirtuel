<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\Thematique;
use App\Models\TypeProjet;
use App\Models\User;
use App\Models\VisioConference;

/**
 * Crée un enseignant avec un cours, une classe de deux étudiants et un groupe.
 *
 * @return array{enseignant: User, cours: Cours, classe: Classe, groupe: Groupe, etudiants: array<int, User>}
 */
function creerTableauBordEnseignant(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'code' => '330-TB',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiants = User::factory()->count(2)->create(['role' => 'etudiant'])->all();
    $classe = Classe::create(['cours_id' => $cours->id]);
    $classe->etudiants()->attach(collect($etudiants)->pluck('id'));

    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $etudiants[0]->id]);
    $groupe->membres()->attach($etudiants[0]->id);

    return compact('enseignant', 'cours', 'classe', 'groupe', 'etudiants');
}

test("le tableau de bord de l'enseignant expose cours, travaux remis et visios", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'etudiants' => $etudiants] = creerTableauBordEnseignant();

    // Un étudiant inscrit dans deux classes du même cours ne compte qu'une fois
    Classe::create(['cours_id' => $cours->id])->etudiants()->attach($etudiants[0]->id);

    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'TP', 'accessible' => true]);
    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
        'titre_projet' => 'Mon projet',
        'remis_le' => now()->subDay(),
    ]);
    ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Non remis'])->id]);

    VisioConference::create(['cours_id' => $cours->id, 'animateur_id' => $enseignant->id, 'jitsi_room' => 'salle-a', 'titre' => 'À venir', 'scheduled_at' => now()->addDay()]);
    VisioConference::create(['cours_id' => $cours->id, 'animateur_id' => $enseignant->id, 'jitsi_room' => 'salle-b', 'titre' => 'Passée', 'scheduled_at' => now()->subDay()]);

    $this->actingAs($enseignant)
        ->get('/enseignant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Enseignant/Index')
            ->has('cours', 1)
            ->where('cours.0.id', $cours->id)
            ->where('cours.0.classes_count', 2)
            ->where('cours.0.etudiants_count', 2)
            ->missing('cours.0.classes')
            ->has('travauxRemis', 1)
            ->where('travauxRemis.0.id', $projet->id)
            ->where('travauxRemis.0.titre_projet', 'Mon projet')
            ->where('travauxRemis.0.remis_le', $projet->fresh()->remis_le->toIso8601String())
            ->where('travauxRemis.0.classe', ['id' => $groupe->id, 'numero' => $groupe->fresh()->numero, 'cours_id' => $cours->id])
            ->where('travauxRemis.0.membres', [$etudiants[0]->only('id', 'prenom', 'nom')])
            ->has('prochainesVisios', 1)
            ->where('prochainesVisios.0.titre', 'À venir')
        );
});

test("le tableau de bord sépare les témoins en attente et ceux approuvés par l'enseignant", function () {
    ['enseignant' => $enseignant] = creerTableauBordEnseignant();
    $thematique = Thematique::factory()->create(['enseignant_id' => $enseignant->id, 'nom' => 'Ma thématique']);
    Thematique::factory()->create(['nom' => "Celle d'un autre"]);

    $enAttente = User::factory()->create(['role' => 'personne_agee', 'statut' => 'en_attente']);
    $enAttente->thematiquesChoisies()->attach($thematique->id);

    // Lié à une thématique invisible pour l'enseignant : ne doit pas apparaître
    User::factory()->create(['role' => 'personne_agee', 'statut' => 'en_attente'])
        ->thematiquesChoisies()->attach(Thematique::factory()->create()->id);

    $approuve = User::factory()->create(['role' => 'personne_agee', 'statut' => 'actif', 'approuve_par_id' => $enseignant->id]);
    User::factory()->create(['role' => 'personne_agee', 'statut' => 'actif', 'approuve_par_id' => User::factory()->create(['role' => 'enseignant'])->id]);

    $this->actingAs($enseignant)
        ->get('/enseignant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('thematiques', 1)
            ->where('thematiques.0.nom', 'Ma thématique')
            ->where('thematiques.0.enseignant.id', $enseignant->id)
            ->has('temoinsEnAttente', 1)
            ->where('temoinsEnAttente.0.id', $enAttente->id)
            ->where('temoinsEnAttente.0.thematiques_choisies.0.nom', 'Ma thématique')
            ->has('temoinsApprouves', 1)
            ->where('temoinsApprouves.0.id', $approuve->id)
        );
});
