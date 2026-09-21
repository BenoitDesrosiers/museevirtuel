<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $teachers = array_map(
            fn (int $number): array => [
                'prenom' => "Prof {$number}",
                'nom' => 'Test',
                'email' => "prof{$number}@demo.com",
                'email_verified_at' => $now,
                'password' => Hash::make('password'),
                'role' => 'enseignant',
                'statut' => 'actif',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            range(1, 5),
        );

        DB::table('users')->upsert(
            $teachers,
            ['email'],
            ['prenom', 'nom', 'email_verified_at', 'password', 'role', 'statut', 'updated_at'],
        );
    }

    public function down(): void
    {
        DB::table('users')
            ->whereIn('email', array_map(
                fn (int $number): string => "prof{$number}@demo.com",
                range(1, 5),
            ))
            ->delete();
    }
};
