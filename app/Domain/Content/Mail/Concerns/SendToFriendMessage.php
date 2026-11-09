<?php

namespace App\Domain\Content\Mail\Concerns;

/**
 * Shared sender and link base for the three send-to-friend Mailables (#106,
 * owner-approved 2026-11-08):
 *
 *  - From is always the site address; the visitor's validated email (and name)
 *    goes into Reply-To. Visitor input never sets the From address or domain.
 *  - Absolute links in the mail are built from APP_URL (`config('app.url')`), never
 *    from the request's Host header or scheme (N3, scoped to these three mails).
 */
trait SendToFriendMessage
{
    public const SITE_SENDER = 'info@way2allah.com';

    protected function siteUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
