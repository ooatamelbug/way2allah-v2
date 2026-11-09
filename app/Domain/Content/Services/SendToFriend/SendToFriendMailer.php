<?php

namespace App\Domain\Content\Services\SendToFriend;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Sends one send-to-friend mail (#106, 2026-11-08). A mail-transport failure is
 * caught and reported as false, so the endpoints answer with their controlled
 * failure response instead of an uncontrolled 500. Only transport exceptions are
 * caught — programming errors still surface. The log entry names the module, the
 * item id and the exception class only: no addresses, message content, transport
 * message (which can echo addresses or server responses) or credentials.
 */
final class SendToFriendMailer
{
    public function send(string $recipientEmail, Mailable $mail, string $module, int $itemId): bool
    {
        try {
            Mail::to($recipientEmail)->send($mail);

            return true;
        } catch (TransportExceptionInterface $e) {
            Log::warning('send-to-friend: mail transport failure', [
                'module' => $module,
                'item_id' => $itemId,
                'exception' => $e::class,
            ]);

            return false;
        }
    }
}
