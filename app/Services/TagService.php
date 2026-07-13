<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TagService
{
    public function create(Workspace $workspace, User $creator, array $data): Tag
    {
        $this->ensureUniqueName($workspace, $data['name']);

        return Tag::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'name' => $data['name'],
            'color' => $this->normalizeColor($data['color']),
        ]);
    }

    public function update(Tag $tag, array $data): Tag
    {
        if (isset($data['name']) && $data['name'] !== $tag->name) {
            $this->ensureUniqueName($tag->workspace, $data['name'], $tag->id);
            $tag->name = $data['name'];
        }
        if (isset($data['color'])) {
            $tag->color = $this->normalizeColor($data['color']);
        }

        $tag->save();

        return $tag;
    }

    public function archive(Tag $tag): void
    {
        $tag->delete();
    }

    private function ensureUniqueName(Workspace $workspace, string $name, ?int $excludeId = null): void
    {
        $query = Tag::withTrashed()->where('workspace_id', $workspace->id)->where('name', $name);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => ['Já existe uma tag com esse nome.'],
            ]);
        }
    }

    private function normalizeColor(string $color): string
    {
        $color = trim($color);

        if (! str_starts_with($color, '#')) {
            $color = '#' . $color;
        }

        return strtoupper($color);
    }
}
