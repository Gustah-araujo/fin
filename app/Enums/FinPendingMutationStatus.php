<?php

declare(strict_types=1);

namespace App\Enums;

enum FinPendingMutationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
