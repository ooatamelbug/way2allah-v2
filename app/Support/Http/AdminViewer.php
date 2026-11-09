<?php

namespace App\Support\Http;

/**
 * The per-request answer to "is an administrator viewing this public
 * response?" — for code outside the Admin domain (Content controllers,
 * download telemetry) that must not depend on Admin's internals
 * (`DomainBoundaryTest`, Blueprint v1.0 §2).
 *
 * Implemented by `App\Domain\Admin\Contextual\ContextualAdmin`, and bound to
 * the very same per-request instance: `markRendered()` here sets the state
 * that `KeepContextualPagesPrivate` reads to send `no-store, private`.
 */
interface AdminViewer
{
    /** True when a signed-in administrator is viewing; false for a visitor, without touching the database. */
    public function active(): bool;

    /** The viewing administrator's id, or null for a visitor. */
    public function adminId(): ?int;

    /** Keep this response out of shared caches: it shows what only an administrator may see. */
    public function markRendered(): void;
}
