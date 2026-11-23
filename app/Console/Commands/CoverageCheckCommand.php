<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Coverage\CloverCoverageParser;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;

class CoverageCheckCommand extends Command
{
    protected $signature = 'coverage:check
        {--file=coverage/clover.xml : Path to the Clover XML report}
        {--baseline=coverage-baseline.json : Path to the JSON baseline file}
        {--update : Update the baseline to the current coverage instead of checking}';

    protected $description = 'Check code coverage against the ratchet baseline (fails when coverage decreases)';

    public function handle(CloverCoverageParser $parser): int
    {
        $current = $this->parseCoverage($parser);

        if ($current === null) {
            return self::FAILURE;
        }

        $baselinePath = (string) $this->option('baseline');

        if ((bool) $this->option('update')) {
            return $this->updateBaseline($baselinePath, $current);
        }

        return $this->checkBaseline($baselinePath, $current);
    }

    private function parseCoverage(CloverCoverageParser $parser): ?float
    {
        $path = (string) $this->option('file');

        try {
            return $parser->parse($path);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return null;
        }
    }

    private function updateBaseline(string $baselinePath, float $current): int
    {
        $directory = dirname($baselinePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $payload = json_encode(['lines_percent' => $current], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        file_put_contents($baselinePath, $payload);

        $this->info("Coverage baseline updated to {$current}%");

        return self::SUCCESS;
    }

    private function checkBaseline(string $baselinePath, float $current): int
    {
        $baselinePercent = $this->readBaseline($baselinePath);

        if ($baselinePercent === null) {
            $this->error("Coverage baseline file is missing or invalid: {$baselinePath}");

            return self::FAILURE;
        }

        if ($current + 0.005 >= $baselinePercent) {
            $this->info("Coverage check passed: {$current}% (baseline: {$baselinePercent}%)");

            return self::SUCCESS;
        }

        $delta = round($current - $baselinePercent, 2);

        $this->error("Coverage decreased: {$current}% (baseline: {$baselinePercent}%, delta: {$delta}pp)");

        return self::FAILURE;
    }

    private function readBaseline(string $baselinePath): ?float
    {
        if (! is_file($baselinePath)) {
            return null;
        }

        $contents = file_get_contents($baselinePath);

        if ($contents === false) {
            return null;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $linesPercent = $decoded['lines_percent'] ?? null;

        if (! is_numeric($linesPercent)) {
            return null;
        }

        return (float) $linesPercent;
    }
}
