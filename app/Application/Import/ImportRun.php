<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * L'état complet d'un import, de son lancement à son rapport de fin.
 *
 * Immuable, et sérialisable de bout en bout : c'est ce que le suivi publie sous la clé
 * du job, ce que l'endpoint de progression rend, et ce que le rapport final archive.
 * Le résumé textuel qu'elle sait rendre est ce que le panneau d'administration affiche
 * déjà, ce qui enrichit le suivi sans toucher au front.
 */
final readonly class ImportRun
{
    /**
     * @param  list<ImportStep>  $steps
     */
    public function __construct(
        public string $jobId,
        public int $startedAt,
        public array $steps,
        public int $budgetUsed,
        public ?ImportWait $wait,
    ) {}

    /**
     * @param  list<ImportStage>  $stages
     */
    public static function start(string $jobId, array $stages, int $startedAt): self
    {
        return new self(
            $jobId,
            $startedAt,
            array_map(ImportStep::pending(...), $stages),
            0,
            null,
        );
    }

    public function step(ImportStage $importStage): ImportStep
    {
        foreach ($this->steps as $step) {
            if ($step->stage === $importStage) {
                return $step;
            }
        }

        throw new \InvalidArgumentException('Stage outside this run: '.$importStage->value);
    }

    public function withStep(ImportStep $importStep): self
    {
        return new self(
            $this->jobId,
            $this->startedAt,
            array_map(
                static fn (ImportStep $existing): ImportStep => $existing->stage === $importStep->stage ? $importStep : $existing,
                $this->steps,
            ),
            $this->budgetUsed,
            $this->wait,
        );
    }

    public function waitingOn(?ImportWait $importWait): self
    {
        return new self($this->jobId, $this->startedAt, $this->steps, $this->budgetUsed, $importWait);
    }

    public function withBudgetUsed(int $budgetUsed): self
    {
        return new self($this->jobId, $this->startedAt, $this->steps, $budgetUsed, $this->wait);
    }

    /**
     * Un échec d'étape ne clôt pas l'import : il n'est visible qu'une fois toutes les
     * étapes terminées, faute de quoi le front cesserait de suivre un import qui tourne.
     */
    public function status(): ImportStepStatus
    {
        $terminal = array_filter($this->steps, static fn (ImportStep $importStep): bool => $importStep->isTerminal());

        if (count($terminal) === count($this->steps)) {
            return array_any($this->steps, static fn (ImportStep $importStep): bool => $importStep->status === ImportStepStatus::Failed)
                ? ImportStepStatus::Failed
                : ImportStepStatus::Completed;
        }

        // Un import retenu par le plafond horaire avant même sa première étape tourne
        // déjà : le dire en attente le ferait passer pour n'avoir jamais démarré.
        $untouched = $terminal === []
            && ! $this->currentStage() instanceof ImportStage
            && ! $this->wait instanceof ImportWait;

        return $untouched ? ImportStepStatus::Pending : ImportStepStatus::Running;
    }

    public function currentStage(): ?ImportStage
    {
        foreach ($this->steps as $step) {
            if ($step->status === ImportStepStatus::Running) {
                return $step->stage;
            }
        }

        return null;
    }

    /**
     * Chaque étape pèse autant que les autres. C'est grossier — la garde-robe dure plus
     * qu'un index de montures — mais honnête : pondérer demanderait des durées de
     * référence que rien ne garantit d'un patch à l'autre.
     */
    public function fraction(): float
    {
        if ($this->steps === []) {
            return 1.0;
        }

        $done = array_sum(array_map(static fn (ImportStep $importStep): float => $importStep->fraction(), $this->steps));

        return $done / count($this->steps);
    }

    public function elapsedSeconds(int $now): int
    {
        return max(0, $now - $this->startedAt);
    }

    /**
     * Temps restant estimé : la durée moyenne des étapes déjà faites, multipliée par le
     * nombre d'étapes restantes.
     *
     * L'estimation se rafraîchit à l'étape et pas à la seconde, et c'est délibéré. Sur
     * une chaîne aux étapes très inégales — vingt secondes pour le socle, deux minutes
     * pour les hauts faits — extrapoler à la seconde enfle tant qu'une étape longue
     * n'aboutit pas, puis tombe à zéro alors qu'il reste tout à faire : les deux se
     * lisent comme une panne. Une étape ignorée par la porte de build ne pèse pas dans
     * la moyenne, n'ayant rien coûté.
     */
    public function etaSeconds(): ?int
    {
        $measured = array_filter(
            $this->steps,
            static fn (ImportStep $importStep): bool => $importStep->isTerminal() && $importStep->durationMs > 0,
        );
        if ($measured === []) {
            return null;
        }

        $remaining = count(array_filter($this->steps, static fn (ImportStep $importStep): bool => ! $importStep->isTerminal()));
        if ($remaining === 0) {
            return 0;
        }

        $meanSeconds = array_sum(array_map(static fn (ImportStep $importStep): int => $importStep->durationMs, $measured))
            / count($measured) / 1000;

        return (int) round($meanSeconds * $remaining);
    }

    /**
     * Le rapport lisible : ce qui est en cours, ce qui est attendu, puis une ligne par
     * étape. C'est le texte que le panneau affiche et celui qu'on archive.
     */
    public function summary(int $now): string
    {
        $lines = [$this->headline($now), ...$this->contextLines($now), ''];

        foreach ($this->steps as $step) {
            $lines[] = $this->describeStep($step);
        }

        return implode(PHP_EOL, $lines);
    }

    private function headline(int $now): string
    {
        return match ($this->status()) {
            ImportStepStatus::Pending => 'Import en attente de démarrage.',
            ImportStepStatus::Completed => sprintf('Import terminé en %s.', $this->duration($this->elapsedSeconds($now) * 1000)),
            ImportStepStatus::Failed => sprintf('Import terminé en %s, avec des échecs.', $this->duration($this->elapsedSeconds($now) * 1000)),
            default => sprintf(
                'Import en cours — %s (%d %%).',
                $this->currentStage()?->label() ?? 'démarrage',
                (int) round($this->fraction() * 100),
            ),
        };
    }

    /**
     * @return list<string>
     */
    private function contextLines(int $now): array
    {
        $lines = [];

        if ($this->wait instanceof ImportWait) {
            $lines[] = 'En attente : '.$this->wait->describe().'.';
        }

        $eta = $this->etaSeconds();

        $lines[] = sprintf(
            'Budget horaire : %s %s consommés · écoulé %s%s',
            $this->count($this->budgetUsed),
            $this->plural($this->budgetUsed, 'appel', 'appels'),
            $this->duration($this->elapsedSeconds($now) * 1000),
            $eta === null || $eta === 0 ? '' : sprintf(' · reste ~%s', $this->duration($eta * 1000)),
        );

        return $lines;
    }

    private function describeStep(ImportStep $importStep): string
    {
        $label = $importStep->stage->label();

        return match ($importStep->status) {
            ImportStepStatus::Pending => sprintf('·  %s — à faire', $label),
            ImportStepStatus::Running => sprintf('⏳ %s — %d %%%s', $label, (int) round($importStep->fraction() * 100), $this->stepFacts($importStep)),
            ImportStepStatus::Completed => sprintf('✓  %s — %s', $label, ltrim($this->stepFacts($importStep), ' ·')),
            ImportStepStatus::Failed => sprintf('✗  %s — échec : %s', $label, $importStep->error ?? 'raison inconnue'),
            ImportStepStatus::Skipped => sprintf('⏭  %s — déjà à jour pour ce build', $label),
        };
    }

    /**
     * Les lignes ne sont rapportées que pour les étapes qui en comptent : le socle de
     * référence est chargé par `COPY` dans des tables sans horodatages, et trois zéros
     * s'y liraient « rien ne s'est passé » au lieu de « sans objet ».
     */
    private function stepFacts(ImportStep $importStep): string
    {
        $facts = [];

        if ($importStep->stage->tables() !== []) {
            $facts[] = sprintf(
                '%s %s, %s %s, %s %s',
                $this->count($importStep->rows->created),
                $this->plural($importStep->rows->created, 'créée', 'créées'),
                $this->count($importStep->rows->updated),
                $this->plural($importStep->rows->updated, 'mise à jour', 'mises à jour'),
                $this->count($importStep->rows->deleted),
                $this->plural($importStep->rows->deleted, 'supprimée', 'supprimées'),
            );
        }

        $facts[] = sprintf('%s %s', $this->count($importStep->apiCalls), $this->plural($importStep->apiCalls, 'appel', 'appels'));
        $facts[] = $this->duration($importStep->durationMs);

        return ' · '.implode(' · ', $facts);
    }

    private function count(int $value): string
    {
        return number_format($value, 0, ',', ' ');
    }

    private function plural(int $value, string $singular, string $plural): string
    {
        return $value > 1 ? $plural : $singular;
    }

    private function duration(int $milliseconds): string
    {
        $seconds = intdiv($milliseconds, 1000);

        if ($milliseconds < 10_000) {
            return number_format($milliseconds / 1000, 1, ',', ' ').' s';
        }

        if ($seconds < 60) {
            return $seconds.' s';
        }

        if ($seconds < 3600) {
            return sprintf('%d min %02d s', intdiv($seconds, 60), $seconds % 60);
        }

        return sprintf('%d h %02d min', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    /**
     * @return array{job_id: string, started_at: int, budget_used: int, wait: array{reason: string, seconds: int, count: int}|null, steps: list<array{stage: string, status: string, created: int, updated: int, deleted: int, api_calls: int, duration_ms: int, offset: int, total: int, error: string|null}>}
     */
    public function toArray(): array
    {
        return [
            'job_id' => $this->jobId,
            'started_at' => $this->startedAt,
            'budget_used' => $this->budgetUsed,
            'wait' => $this->wait?->toArray(),
            'steps' => array_map(static fn (ImportStep $importStep): array => $importStep->toArray(), $this->steps),
        ];
    }

    /**
     * @param  array{job_id: string, started_at: int, budget_used: int, wait: array{reason: string, seconds: int, count: int}|null, steps: list<array{stage: string, status: string, created: int, updated: int, deleted: int, api_calls: int, duration_ms: int, offset: int, total: int, error: string|null}>}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['job_id'],
            $payload['started_at'],
            array_map(ImportStep::fromArray(...), $payload['steps']),
            $payload['budget_used'],
            $payload['wait'] === null ? null : ImportWait::fromArray($payload['wait']),
        );
    }
}
