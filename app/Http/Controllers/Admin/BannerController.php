<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Banner;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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
            'link_url'    => 'nullable|url:http,https|max:500',
            'product_id'  => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'boolean',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date',
            'title_en'       => 'nullable|string|max:255',
              'subtitle_en'    => 'nullable|string|max:255',                      
        ]);

        $this->assertDateRange($data['starts_at'] ?? null, $data['ends_at'] ?? null);

        $data['image'] = $this->fileUploadService->upload($request->file('image'), 'banners');

        $banner = Banner::create($data);

        AuditLog::record('banner.created', $banner, ['title' => $banner->title]);
        HomeController::flush();

        return response()->json($banner, 201);
    }

    public function update(Request $request, Banner $banner)
    {
        $data = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'subtitle'    => 'nullable|string|max:255',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'link_url'    => 'nullable|url:http,https|max:500',
            'product_id'  => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'boolean',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date',
               'title_en'       => 'nullable|string|max:255',
              'subtitle_en'    => 'nullable|string|max:255',                        
        ]);

        // مقارنة صريحة تدعم تحديث تاريخ النهاية فقط دون إرسال البداية.
        $this->assertDateRange(
            $data['starts_at'] ?? $banner->starts_at,
            $data['ends_at'] ?? $banner->ends_at
        );

        if ($request->hasFile('image')) {
            $this->fileUploadService->delete($banner->image);
            $data['image'] = $this->fileUploadService->upload($request->file('image'), 'banners');
        } else {
            unset($data['image']);
        }

        $banner->update($data);

        AuditLog::record('banner.updated', $banner, ['title' => $banner->title]);
        HomeController::flush();

        return response()->json($banner);
    }

    public function destroy(Banner $banner)
    {
        $this->fileUploadService->delete($banner->image);
        $banner->delete();

        AuditLog::record('banner.deleted', $banner, ['title' => $banner->title]);
        HomeController::flush();

        return response()->json(['message' => 'تم حذف البانر']);
    }

    /**
     * نهاية البانر لازم تكون بعد بدايته (مقارنة صريحة تدعم التحديث الجزئي).
     */
    protected function assertDateRange(mixed $startsAt, mixed $endsAt): void
    {
        if (empty($startsAt) || empty($endsAt)) {
            return;
        }

        if (Carbon::parse($endsAt)->lessThanOrEqualTo(Carbon::parse($startsAt))) {
            throw ValidationException::withMessages([
                'ends_at' => __('تاريخ الانتهاء يجب أن يكون بعد تاريخ البداية'),
            ]);
        }
    }
}
