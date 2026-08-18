<?php

namespace App\Domain\Content\Mail;

use App\Domain\Content\Models\AnasheedItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Replaces `anasheed/functions.php`'s `anasheed_send_friend()` raw
 * `shams_mail_no_spam()` call — G-11-02 (Phase 1 audit).
 *
 * **Confirmed different from `FatwaFriendMail`'s own precedent, not
 * copied from it:** `shams_mail_no_spam()` (`anasheed/functions.php:942-949`)
 * sets `From`/`Reply-To`/`Return-Path` to the **submitting user's own**
 * name/email (`$sender_name`/`$sender`, i.e. `your_name`/`your_email`),
 * not a fixed site address — unlike `sendemail.php`'s hardcoded
 * `info@way2allah.com`. Reproduced here via `->from($yourEmail, $yourName)`.
 *
 * Subject (`:660`): `"{item title} - موقع الطريق الى الله"`, exactly.
 * Body (`:662-668`) is a verbatim translation of the legacy HTML string —
 * see `resources/views/emails/anasheed-friend.blade.php`.
 */
class AnasheedFriendMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly AnasheedItem $anasheedItem,
        public readonly string $friendName,
        public readonly string $yourName,
        public readonly string $yourEmail,
    ) {}

    public function build(): self
    {
        $subject = $this->anasheedItem->title.' - موقع الطريق الى الله';

        return $this->from($this->yourEmail, $this->yourName)
            ->subject($subject)
            ->view('emails.anasheed-friend')
            ->with([
                'anasheedItem' => $this->anasheedItem,
                'friendName' => $this->friendName,
                'yourName' => $this->yourName,
            ]);
    }
}
