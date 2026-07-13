<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Workspace;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Category::class, $workspace]);

        $categories = $workspace->categories()->orderBy('position')->orderBy('name')->get();

        return inertia('Categories/Index', [
            'categories' => CategoryResource::collection($categories),
        ]);
    }

    public function create(Workspace $workspace): Response
    {
        $this->authorize('create', [Category::class, $workspace]);

        return inertia('Categories/Create');
    }

    public function store(StoreCategoryRequest $request, Workspace $workspace, CategoryService $categoryService): RedirectResponse
    {
        $this->authorize('create', [Category::class, $workspace]);

        $categoryService->create($workspace, $request->user(), $request->validated());

        return redirect()->route('categories.index', $workspace);
    }

    public function show(Workspace $workspace): RedirectResponse
    {
        return redirect()->route('categories.index', $workspace);
    }

    public function edit(Workspace $workspace, Category $category): Response
    {
        abort_if($category->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$category, $workspace]);

        return inertia('Categories/Edit', [
            'category' => new CategoryResource($category),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Workspace $workspace, Category $category, CategoryService $categoryService): RedirectResponse
    {
        abort_if($category->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$category, $workspace]);

        $categoryService->update($category, $request->validated());

        return redirect()->route('categories.index', $workspace);
    }

    public function destroy(Workspace $workspace, Category $category, CategoryService $categoryService): RedirectResponse
    {
        abort_if($category->workspace_id !== $workspace->id, 404);

        $this->authorize('delete', [$category, $workspace]);

        $categoryService->archive($category);

        return redirect()->route('categories.index', $workspace);
    }
}
