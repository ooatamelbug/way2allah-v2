<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\TelawahGroup;
use App\Domain\Content\Models\TelawahItem;
use Illuminate\Contracts\View\View;

/** Replaces `telawah/group.php` — Roadmap task 4.8. */
class TelawahGroupController
{
    public function show(int $group): View
    {
        $groupModel = TelawahGroup::findOrFail($group);

        $subGroups = TelawahGroup::where('parent_id', $group)->orderBy('id')->get(['id', 'title', 'hits', 'child', 'telawah', 'des']);

        $items = TelawahItem::where('group_id', $group)->orderBy('sorah')->get(['id', 'title', 'sorah']);

        return view('telawah.group', compact('groupModel', 'subGroups', 'items'));
    }
}
