<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'is_active' => 'nullable|boolean',
        ]);

        $banners = Banner::when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->paginate(20);

        return response()->json($banners);
    }

    public function show(Banner $banner)
    {
        return response()->json($banner);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'subtitle'    => 'nullable|string|max:255',
            'image'       => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'link_url'    => 'nullable|url|max:500',
            'product_id'  => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'boolean',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date|after_or_equal:starts_at',
            'title_en'       => 'nullable|string|max:255',
              'subtitle_en'    => 'nullable|string|max:255',                      
        ]);

        $data['image'] = $this->fileUploadService->upload($request->file('image'), 'banners');

        return response()->json(Banner::create($data), 201);
    }

    public function update(Request $request, Banner $banner)
    {
        $data = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'subtitle'    => 'nullable|string|max:255',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'link_url'    => 'nullable|url|max:500',
            'product_id'  => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'boolean',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date|after_or_equal:starts_at',
               'title_en'       => 'nullable|string|max:255',
              'subtitle_en'    => 'nullable|string|max:255',                        
        ]);

        if ($request->hasFile('image')) {
            $this->fileUploadService->delete($banner->image);
            $data['image'] = $this->fileUploadService->upload($request->file('image'), 'banners');
        } else {
            unset($data['image']);
        }

        $banner->update($data);

        return response()->json($banner);
    }

    public function destroy(Banner $banner)
    {
        $this->fileUploadService->delete($banner->image);
        $banner->delete();

        return response()->json(['message' => 'تم حذف البانر']);
    }
}
