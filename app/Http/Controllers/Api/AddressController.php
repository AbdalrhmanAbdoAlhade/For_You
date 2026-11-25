<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public const MAX_ADDRESSES = 10;

    public function index(Request $request)
    {
        return response()->json(
            $request->user()
                ->addresses()
                ->orderByDesc('is_default')
                ->latest()
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $user = $request->user();

        $data['address_line'] = $this->formatAddressLine($data);

        try {
            $address = DB::transaction(function () use ($user, $data) {
                User::whereKey($user->id)->lockForUpdate()->first();

                if ($user->addresses()->count() >= self::MAX_ADDRESSES) {
                    throw new \DomainException(
                        __('وصلت للحد الأقصى من العناوين (:max)', [
                            'max' => self::MAX_ADDRESSES,
                        ])
                    );
                }

                $makeDefault = !empty($data['is_default'])
                    || !$user->addresses()->exists();

                if ($makeDefault) {
                    $user->addresses()->update(['is_default' => false]);
                }

                return $user->addresses()->create([
                    ...$data,
                    'is_default' => $makeDefault,
                ]);
            });
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json($address, 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        $address = $user->addresses()->findOrFail($id);

        $data = $request->validate($this->rules(true));

        if ($address->is_default) {
            unset($data['is_default']);
        }

        $merged = array_merge($address->toArray(), $data);

        if (
            array_key_exists('street_address', $data)
            || array_key_exists('building', $data)
            || array_key_exists('floor', $data)
            || array_key_exists('apartment', $data)
            || array_key_exists('governorate', $data)
            || array_key_exists('city', $data)
        ) {
            $data['address_line'] = $this->formatAddressLine($merged);
        }

        DB::transaction(function () use ($user, $address, $data) {
            if (!empty($data['is_default'])) {
                $user->addresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $address->update($data);
        });

        return response()->json($address->fresh());
    }

    public function setDefault(Request $request, $id)
    {
        $user = $request->user();

        $address = $user->addresses()->findOrFail($id);

        DB::transaction(function () use ($user, $address) {
            $user->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return response()->json($address->fresh());
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $address = $user->addresses()->findOrFail($id);

        DB::transaction(function () use ($user, $address) {
            $wasDefault = $address->is_default;

            $address->delete();

            if ($wasDefault) {
                $user->addresses()->latest()->first()?->update([
                    'is_default' => true,
                ]);
            }
        });

        return response()->json([
            'message' => 'تم حذف العنوان',
        ]);
    }

    protected function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return [
            'label' => 'nullable|string|max:50',
            'recipient_name' => "{$required}|string|max:255",
            'phone' => "{$required}|string|max:30",

            'governorate' => 'nullable|string|max:100',
            'city' => $partial
                ? 'sometimes|required|string|max:100'
                : 'required|string|max:100',

            'country' => 'nullable|string|max:100',

            'address_line' => $partial
                ? 'sometimes|nullable|string|max:1000'
                : 'required_without:street_address|nullable|string|max:1000',

            'street_address' => $partial
                ? 'sometimes|nullable|string|max:500'
                : 'required_without:address_line|nullable|string|max:500',

            'building' => 'nullable|string|max:100',
            'floor' => 'nullable|string|max:50',
            'apartment' => 'nullable|string|max:50',

            'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90',
            'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',

            'notes' => 'nullable|string|max:500',
            'is_default' => 'sometimes|boolean',
        ];
    }

    protected function formatAddressLine(array $data): string
{
    $street = $data['street_address'] ?? null;

    // الحفاظ على العنوان القديم لو مفيش street_address جديد
    if (!is_string($street) || trim($street) === '') {
        $street = $data['address_line'] ?? null;
    }

    $parts = [
        $street,
        !empty($data['building']) ? 'مبنى ' . $data['building'] : null,
        !empty($data['floor']) ? 'الدور ' . $data['floor'] : null,
        !empty($data['apartment']) ? 'شقة ' . $data['apartment'] : null,
        $data['city'] ?? null,
        $data['governorate'] ?? null,
    ];

    return implode('، ', array_filter(
        array_map(
            fn ($part) => is_string($part) ? trim($part) : null,
            $parts
        ),
        fn ($part) => $part !== null && $part !== ''
    ));
}
}