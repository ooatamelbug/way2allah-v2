<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\MediaPlayerService;
use Illuminate\Http\Request;

/**
 * Replaces `ajax_3K2r.php?op=get-mada-player` (`get-mada-player.htm`) —
 * the shared, cross-module AJAX player endpoint `w2a_play()` (`scripts/
 * w2a_play.js`) posts to. One endpoint for every content type
 * `MediaPlayerService` supports, matching legacy's own single dispatcher
 * — "do not create separate duplicated endpoints for Khotab and Khotab
 * mirrors" (Batch 4 instructions). It now serves khotab, anasheed,
 * telawah, fatawa, and recorded chat-room lessons through their supported
 * service types.
 *
 * Public, unauthenticated — matches legacy's own `get_w2a_mada_player()`
 * exactly (no session/login/admin check there either). Missing/invalid
 * `id`/`type` returns an empty 200 body, same as legacy's own silent
 * `return;` (confirmed live, `get-mada-player.htm`'s Batch 4 investigation
 * response captures) — not a 404/422, deliberately matching the observed
 * legacy contract this endpoint's only real caller (`w2a_play.js`) relies
 * on (`dataType: 'html'`, no error-branch handling).
 */
class MediaPlayerController
{
    public function show(Request $request, MediaPlayerService $player): string
    {
        $type = (string) $request->input('type', '');
        $id = (int) $request->input('id', 0);

        if ($type === '' || $id <= 0) {
            return '';
        }

        return (string) $player->play($type, $id);
    }
}
