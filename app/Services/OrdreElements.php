<?php

namespace App\Services;

use App\Exceptions\TransactionEchoueeException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gère l'ordre des éléments d'une liste (ajout en fin, suppression, tri).
 */
class OrdreElements
{
    /**
     * Retourne l'ordre à donner à un nouvel élément placé en fin de liste.
     *
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $portee
     */
    public function prochain(Builder|Relation $portee): int
    {
        return ((clone $portee)->max('ordre') ?? 0) + 1;
    }

    /**
     * Supprime l'élément puis renumérote les suivants pour garder une suite 1..n sans trou.
     *
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $portee
     *
     * @throws TransactionEchoueeException
     */
    public function supprimer(Model $element, Builder|Relation $portee): void
    {
        try {
            DB::transaction(function () use ($element, $portee): void {
                $element->delete();

                (clone $portee)->orderBy('ordre')->each(function (Model $e, int $index): void {
                    $e->update(['ordre' => $index + 1]);
                });
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }
    }

    /**
     * Applique l'ordre reçu du client.
     *
     * Rejette la liste entière si un ID n'appartient pas à la portée : un ID étranger
     * signale soit un client désynchronisé, soit une tentative de toucher une autre liste.
     *
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $portee
     * @param  list<int>  $ids
     *
     * @throws ValidationException si un identifiant n'appartient pas à la portée
     * @throws TransactionEchoueeException
     */
    public function reordonner(Builder|Relation $portee, array $ids): void
    {
        if ((clone $portee)->whereKey($ids)->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'ordre' => 'Certains éléments n’appartiennent pas à cette liste.',
            ]);
        }

        try {
            DB::transaction(function () use ($portee, $ids): void {
                foreach ($ids as $index => $id) {
                    (clone $portee)->whereKey($id)->update(['ordre' => $index + 1]);
                }
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }
    }
}
