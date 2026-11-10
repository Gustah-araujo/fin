<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Workspace;
use Carbon\Carbon;

class DuplicateDetectionService
{
    private const VALUE_TOLERANCE = 0.01;

    private const DATE_TOLERANCE_DAYS = 1;

    private const SIMILARITY_THRESHOLD = 80.0;

    public function findDuplicate(Workspace $workspace, TransactionType $type, float $value, Carbon $date, string $description): ?Transaction
    {
        $candidates = Transaction::query()
            ->where('workspace_id', $workspace->id)
            ->where('type', $type->value)
            ->whereBetween('value', [$value - self::VALUE_TOLERANCE, $value + self::VALUE_TOLERANCE])
            ->whereBetween('date', [
                $date->copy()->subDays(self::DATE_TOLERANCE_DAYS)->toDateString(),
                $date->copy()->addDays(self::DATE_TOLERANCE_DAYS)->toDateString(),
            ])
            ->get();

        foreach ($candidates as $candidate) {
            similar_text(mb_strtolower((string) $candidate->description), mb_strtolower($description), $percent);

            if ($percent >= self::SIMILARITY_THRESHOLD) {
                return $candidate;
            }
        }

        return null;
    }
}
