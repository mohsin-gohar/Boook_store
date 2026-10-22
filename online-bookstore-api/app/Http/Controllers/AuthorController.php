<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthorResource;
use App\Models\Author;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    /**
     * Display a listing of all authors.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Author::query()->withCount('books');

        // Search by name (case-insensitive).
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        $query->orderBy('name');

        $perPage = min((int) $request->input('per_page', 100), 100);
        $authors = $query->paginate($perPage);

        return ApiResponse::paginated(
            $authors,
            AuthorResource::collection($authors->items())->resolve(),
        );
    }

    /**
     * Display a single author.
     */
    public function show(int $author): JsonResponse
    {
        $model = Author::withCount('books')->find($author);

        if (! $model) {
            return ApiResponse::error('Author not found.', 404);
        }

        return ApiResponse::success(new AuthorResource($model));
    }
}
