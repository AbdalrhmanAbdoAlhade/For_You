<?php

namespace App\Support;

class NotificationType
{
    // ===== الأنواع الموجودة =====
    public const ORDER_STATUS  = 'order_status';
    public const NEW_COMMISSION = 'new_commission';

    // ===== الأنواع الجديدة =====
    public const CREATOR_STORY = 'creator_story';
    public const NEW_OFFER     = 'new_offer';

    // ===== تجميع حسب الـ Tab في الـ UI =====
    public const TAB_ORDERS   = [self::ORDER_STATUS, self::NEW_COMMISSION];
    public const TAB_CREATORS = [self::CREATOR_STORY];
    public const TAB_OFFERS   = [self::NEW_OFFER];

    public static function forTab(string $tab): array
    {
        return match ($tab) {
            'orders'   => self::TAB_ORDERS,
            'creators' => self::TAB_CREATORS,
            'offers'   => self::TAB_OFFERS,
            default    => [],
        };
    }
}
