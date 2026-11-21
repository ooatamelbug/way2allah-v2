<?php

namespace App\Domain\Content\Ads;

use App\Support\Http\SafeExternalUrl;
use Illuminate\Support\Facades\DB;
use Random\Randomizer;

/**
 * F01 / B6c-1 — selection and safe presentation of one advertisement for one of OLD's twelve positions
 * (`show_ads_byposition()` + `switch_on_ads_type()`). Read-only: B6c-1 performs no view-counter, click-counter or
 * auto-hide write.
 *
 * Selection (parity): OLD picked `ORDER BY RAND() LIMIT 1` among `position = ? AND show = 1` and, when the pick was
 * ineligible, retried (recursively; ended ads were first hidden). The net effect is one uniformly random eligible
 * ad. Here: load the (tiny) pool once, apply {@see LegacyAdEligibility}, pick one uniformly. One ad per slot.
 *
 * Presentation (security, all stored values untrusted):
 * - type 0 (raw HTML/JS «جوجل أدسنس») is **withheld**: never output, never modified (plan §B6c-1 code-ad strategy);
 * - type 1: OLD's URL rewrites are applied (`/images/`→`/media/` and http→https when linked; http→https only for the
 *   popup), then the image URL and the link must both pass {@see SafeExternalUrl}; otherwise the ad is withheld.
 *   Values reach the markup only through Blade's escaping; nothing is placed in a script.
 *
 * Returns null — and runs **no query** — while `ads.rendering_enabled` is off, and for an unknown position.
 */
final class AdSlotService
{
    /** OLD's wrapper div per position (`switch_on_ads_type()`); 1–3 had none (the home page supplied it). */
    public const WRAPPERS = [
        1 => null, 2 => null, 3 => null,
        4 => 'ads_details',
        5 => 'ads_details_bottom', 6 => 'ads_details_bottom', 8 => 'ads_details_bottom',
        7 => 'ads_details_2', 9 => 'ads_details_2', 10 => 'ads_details_2',
        11 => 'ads_dialog',
    ];

    public const POPUP_POSITION = 12;

    public const TYPE_CODE = 0;

    public function __construct(private readonly Randomizer $randomizer) {}

    /**
     * @return array{position: int, ad_id: int, wrapper: string|null, popup: bool, src: string, href: string|null}|null
     */
    public function present(int $position, ?string $today = null): ?array
    {
        if (! AdsSwitch::renderingEnabled() || ! self::isKnownPosition($position)) {
            return null;
        }
        if ($position === self::POPUP_POSITION && ! AdsSwitch::popupEnabled()) {
            return null;
        }

        $ad = $this->select($position, $today);

        return $ad === null ? null : self::presentation($position, $ad);
    }

    /** One uniformly random eligible ad of the position (any type), or null. Read-only. */
    public function select(int $position, ?string $today = null): ?object
    {
        if (! AdsSwitch::renderingEnabled() || ! self::isKnownPosition($position)) {
            return null;
        }

        $today ??= now()->format('Y-m-d');
        $eligible = DB::connection('main')->table('nuke_ads')
            ->where('position', $position)->where('show', 1)->orderBy('id')
            ->get(['id', 'type', 'image_path', 'link', 'ads_show_type', 'startdate', 'enddate', 'num_view', 'required_num_view'])
            ->filter(fn (object $ad) => LegacyAdEligibility::isEligible($ad, $today))
            ->values();

        return $eligible->isEmpty() ? null : $eligible[$this->randomizer->getInt(0, $eligible->count() - 1)];
    }

    /**
     * The safe rendering of a selected ad, or null when it must be withheld (code ad, no image, unsafe URL).
     *
     * @return array{position: int, ad_id: int, wrapper: string|null, popup: bool, src: string, href: string|null}|null
     */
    public static function presentation(int $position, object $ad): ?array
    {
        if ((int) $ad->type === self::TYPE_CODE) {
            return null;
        }

        $image = (string) ($ad->image_path ?? '');
        $link = trim((string) ($ad->link ?? ''));
        if (trim($image) === '') {
            return null;
        }

        $popup = $position === self::POPUP_POSITION;
        if ($popup) {
            $image = str_replace('http://', 'https://', $image);
        } elseif ($link !== '') {
            $image = str_replace('http://', 'https://', str_replace('/images/', '/media/', $image));
        }

        $src = SafeExternalUrl::href($image);
        $href = $link === '' ? null : SafeExternalUrl::href($link);
        if ($src === null || ($link !== '' && $href === null)) {
            return null;
        }

        return [
            'position' => $position,
            'ad_id' => (int) $ad->id,
            'wrapper' => $popup ? null : self::WRAPPERS[$position],
            'popup' => $popup,
            'src' => $src,
            'href' => $href,
        ];
    }

    public static function isKnownPosition(int $position): bool
    {
        return $position >= 1 && $position <= self::POPUP_POSITION;
    }
}
