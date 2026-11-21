<?php

namespace App\Domain\Admin\Actions\Ads;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\LegacyAds;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * F01 / B6a — shared rules for writing `nuke_ads` the way OLD did.
 *
 * Column semantics follow OLD `add_ads()` / `edit_ads()` (`modules/ads/admin/index.php:324-570`):
 * - type 1 (image): `image_path` = the uploaded file's URL (`path_type` 1) or the typed URL (`path_type` 2);
 * - type 0 (code): `image_path` = the raw code, `path_type` = 2 (the value OLD's hidden select posted);
 * - `period` writes `startdate`/`enddate` only, `numview` writes `required_num_view` only (the other side is kept);
 * - `percentage`: 0 on create (OLD's form field was commented out → `intval('')`). On EDIT it is left unchanged —
 *   OLD reset it to 0 as a side effect of the missing field; B6a does not rewrite existing values.
 * - `num_view` / `num_click` are never written by administration.
 */
final class AdRecord
{
    /** Columns compared for "changed" and recorded in audit snapshots. */
    public const COLUMNS = ['name', 'image_path', 'position', 'percentage', 'type', 'ads_show_type', 'required_num_view',
        'show', 'link', 'startdate', 'enddate', 'num_view', 'num_click', 'path_type'];

    /** Raw HTML/JS ads (type 0) may only be created, edited, converted, enabled/disabled or deleted by a super-admin (owner, B6a). */
    public static function authorizeCode(AdminUser $actor, int ...$types): void
    {
        if (in_array(LegacyAds::TYPE_CODE, $types, true) && ! $actor->hasRole('super-admin')) {
            throw new AuthorizationException('Raw HTML/JavaScript advertisements are super-admin only.');
        }
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     * @return array<string, mixed>
     */
    public static function valuesForCreate(array $data, ?string $uploadedUrl): array
    {
        $values = self::common($data) + ['percentage' => 0];

        return $values + self::media($data, $uploadedUrl, null) + self::schedule($data);
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     * @return array<string, mixed> only the columns OLD's edit wrote
     */
    public static function valuesForUpdate(array $data, ?string $uploadedUrl, object $existing): array
    {
        return self::common($data) + self::media($data, $uploadedUrl, $existing) + self::schedule($data);
    }

    /**
     * Audit-safe snapshot: the raw code of a type-0 ad is never copied into the audit log (fingerprint + length
     * instead), and any other value carrying markup is redacted — the audit recorder refuses markup.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(object $row): array
    {
        $out = [];
        foreach (self::COLUMNS as $column) {
            $value = $row->{$column} ?? null;

            if ($column === 'image_path' && (int) ($row->type ?? 1) === LegacyAds::TYPE_CODE) {
                $out[$column] = self::fingerprint((string) $value);

                continue;
            }

            $out[$column] = is_string($value) && preg_match('/<[a-z!\/][^>]*>/i', $value) === 1
                ? ['redacted' => 'markup', 'length' => mb_strlen($value)]
                : $value;
        }

        return $out;
    }

    /** @return array{code_sha256: string, length: int} */
    public static function fingerprint(string $code): array
    {
        return ['code_sha256' => hash('sha256', $code), 'length' => mb_strlen($code)];
    }

    /** @return array<string, mixed> */
    private static function common(array $data): array
    {
        return [
            'name' => trim((string) $data['name']),
            'type' => (int) $data['type'],
            'position' => (int) $data['position'],
            'show' => (int) ($data['show'] ?? 0) === 1 ? 1 : 0,
            'ads_show_type' => (string) $data['ads_show_type'],
            'link' => trim((string) ($data['link'] ?? '')),
        ];
    }

    /** @return array<string, mixed> */
    private static function media(array $data, ?string $uploadedUrl, ?object $existing): array
    {
        if ((int) $data['type'] === LegacyAds::TYPE_CODE) {
            return ['image_path' => (string) $data['google_code'], 'path_type' => LegacyAds::PATH_URL];
        }

        if ((int) $data['path_type'] === LegacyAds::PATH_UPLOAD) {
            if ($uploadedUrl !== null) {
                return ['image_path' => $uploadedUrl, 'path_type' => LegacyAds::PATH_UPLOAD];
            }

            // Edit without a new file: keep the current upload (OLD: empty upload + empty URL → image untouched).
            return $existing !== null ? [] : ['image_path' => '', 'path_type' => LegacyAds::PATH_UPLOAD];
        }

        return ['image_path' => trim((string) $data['image_url']), 'path_type' => LegacyAds::PATH_URL];
    }

    /** @return array<string, mixed> */
    private static function schedule(array $data): array
    {
        return (string) $data['ads_show_type'] === 'period'
            ? ['startdate' => (string) $data['startdate'], 'enddate' => (string) $data['enddate']]
            : ['required_num_view' => (int) $data['required_num_view']];
    }
}
