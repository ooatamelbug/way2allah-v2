<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Content\Models\KhotabItem;
use Illuminate\Contracts\View\View;

/**
 * OLD Admin Migration Phase 2 — Khotab link reporting. Read-only.
 *
 * `lostLinks()` replaces the **listing half** of OLD's `KhotabLostedLink`
 * (`modules/Khotab/admin/index.php:3435-3515`): `nuke_islamic_khotab WHERE
 * down = 0 ORDER BY id ASC`, 50 per page, with the total as the headline —
 * OLD's own select list, order and page size. OLD's per-row repair form is
 * **not** reproduced: it posted `op=KhotabRepairLostedLink`, which has no
 * handler anywhere, with an undefined id (Pass 3D §2.2). Nor is OLD's
 * per-row edit-item link: item CRUD is externally owned (BC#6). OLD ran
 * three extra queries per row for author, group and series; these are
 * eager-loaded here.
 *
 * `item()` is the contextual link-health page for one item: its primary
 * link and each mirror, with the **existing** `LinkQualityStatsController`
 * recheck / fix-size actions offered in context. Those actions are reused
 * unchanged — their routes, permission (`khotab.repair`) and behavior are
 * not reopened. This class adds entry points only.
 *
 * Gate: `khotab.repair`. OLD's lost-link screen sat in the Khotab admin's
 * maintenance section ("الصيانة") and called no `GreenZone()`, so any
 * admin could reach it; `khotab.repair` ("صيانة الوصلات" — link
 * maintenance) is the existing Laravel permission for exactly this
 * capability, and gating it is the project's ratified hardening
 * (decision-log #10).
 */
class KhotabLinkReportController
{
    /** OLD: `LIMIT $Page * 50, 50`. */
    private const PER_PAGE = 50;

    public function lostLinks(): View
    {
        $items = KhotabItem::query()
            ->select(['id', 'title', 'author', 'link', 'ser_id', 'group_id', 'vedio'])
            ->where('down', 0)
            ->with(['authorModel:id,name,prename', 'group:id,title', 'series:id,title'])
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        return view('admin.link-reports.lost', ['items' => $items]);
    }

    public function item(KhotabItem $khotabItem): View
    {
        return view('admin.link-reports.item', [
            'item' => $khotabItem,
            'mirrors' => $khotabItem->mirrors()->orderBy('id')->get(),
        ]);
    }
}
