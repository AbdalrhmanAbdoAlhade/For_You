<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * يبعت push notification لمجموعة tokens.
     * لو FCM مش مظبوط، بيرجع skipped=true مع warning log (مش صامت).
     *
     * @param array<string> $tokens
     * @param array{title:string, body:string, data?:array} $payload
     * @return array{success:int, failed:int, skipped:bool, error?:string}
     */
    public function send(array $tokens, array $payload): array
    {
        $tokens = array_values(array_filter($tokens));

        if (empty($tokens)) {
            return ['success' => 0, 'failed' => 0, 'skipped' => true, 'error' => 'no_tokens'];
        }

        $status = $this->configStatus();

        if (!$status['configured']) {
            Log::warning('FCM skipped: not configured', [
                'reason'       => $status['reason'],
                'token_count'  => count($tokens),
                'payload_type' => $payload['data']['type'] ?? null,
            ]);
            return ['success' => 0, 'failed' => 0, 'skipped' => true, 'error' => 'not_configured'];
        }

        try {
            $messaging = app(\Kreait\Firebase\Contract\Messaging::class);

            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(
                    \Kreait\Firebase\Messaging\Notification::create(
                        (string) ($payload['title'] ?? ''),
                        (string) ($payload['body'] ?? '')
                    )
                )
                ->withData($this->stringifyData($payload['data'] ?? []));

            // v6 uses sendMulticast(); v7+ recommends sendEachForMulticast()
            if (method_exists($messaging, 'sendMulticast')) {
                $report = $messaging->sendMulticast($message, $tokens);
            } elseif (method_exists($messaging, 'sendEachForMulticast')) {
                $report = $messaging->sendEachForMulticast($message, $tokens);
            } else {
                throw new \RuntimeException('Kreait Messaging: no compatible multicast method found');
            }

            $this->pruneInvalidTokens($report);

            return [
                'success' => $report->successes()->count(),
                'failed'  => $report->failures()->count(),
                'skipped' => false,
            ];
        } catch (\Throwable $e) {
            Log::error('FCM send failed', [
                'error'       => $e->getMessage(),
                'token_count' => count($tokens),
            ]);
            return ['success' => 0, 'failed' => count($tokens), 'skipped' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{configured:bool, reason:string}
     */
    public function configStatus(): array
    {
        if (!class_exists(\Kreait\Firebase\Contract\Messaging::class)) {
            return ['configured' => false, 'reason' => 'package_not_installed'];
        }

        $path = $this->credentialsPath();

        if ($path === null) {
            return ['configured' => false, 'reason' => 'credentials_not_set'];
        }

        if (!file_exists($path)) {
            return ['configured' => false, 'reason' => 'credentials_file_missing'];
        }

        return ['configured' => true, 'reason' => 'ok'];
    }

    public function isConfigured(): bool
    {
        return $this->configStatus()['configured'];
    }

    protected function credentialsPath(): ?string
    {
        $path = config('services.fcm.credentials_path');

        return $path && is_string($path) ? $path : null;
    }

    /** FCM data values لازم تكون string */
    protected function stringifyData(array $data): array
    {
        return array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $data);
    }

    protected function pruneInvalidTokens($report): void
    {
        $invalidTokens = [];

        foreach ($report->failures()->getItems() as $failure) {
            $error = $failure->error();
            if ($error && in_array($error->getMessage(), [
                'Requested entity was not found.',
                'NotRegistered',
                'InvalidRegistration',
            ], true)) {
                $invalidTokens[] = $failure->target()->value();
            }
        }

        if (!empty($invalidTokens)) {
            \App\Models\DeviceToken::whereIn('token', $invalidTokens)->delete();
            Log::info('FCM pruned invalid tokens', ['count' => count($invalidTokens)]);
        }
    }
}
