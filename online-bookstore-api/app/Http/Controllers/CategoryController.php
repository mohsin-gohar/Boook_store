<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of all categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::query()->withCount('books')->orderBy('name');

        $perPage = min((int) $request->input('per_page', 100), 100);
        $categories = $query->paginate($perPage);

        return ApiResponse::paginated(
            $categories,
            CategoryResource::collection($categories->items())->resolve(),
        );
    }

    /**
     * Display a single category.
     */
    public function show(int $category): JsonResponse
    {
        $model = Category::withCount('books')->find($category);

        if (! $model) {
            return ApiResponse::error('Category not found.', 404);
        }

        return ApiResponse::success(new CategoryResource($model));
    }
}
