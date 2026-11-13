<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public const MAX_ADDRESSES = 10;

    public function index(Request $request)
    {
        return response()->json(
            $request->user()->addresses()->orderByDesc('is_default')->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $user = $request->user();

        try {
            $address = DB::transaction(function () use ($user, $data) {
                // قفل صف المستخدم حتى لا يتجاوز طلبان متزمنان حد العناوين.
                \App\Models\User::whereKey($user->id)->lockForUpdate()->first();

                if ($user->addresses()->count() >= self::MAX_ADDRESSES) {
                    throw new \DomainException(
                        __('وصلت للحد الأقصى من العناوين (:max)', [
                            'max' => self::MAX_ADDRESSES,
                        ])
                    );
                }

                // أول عنوان بيبقى الافتراضي تلقائي
                $makeDefault = !empty($data['is_default']) || !$user->addresses()->exists();

                if ($makeDefault) {
                    $user->addresses()->update(['is_default' => false]);
                }

                return $user->addresses()->create([...$data, 'is_default' => $makeDefault]);
            });
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($address, 201);
    }

    public function update(Request $request, $id)
    {
        $user    = $request->user();
        $address = $user->addresses()->findOrFail($id);
        $data    = $request->validate($this->rules(true));

        // مينفعش نشيل الافتراضي من غير ما نختار بديل (استخدم setDefault على عنوان تاني)
        if ($address->is_default) {
            unset($data['is_default']);
        }

        DB::transaction(function () use ($user, $address, $data) {
            if (!empty($data['is_default'])) {
                $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $address->update($data);
        });

        return response()->json($address->fresh());
    }

    public function setDefault(Request $request, $id)
    {
        $user    = $request->user();
        $address = $user->addresses()->findOrFail($id);

        DB::transaction(function () use ($user, $address) {
            $user->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return response()->json($address->fresh());
    }

    public function destroy(Request $request, $id)
    {
        $user    = $request->user();
        $address = $user->addresses()->findOrFail($id);

        DB::transaction(function () use ($user, $address) {
            $wasDefault = $address->is_default;
            $address->delete();

            // لو مسحنا الافتراضي، آخر عنوان بيبقى الافتراضي
            if ($wasDefault) {
                $user->addresses()->latest()->first()?->update(['is_default' => true]);
            }
        });

        return response()->json(['message' => 'تم حذف العنوان']);
    }

    protected function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return [
            'label'          => 'nullable|string|max:50',
            'recipient_name' => "{$required}|string|max:255",
            'phone'          => "{$required}|string|max:30",
            'city'           => "{$required}|string|max:100",
            'country'        => 'nullable|string|max:100',
            'address_line'   => "{$required}|string|max:500",
            'notes'          => 'nullable|string|max:500',
            'is_default'     => 'boolean',
        ];
    }
}
