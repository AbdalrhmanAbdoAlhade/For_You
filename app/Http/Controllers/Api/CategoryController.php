<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::active()
            ->with(['children' => fn($q) => $q->active()])
            ->when($request->parent_id, fn($q) => $q->where('parent_id', $request->parent_id))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('sort_order')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function show($id)
    {
        $category = Category::active()
            ->with(['children', 'products' => fn($q) => $q->active()])
            ->findOrFail($id);

        return new CategoryResource($category);
    }
}
