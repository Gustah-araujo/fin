<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Models\Workspace;
use App\Services\TagService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Tag::class, $workspace]);

        $tags = $workspace->tags()->orderBy('name')->get();

        return inertia('Tags/Index', [
            'tags' => TagResource::collection($tags),
        ]);
    }

    public function create(Workspace $workspace): Response
    {
        $this->authorize('create', [Tag::class, $workspace]);

        return inertia('Tags/Create');
    }

    public function store(StoreTagRequest $request, Workspace $workspace, TagService $tagService): RedirectResponse
    {
        $this->authorize('create', [Tag::class, $workspace]);

        $tagService->create($workspace, $request->user(), $request->validated());

        return redirect()->route('tags.index', $workspace);
    }

    public function show(Workspace $workspace): RedirectResponse
    {
        return redirect()->route('tags.index', $workspace);
    }

    public function edit(Workspace $workspace, Tag $tag): Response
    {
        abort_if($tag->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$tag, $workspace]);

        return inertia('Tags/Edit', [
            'tag' => new TagResource($tag),
        ]);
    }

    public function update(UpdateTagRequest $request, Workspace $workspace, Tag $tag, TagService $tagService): RedirectResponse
    {
        abort_if($tag->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$tag, $workspace]);

        $tagService->update($tag, $request->validated());

        return redirect()->route('tags.index', $workspace);
    }

    public function destroy(Workspace $workspace, Tag $tag, TagService $tagService): RedirectResponse
    {
        abort_if($tag->workspace_id !== $workspace->id, 404);

        $this->authorize('delete', [$tag, $workspace]);

        $tagService->archive($tag);

        return redirect()->route('tags.index', $workspace);
    }
}
