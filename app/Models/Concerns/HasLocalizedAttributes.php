<?php

namespace App\Models\Concerns;

trait HasLocalizedAttributes
{
    public function localized(string $attribute): ?string
    {
        if (app()->getLocale() === 'en') {
            $en = $this->getAttribute($attribute . '_en');
            if (filled($en)) {
                return $en;
            }
        }

        return $this->getAttribute($attribute);
    }
}
