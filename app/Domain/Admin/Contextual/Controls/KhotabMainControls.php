<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Contextual\ContextualControl;

/**
 * OLD public admin parity — Khotab Main (owner, 2026-10-21).
 *
 * `panel()` is OLD's `admincp.htm` on `khotab-video.htm` / `khotab-audio.htm`,
 * in OLD's order and with OLD's labels: «أضـــف شـــيـــخ», «إصــــلاح
 * الـــعــدادات» (a GET link in OLD; it rewrites every author's counters, so
 * here it is a CSRF-protected POST) and «تعليقات تنتظر النشر (N)», N being the
 * site-wide count of pending Khotab comments. OLD's «ملفات مفقودة»
 * (`KhotabListDown`) had no handler anywhere and is not offered.
 */
final class KhotabMainControls
{
    /**
     * @param  string  $media  `video` | `audio` — the page the panel is on
     * @return list<ContextualControl>
     */
    public static function panel(string $media, int $pendingComments): array
    {
        $id = ['media' => $media];

        return [
            ContextualControl::link('أضـــف شـــيـــخ', 'fa-user-plus', 'admin.khotab.main.authors.create', $id, returns: false),
            ContextualControl::action('إصــــلاح الـــعــدادات', 'fa-wrench', 'admin.khotab.main.repair-counts', $id, 'POST', [], 'إصلاح عدادات جميع الدعاة؟'),
            ContextualControl::link('تعليقات تنتظر النشر ('.$pendingComments.')', 'fa-comments', 'admin.khotab.main.comments', $id, returns: false),
        ];
    }
}
