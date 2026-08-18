<?php

namespace App\Domain\Content\Mail;

use App\Domain\Content\Models\KhotabItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Replaces `khotab/functions.php`'s `khotab_send_friend()` raw
 * `shams_mail_no_spam()` call — visual parity audit (khotab-item-298784.htm)
 * Batch 3 / Finding #11. Same underlying legacy helper
 * (`shams_mail_no_spam()`, `functions.php:942-981`) as `AnasheedFriendMail`,
 * confirmed identical `From`/`Reply-To`/`Return-Path` = submitter's own
 * name/email behavior — reproduced the same way, via `->from()`.
 *
 * Subject (`khotab/functions.php:1214`): `"{item title} - موقع الطريق الى
 * الله"`, exactly — matching `AnasheedFriendMail`'s own subject format
 * byte-for-byte (both modules hardcode the same site name).
 *
 * Body (`:1216-1221`) is a verbatim translation of the legacy HTML string
 * — EXCEPT the item link. Legacy's own `khotab_send_friend()` links to
 * `$siteurl.'var-item-'.$Khotab->id.'.htm'` — anasheed's own URL pattern,
 * not khotab's (`khotab-item-{id}.htm`). Confirmed via `AnasheedFriendMail`'s
 * own email view, which correctly uses `var-item-` for an *actual*
 * anasheed item: `khotab_send_friend()` was evidently copy-pasted from
 * `anasheed_send_friend()` and the URL segment never updated. A
 * share-with-a-friend email whose link points at an unrelated content
 * type defeats the feature's purpose — not reproduced, same "don't
 * reproduce legacy bugs" standard already applied to the malformed
 * page-title icon and the audio breadcrumb label. Uses khotab's own
 * `/khotab-item-{id}.htm` instead.
 */
class KhotabFriendMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly KhotabItem $khotabItem,
        public readonly string $friendName,
        public readonly string $yourName,
        public readonly string $yourEmail,
    ) {}

    public function build(): self
    {
        $subject = $this->khotabItem->title.' - موقع الطريق الى الله';

        return $this->from($this->yourEmail, $this->yourName)
            ->subject($subject)
            ->view('emails.khotab-friend')
            ->with([
                'khotabItem' => $this->khotabItem,
                'friendName' => $this->friendName,
                'yourName' => $this->yourName,
            ]);
    }
}
