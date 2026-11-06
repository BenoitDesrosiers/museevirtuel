<?php

namespace App\Services;

use App\Actions\SupprimerAnnotationsOrphelines;
use App\Exceptions\TransactionEchoueeException;
use App\Models\ProjetRecherche;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * Service des listes ordonnées de paragraphes rédigés par les étudiants dans un projet.
 */
class ParagraphesProjetService
{
    /**
     * Crée le service.
     */
    public function __construct(
        private OrdreElements $ordres,
        private SupprimerAnnotationsOrphelines $supprimerOrphelines,
    ) {}

    /**
     * Ajoute un paragraphe vide à la fin de la liste.
     *
     * @param  HasMany<Model, ProjetRecherche>  $portee
     * @param  array<string, mixed>  $attributs  Attributs propres à la liste (ex. section_id)
     */
    public function ajouter(HasMany $portee, array $attributs = []): Model
    {
        return $portee->create([
            ...$attributs,
            'ordre' => $this->ordres->prochain($portee),
            'titre' => null,
            'contenu' => null,
        ]);
    }

    /**
     * Met à jour le titre et/ou le contenu d'un paragraphe.
     *
     * Quand le contenu change, les annotations dont la marque a disparu du HTML sont supprimées.
     *
     * @param  string  $champ  Clé du champ annotable (ex. « developpement_12 »)
     * @param  array{titre?: string|null, contenu?: string|null}  $donnees
     */
    public function mettreAJour(Model $paragraphe, ProjetRecherche $projet, string $champ, array $donnees): void
    {
        $paragraphe->update($donnees);

        if (array_key_exists('contenu', $donnees) && $donnees['contenu'] !== null) {
            $this->supprimerOrphelines->execute($projet, $champ, $donnees['contenu']);
        }
    }

    /**
     * Supprime un paragraphe et renumérote les suivants, sauf si c'est le dernier de la liste.
     *
     * @param  HasMany<Model, ProjetRecherche>  $portee
     * @return bool false si le paragraphe n'a pas été supprimé parce qu'il est le dernier
     *
     * @throws TransactionEchoueeException
     */
    public function supprimer(Model $paragraphe, HasMany $portee): bool
    {
        if ((clone $portee)->count() <= 1) {
            return false;
        }

        $this->ordres->supprimer($paragraphe, $portee);

        return true;
    }

    /**
     * Applique l'ordre reçu du client.
     *
     * @param  HasMany<Model, ProjetRecherche>  $portee
     * @param  list<int>  $ids
     *
     * @throws ValidationException si un identifiant n'appartient pas à la liste
     * @throws TransactionEchoueeException
     */
    public function reordonner(HasMany $portee, array $ids): void
    {
        $this->ordres->reordonner($portee, $ids);
    }
}
