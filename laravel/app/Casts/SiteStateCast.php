<?php

namespace App\Casts;

use Domain\SiteRegistry\SiteState;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class SiteStateCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value instanceof SiteState) {
            return $value;
        }

        return SiteState::tryFrom((string) $value) ?? SiteState::UNKNOWN;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value instanceof SiteState) {
            return $value->value;
        }

        return (string) $value;
    }
}
