<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

trait HandlesIdempotency
{
    /**
     * ينفّذ $callback مرة واحدة لكل Idempotency-Key.
     * $callback لازم يرجّع JsonResponse. الاستجابة الناجحة (201) بس هي اللي بتتخزن.
     *
     * @param bool $required لو true والهيدر ناقص يرجع 422
     */
    protected function idempotent(
        Request $request,
        string $scope,
        Closure $callback,
        bool $required = false
    ): JsonResponse {
        $key = $request->header('Idempotency-Key');

        if (!$key) {
            if ($required) {
                return response()->json(['message' => 'الهيدر Idempotency-Key مطلوب'], 422);
            }

            return $callback();
        }

        if (strlen($key) > 255) {
            return response()->json(['message' => 'Idempotency-Key طويل جدًا'], 422);
        }

        $cacheKey    = 'idem:' . $scope . ':' . $request->user()->id . ':' . hash('sha256', $key);
        $fingerprint = hash('sha256', json_encode($request->all()));

        if ($cached = Cache::get($cacheKey)) {
            return $this->replayIdempotent($cached, $fingerprint);
        }

        $lock = Cache::lock($cacheKey . ':lock', 30);

        if (!$lock->get()) {
            return response()->json(['message' => 'الطلب قيد المعالجة، حاول بعد ثوانٍ'], 409);
        }

        try {
            // مراجعة تانية بعد القفل: ممكن طلب تاني يكون خلص في الفاصل ده
            if ($cached = Cache::get($cacheKey)) {
                return $this->replayIdempotent($cached, $fingerprint);
            }

            $response = $callback();

            if ($response->getStatusCode() === 201) {
                Cache::put($cacheKey, [
                    'fingerprint' => $fingerprint,
                    'status'      => 201,
                    'body'        => $response->getData(true),
                ], now()->addDays(7));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function replayIdempotent(array $cached, string $fingerprint): JsonResponse
    {
        if (!hash_equals($cached['fingerprint'], $fingerprint)) {
            return response()->json(
                ['message' => 'نفس الـ Idempotency-Key اتستخدم مع بيانات مختلفة'],
                422
            );
        }

        return response()
            ->json($cached['body'], $cached['status'])
            ->header('Idempotent-Replayed', 'true');
    }
}
