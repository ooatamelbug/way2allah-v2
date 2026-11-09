<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Contextual\ContextualControl;
use App\Domain\Content\Models\Author;

/**
 * OLD public admin parity — the Khotab author page (owner, 2026-10-21).
 *
 * `panel()` is OLD's «لوحة تحكم الداعية» (`author_cp.htm`) on
 * `khotab-video-{id}.htm` / `khotab-audio-{id}.htm`, in OLD's order and with
 * OLD's labels. Each link opens the Laravel counterpart of the OLD admin op's
 * page, which sends the admin back where OLD did — so they carry no return_to.
 * «صيانة العدادات» was a GET link in OLD; it changes data, so here it is a
 * CSRF-protected POST.
 */
final class KhotabAuthorControls
{
    /**
     * @param  string  $media  `video` | `audio` — the page the panel is on
     * @return list<ContextualControl>
     */
    public static function panel(Author $author, string $media): array
    {
        $id = ['author' => $author->id, 'media' => $media];

        return [
            ContextualControl::link('تعديل بيانات الداعية', 'fa-pencil-square-o', 'admin.khotab.authors.edit', $id, returns: false),
            ContextualControl::link('إضافة مجموعة', 'fa-folder', 'admin.khotab.authors.groups.create', $id, returns: false),
            ContextualControl::link('إضافة سلسلة', 'fa-folder-open-o', 'admin.khotab.authors.series.create', $id, returns: false),
            ContextualControl::link('إضافة مادة', 'fa-plus', 'admin.khotab.authors.items.create', $id, returns: false),
            ContextualControl::link('حذف الداعية', 'fa-trash', 'admin.khotab.authors.delete', $id, returns: false),
            ContextualControl::link('مواد لم تصنف', 'fa-list', 'admin.khotab.authors.items', $id, returns: false),
            ContextualControl::action('صيانة العدادات', 'fa-wrench', 'admin.khotab.authors.repair-counts', $id, 'POST'),
        ];
    }
}
