<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class WhisperCheck extends Command
{
    /**
     * @var string
     */
    protected $signature = 'whisper:check';

    /**
     * @var string
     */
    protected $description = 'Vérifie que la CLI Whisper est accessible et affiche sa version.';

    /**
     * Vérifie la disponibilité du binaire Whisper configuré.
     *
     * Utile pour diagnostiquer rapidement un problème de PATH ou de WHISPER_BINARY
     * avant de lancer des jobs de transcription.
     */
    public function handle(): int
    {
        $binary = config('services.whisper.binary', 'whisper');
        $model = config('services.whisper.model', 'small');

        $this->line("Binaire : <info>{$binary}</info>");
        $this->line("Modèle  : <info>{$model}</info>");
        $this->newLine();

        // `whisper --version` retourne toujours exit code 1 (argparse ne le supporte pas).
        // `whisper --help` crashe sur Windows avec UnicodeEncodeError (CP1252 + CJK).
        // On vérifie l'existence du fichier (chemin absolu) ou la présence dans le PATH.
        $binaryAbsolu = $binary !== basename($binary);

        if ($binaryAbsolu) {
            $whisperDispo = is_file($binary);
        } else {
            $process = new Process(
                PHP_OS_FAMILY === 'Windows' ? ['where', $binary] : ['which', $binary]
            );
            $process->setTimeout(5);
            $process->run();
            $whisperDispo = $process->isSuccessful();
        }

        if (! $whisperDispo) {
            $this->error('Whisper introuvable.');
            $this->newLine();
            $this->line('Solutions :');
            $this->line('  1. pip install openai-whisper');
            $this->line('  2. Ajouter WHISPER_BINARY=<chemin complet> dans ..env');

            return self::FAILURE;
        }

        $this->info('Whisper est disponible.');

        return self::SUCCESS;
    }
}
