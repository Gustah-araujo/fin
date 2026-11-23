<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FinRunStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $uuid
 * @property FinRunStatus $status
 * @property string|null $error_message
 * @property string|null $result_message_id
 * @property Carbon|null $created_at
 */
class FinRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'error_message' => $this->error_message,
            'result_message_id' => $this->result_message_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
