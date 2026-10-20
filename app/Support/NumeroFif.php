<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Numéro de Fiche d'Identification Famille : <année><armée><région><rang>, ex. 26AT1R001.
 *
 *   26  = année d'enregistrement (2026)
 *   AT  = armée (Armée de Terre)
 *   1R  = région (1re Région Militaire)
 *   001 = rang d'enregistrement pour cette année, cette armée et cette région
 *
 * Les codes sont déduits des libellés de la structure organisationnelle (Paramètres).
 */
class NumeroFif
{
    public const RANK_DIGITS = 3;

    /** Code utilisé quand l'armée ou la région n'est pas renseignée. */
    public const UNKNOWN = 'X';

    private const STOP_WORDS = ['de', 'du', 'des', 'la', 'le', 'les', 'et', 'l', 'd', 'en', 'au', 'aux'];

    public static function prefix(int $year, ?string $armee, ?string $region): string
    {
        return sprintf('%02d', $year % 100).self::armeeCode($armee).self::regionCode($region);
    }

    public static function format(string $prefix, int $rank): string
    {
        return $prefix.str_pad((string) $rank, self::RANK_DIGITS, '0', STR_PAD_LEFT);
    }

    /** Rang contenu dans un numéro de ce préfixe, ou null s'il appartient à un autre préfixe. */
    public static function rank(string $prefix, string $numero): ?int
    {
        return preg_match('/^'.preg_quote($prefix, '/').'(\d{'.self::RANK_DIGITS.',})$/', $numero, $m) ? (int) $m[1] : null;
    }

    /** Armée de Terre → AT, Armée de l'Air → AA, Gendarmerie Nationale → GN. */
    public static function armeeCode(?string $armee): string
    {
        return self::initials($armee);
    }

    /**
     * 1re Région Militaire — Kaya → 1R ; 1re Région Aérienne → 1RA ; 6e Groupement d'Intervention Rapide (GIR) → 6GIR ;
     * Garde Républicaine (GR) — Ouagadougou → GR ; Groupement d'Artillerie — Kaya → GA.
     */
    public static function regionCode(?string $region): string
    {
        $region = trim((string) $region);
        if ($region === '') {
            return self::UNKNOWN;
        }

        // 1re Région Militaire → 1R ; 1re Région Aérienne → 1RA.
        if (preg_match('/^(\d+)\s*(?:re|er|e|ème|eme)?\s+r[ée]gion(\s+a[ée]rienne)?/iu', $region, $m)) {
            return $m[1].'R'.(empty($m[2]) ? '' : 'A');
        }

        $acronym = preg_match('/\(([A-Z]{2,6})\)/u', $region, $a) ? $a[1] : null;
        if ($acronym && preg_match('/^(\d+)\s*(?:re|er|e|ème|eme)?\s/iu', $region, $n)) {
            return $n[1].$acronym;
        }

        return $acronym ?? self::initials(preg_split('/\s+[—–-]\s+/u', $region)[0]);
    }

    private static function initials(?string $label): string
    {
        $label = trim((string) $label);
        if ($label === '') {
            return self::UNKNOWN;
        }

        // Un sigle déjà écrit en capitales (CECF) est gardé tel quel.
        if (preg_match('/^[A-Z]{2,6}$/', $label)) {
            return $label;
        }

        $words = preg_split("/[\s'’\/-]+/u", Str::ascii($label), -1, PREG_SPLIT_NO_EMPTY);
        $code = collect($words)
            ->reject(fn (string $word) => in_array(mb_strtolower($word), self::STOP_WORDS, true) || ! preg_match('/^[A-Za-z]/', $word))
            ->map(fn (string $word) => strtoupper($word[0]))
            ->take(4)
            ->implode('');

        return $code !== '' ? $code : self::UNKNOWN;
    }
}
