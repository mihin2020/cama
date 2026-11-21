<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Nom de tout fichier envoyé sur la plateforme (pièces des assurés comme fichiers du site public).
 *
 *   DOMAINE_PROPRIETAIRE_TYPE_AAAAMMJJ-HHMMSS_ALEA.ext
 *
 *   ASS_26AT1R001_INSCR-CNIB_20261121-143005_7F3A.pdf               pièce d'inscription d'un assuré
 *   ASS_26AT1R001-ENF01_ACTE-NAISSANCE_20261121-143200_B2D4.pdf     pièce du 1er enfant
 *   ASS_26AT1R001-CONJ01_PHOTO_20261121-143220_C3E5.jpg             photo du 1er conjoint
 *   CMS_ARTICLE-RENTREE-2026_IMAGE_20261121-090000_D4F6.webp        image d'une actualité
 *
 * Lisible (on retrouve le propriétaire et la nature du fichier d'un coup d'œil), triable par
 * propriétaire puis par date, en ASCII majuscule sans espace ni accent, et unique grâce à la date
 * à la seconde et à un suffixe aléatoire.
 */
final class UploadFilename
{
    public const ASSURE = 'ASS';

    public const CMS = 'CMS';

    private const SEGMENT_MAX = 48;

    /** Mots de liaison retirés des libellés (« Acte de naissance » → ACTE-NAISSANCE). */
    private const STOP_WORDS = ['DE', 'DU', 'DES', 'LA', 'LE', 'LES', 'ET', 'L', 'D', 'EN', 'AU', 'AUX', 'UN', 'UNE'];

    /**
     * @param  string  $domain  ASS (assurés) ou CMS (site public)
     * @param  string  $owner  à qui appartient le fichier (N° FIF, nom de l'article…)
     * @param  string  $type  nature du fichier (pièce, image, photo…)
     */
    public static function make(string $domain, string $owner, string $type, string $extension, ?CarbonInterface $at = null): string
    {
        $at ??= now();
        $extension = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $extension) ?? '');

        return implode('_', [
            self::segment($domain, 5),
            self::segment($owner),
            self::segment($type),
            $at->format('Ymd-His'),
            strtoupper(bin2hex(random_bytes(2))),
        ]).($extension !== '' ? ".{$extension}" : '');
    }

    /**
     * Nom unique dans un dossier : en cas (très improbable) de collision, un autre suffixe est tiré.
     *
     * @param  callable(string): bool  $exists  le nom existe-t-il déjà ?
     */
    public static function makeUnique(callable $exists, string $domain, string $owner, string $type, string $extension, ?CarbonInterface $at = null): string
    {
        do {
            $name = self::make($domain, $owner, $type, $extension, $at);
        } while ($exists($name));

        return $name;
    }

    /**
     * Segment de nom : ASCII majuscule, mots de liaison retirés, tirets à la place des espaces et
     * symboles. Un libellé trop long est coupé entre deux mots, jamais au milieu d'un mot.
     */
    public static function segment(string $value, int $max = self::SEGMENT_MAX): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', Str::upper(Str::ascii($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = array_values(array_filter($words, fn (string $word) => ! in_array($word, self::STOP_WORDS, true)));

        $slug = '';
        foreach ($kept ?: $words as $word) {
            $next = $slug === '' ? $word : "{$slug}-{$word}";
            if (strlen($next) > $max) {
                // Un seul mot plus long que la limite : on le tronque, faute de mieux.
                $slug = $slug === '' ? substr($word, 0, $max) : $slug;
                break;
            }
            $slug = $next;
        }

        return $slug !== '' ? $slug : 'X';
    }

    /** Libellé d'un propriétaire du site (titre d'article, nom de partenaire) : court, entre deux mots. */
    public static function label(string $value, int $max = 28): string
    {
        return self::segment($value, $max);
    }

    /** Code d'un membre de la famille : CONJ01, ENF02… (rang dans la liste, à partir de 1). */
    public static function memberCode(bool $conjoint, int $position): string
    {
        return ($conjoint ? 'CONJ' : 'ENF').str_pad((string) max(1, $position), 2, '0', STR_PAD_LEFT);
    }
}
