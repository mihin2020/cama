<?php

namespace App\Support;

/** Motifs LIKE construits à partir d'une saisie : « % » et « _ » y sont pris littéralement. */
class SqlLike
{
    public static function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    public static function contains(string $search): string
    {
        return '%'.self::escape($search).'%';
    }
}
