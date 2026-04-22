<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfille etablissement_id sur les thématiques qui ont un enseignant_id
     * mais pas d'etablissement_id, en copiant la valeur depuis le user associé.
     *
     * Cette migration corrige les thématiques créées avant que le lien
     * enseignant → établissement soit systématiquement propagé.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                UPDATE thematiques t
                JOIN users u ON t.enseignant_id = u.id
                SET t.etablissement_id = u.etablissement_id
                WHERE t.etablissement_id IS NULL
                  AND u.etablissement_id IS NOT NULL
            ');
        } else {
            // SQLite : UPDATE avec sous-requête corrélée
            DB::statement('
                UPDATE thematiques
                SET etablissement_id = (
                    SELECT etablissement_id FROM users
                    WHERE users.id = thematiques.enseignant_id
                      AND users.etablissement_id IS NOT NULL
                )
                WHERE etablissement_id IS NULL
                  AND enseignant_id IS NOT NULL
            ');
        }
    }

    /**
     * Irreversible : on ne peut pas savoir quelles lignes avaient NULL volontairement.
     */
    public function down(): void
    {
        //
    }
};
