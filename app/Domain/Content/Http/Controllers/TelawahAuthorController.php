<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\TelawahGroup;
use Illuminate\Contracts\View\View;

/** Replaces `telawah/authors.php` — Roadmap task 4.8. The top-level reciter directory (parent_id=0 groups). */
class TelawahAuthorController
{
    public function index(): View
    {
        $groups = TelawahGroup::where('parent_id', 0)->orderBy('id')->get(['id', 'title', 'hits', 'child', 'telawah', 'des']);

        return view('telawah.authors', compact('groups'));
    }
}
