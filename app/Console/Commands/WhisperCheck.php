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

        $process = new Process([$binary, '--version']);
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Whisper introuvable.');
            $this->line($process->getErrorOutput());
            $this->newLine();
            $this->line('Solutions :');
            $this->line('  1. pip install openai-whisper');
            $this->line('  2. Ajouter WHISPER_BINARY=<chemin complet> dans .env');

            return self::FAILURE;
        }

        $this->info('Whisper est disponible.');
        $this->line(trim($process->getOutput() ?: $process->getErrorOutput()));

        return self::SUCCESS;
    }
}
