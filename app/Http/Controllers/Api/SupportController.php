<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SupportController extends Controller
{
    /** GET /api/support/contact */
    public function contact(): JsonResponse
    {
        $contact = config('support.contact', []);

        // فلتر social_links: نشيل أي null/فاضي
        $socialLinks = [];
        foreach (($contact['social_links'] ?? []) as $network => $url) {
            if ($url !== null && $url !== '') {
                $socialLinks[$network] = $url;
            }
        }

        return response()->json([
            'phone'         => $contact['phone']         ?? null,
            'whatsapp'      => $contact['whatsapp']      ?? null,
            'email'         => $contact['email']         ?? null,
            'address'       => $contact['address']       ?? null,
            'working_hours' => $contact['working_hours'] ?? null,
            'social_links'  => $socialLinks,
        ]);
    }
}
