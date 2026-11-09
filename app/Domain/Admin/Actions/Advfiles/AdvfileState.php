<?php

namespace App\Domain\Admin\Actions\Advfiles;

/**
 * Phase 5 — `nuke_islamic_khotab.advfile`, as OLD reads it.
 *
 * | value | meaning | OLD evidence |
 * |---|---|---|
 * | 0 | none — may be requested | RequestAdvFile `advfile > 0` refusal; gen 2 shows the button only at 0 |
 * | -1 | requested, awaiting approval | RequestAdvFile writes it; AdvfilesMain lists `advfile = -1` |
 * | > 0 | published (1 is what OLD writes) | ConfirmInsert writes 1; every public reader and InsertAdvFile test `advfile > 0` |
 *
 * One local row holds 2, which no code path writes. Every OLD reader treats
 * it as published (`> 0`), and so does this phase.
 */
final class AdvfileState
{
    public const NONE = 0;

    public const PENDING = -1;

    public const PUBLISHED = 1;

    public static function isPublished(int $state): bool
    {
        return $state > 0;
    }

    public static function label(int $state): string
    {
        return match (true) {
            $state > 0 => 'منشور في الملفات الدعائية',
            $state === self::PENDING => 'طلب بانتظار الموافقة',
            $state === self::NONE => 'غير مطلوب',
            default => 'حالة غير معروفة',
        };
    }
}
