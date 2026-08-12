<?php

namespace App\Domain\Content\Mail;

use App\Domain\Content\Models\FatwaQuestion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Replaces `fatawa/sendemail.php`'s raw PHP `mail()` call — Roadmap task
 * 6.1, increment 3. Per the approved technical plan (`fatawa.md` §9's
 * Candidate Package note): the mail *mechanism* is modernized to Laravel's
 * `Mail`/`Mailable` architecture, not ported as a raw `mail()` call with
 * hand-built headers — but the confirmed *content* (subject line
 * construction, body text, sender address) is preserved exactly.
 *
 * **`From: info@way2allah.com` is hardcoded, matching legacy exactly**
 * (`sendemail.php:60`) — not `config('mail.from.address')`'s configured
 * default, since the legacy behavior is this specific, confirmed address.
 *
 * **The email body (`resources/views/emails/fatawa-friend.blade.php`) is
 * a verbatim translation of `sendemail.php:63-92`'s HTML string,
 * including a stale link to `/chat`** (the retired live-room feature,
 * `chat_room` — Confirmation #4, no Zoom or any replacement). This is
 * preserved as legacy's own copy, not scrubbed or updated — rewriting
 * old email marketing copy is a content decision, not part of "port the
 * mail-sending behavior," and is flagged here rather than silently
 * changed or silently kept without comment.
 */
class FatwaFriendMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly FatwaQuestion $fatwaQuestion,
        public readonly string $friendName,
        public readonly string $yourName,
    ) {}

    public function build(): self
    {
        // sendemail.php:52 — subject built from the fatwa's own question
        // text and the answering author's name, exactly.
        $author = $this->fatwaQuestion->author;
        $subject = 'فتوى '.$this->fatwaQuestion->question_text.' '
            .($author?->prename).' : '.($author?->name)
            .'  من موقع الطريق الى الله ';

        return $this->from('info@way2allah.com')
            ->subject($subject)
            ->view('emails.fatawa-friend')
            ->with([
                'fatwaQuestion' => $this->fatwaQuestion,
                'friendName' => $this->friendName,
                'yourName' => $this->yourName,
            ]);
    }
}
