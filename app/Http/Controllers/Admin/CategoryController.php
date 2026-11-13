<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
        ]);

        $categories = Category::with('parent')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('sort_order')
            ->paginate(20);

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'name_en'        => 'nullable|string|max:255',
            'description'    => 'nullable|string',
            'description_en' => 'nullable|string',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'parent_id'      => 'nullable|exists:categories,id',
            'sort_order'     => 'nullable|integer',
            'is_active'      => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);

        // منع slug فاضي (أسماء عربية فقط مثلاً)
        if ($data['slug'] === '') {
            $data['slug'] = 'cat-' . Str::random(8);
        }

        // تفرد الـ slug
        $base = $data['slug'];
        $i = 1;
        while (Category::withTrashed()->where('slug', $data['slug'])->exists()) {
            $data['slug'] = $base . '-' . $i++;
        }

        if ($request->hasFile('image')) {
            $data['image'] = $this->fileUploadService->upload(
                $request->file('image'),
                'categories'
            );
        }

        $category = Category::create($data);

        return new CategoryResource($category);
    }

    public function show(Category $category)
    {
        return response()->json($category->load('parent', 'children', 'products'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'name_en'        => 'nullable|string|max:255',
            'description'    => 'nullable|string',
            'description_en' => 'nullable|string',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'parent_id'      => [
                'nullable',
                'exists:categories,id',
                Rule::notIn([$category->id]),
            ],
            'sort_order'     => 'nullable|integer',
            'is_active'      => 'boolean',
        ]);

        // منع دورة في الشجرة (A→B→A)
        if (!empty($data['parent_id'])) {
            $this->assertNoCycle($category->id, (int) $data['parent_id']);
        }

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
            if ($data['slug'] === '') {
                $data['slug'] = 'cat-' . Str::random(8);
            }

            $base = $data['slug'];
            $i = 1;
            while (
                Category::withTrashed()
                    ->where('slug', $data['slug'])
                    ->where('id', '!=', $category->id)
                    ->exists()
            ) {
                $data['slug'] = $base . '-' . $i++;
            }
        }

        if ($request->hasFile('image')) {
            $this->fileUploadService->delete($category->image);
            $data['image'] = $this->fileUploadService->upload(
                $request->file('image'),
                'categories'
            );
        } else {
            unset($data['image']);
        }

        $category->update($data);

        return response()->json($category->fresh('parent'));
    }

    public function destroy(Category $category)
    {
        // منع الحذف لو فيه منتجات أو أقسام فرعية
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'لا يمكن حذف القسم لأن فيه منتجات مرتبطة. انقل المنتجات أولاً أو عطّل القسم.',
            ]);
        }

        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'لا يمكن حذف القسم لأن فيه أقسام فرعية. انقل أو احذف الأقسام الفرعية أولاً.',
            ]);
        }

        $category->delete();

        return response()->json(['message' => 'تم حذف القسم بنجاح']);
    }

    /**
     * يتأكد إن parent_id الجديد مش نسل من القسم الحالي (يمنع الدورة).
     */
    protected function assertNoCycle(int $categoryId, int $parentId): void
    {
        $current = Category::find($parentId);
        $guard = 0;

        while ($current && $guard < 50) {
            if ((int) $current->id === $categoryId) {
                throw ValidationException::withMessages([
                    'parent_id' => 'لا يمكن اختيار قسم فرعي كأب (دورة في الشجرة).',
                ]);
            }
            $current = $current->parent;
            $guard++;
        }
    }
}
