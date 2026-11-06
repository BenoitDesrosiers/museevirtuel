<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Levée par un service ou une action quand une transaction en base échoue.
 *
 * Toutes les écritures de la transaction ont été annulées. Le message est
 * traduit et destiné à l'utilisateur ; l'erreur d'origine est conservée
 * (getPrevious()) et a déjà été journalisée par report().
 */
class TransactionEchoueeException extends RuntimeException
{
    /**
     * Crée l'exception à partir de l'erreur de base de données qui a fait échouer la transaction.
     */
    public static function depuis(Throwable $erreur): self
    {
        return new self(__('transaction.echec'), previous: $erreur);
    }
}
