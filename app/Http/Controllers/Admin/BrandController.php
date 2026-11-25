<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BrandController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'search'    => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $brands = Brand::query()
            ->with('category')                                // ← جديد
            ->withCount('products')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return BrandResource::collection($brands);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id'    => 'nullable|integer|exists:categories,id',  // ← جديد
            'name'           => 'required|string|max:255',
            'name_en'        => 'nullable|string|max:255',
            'description'    => 'nullable|string',
            'description_en' => 'nullable|string',
            'logo'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'sort_order'     => 'nullable|integer',
            'is_active'      => 'boolean',
        ]);

        $data['slug'] = $this->uniqueSlug($data['name']);

        $logo = null;

        try {
            if ($request->hasFile('logo')) {
                $logo = $this->fileUploadService->upload($request->file('logo'), 'brands');
                $data['logo'] = $logo;
            } else {
                unset($data['logo']);
            }

            $brand = Brand::create($data);
        } catch (\Throwable $e) {
            $this->fileUploadService->delete($logo);
            throw $e;
        }

        AuditLog::record('brand.created', $brand, ['name' => $brand->name]);
        HomeController::flush();

        return (new BrandResource($brand->load('category')))->response()->setStatusCode(201);
    }

    public function show(Brand $brand)
    {
        return new BrandResource($brand->load('category')->loadCount('products'));
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'category_id'    => 'nullable|integer|exists:categories,id',  // ← جديد
            'name'           => 'sometimes|string|max:255',
            'name_en'        => 'nullable|string|max:255',
            'description'    => 'nullable|string',
            'description_en' => 'nullable|string',
            'logo'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'sort_order'     => 'nullable|integer',
            'is_active'      => 'boolean',
        ]);

        $oldLogo = $brand->logo;
        $newLogo = null;

        if ($request->hasFile('logo')) {
            $newLogo = $this->fileUploadService->upload($request->file('logo'), 'brands');
            $data['logo'] = $newLogo;
        } else {
            unset($data['logo']);
        }

        try {
            $brand->update($data);
        } catch (\Throwable $e) {
            $this->fileUploadService->delete($newLogo);
            throw $e;
        }

        if ($newLogo) {
            $this->fileUploadService->delete($oldLogo);
        }

        AuditLog::record('brand.updated', $brand, ['name' => $brand->name]);
        HomeController::flush();

        return new BrandResource($brand->fresh()->load('category'));
    }

    public function destroy(Brand $brand)
    {
        if ($brand->products()->exists()) {
            throw ValidationException::withMessages([
                'brand' => 'لا يمكن حذف البراند لأن فيه منتجات مرتبطة. انقل المنتجات أولاً أو عطّل البراند.',
            ]);
        }

        $brand->delete();

        AuditLog::record('brand.deleted', $brand, ['name' => $brand->name]);
        HomeController::flush();

        return response()->json(['message' => 'تم حذف البراند بنجاح']);
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'brand-' . Str::random(8);
        }

        $slug = $base;
        $i = 1;

        while (Brand::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
