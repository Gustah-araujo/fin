<?php

// PHPStan stub: the vendor Laravel AI SDK does not document the morph
// columns used by the Conversation model. This stub redeclares the class
// with @property annotations so polymorphic access is type-safe.
// Update this file when upgrading laravel/ai.
//
// Keep this stub declaration-only: method bodies referencing vendor
// classes crash PHPStan's stub validator.

namespace Laravel\Ai\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $participant_id
 * @property string|null $participant_type
 */
#[WithoutIncrementing]
class Conversation extends Model {}
