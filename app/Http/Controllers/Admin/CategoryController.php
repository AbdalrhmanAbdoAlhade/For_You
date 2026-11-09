<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\FileUploadService;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::with('parent')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('sort_order')
            ->paginate(20);

        return response()->json($categories);
    }
public function __construct(
    protected FileUploadService $fileUploadService
) {}


 public function store(Request $request)
{
    $data = $request->validate([
        'name'        => 'required|string|max:255',
        'description' => 'nullable|string',
        'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        'parent_id'   => 'nullable|exists:categories,id',
        'sort_order'  => 'nullable|integer',
        'is_active'   => 'boolean',
    ]);

    $data['slug'] = \Illuminate\Support\Str::slug($data['name']);

    if ($request->hasFile('image')) {
        $data['image'] = $this->fileUploadService->upload(
            $request->file('image'),
            'categories'
        );
    }

    $category = Category::create($data);

    return new \App\Http\Resources\CategoryResource($category);
}

    public function show(Category $category)
    {
        return response()->json($category->load('parent', 'children', 'products'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'boolean',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(['message' => 'تم حذف القسم بنجاح']);
    }
}
