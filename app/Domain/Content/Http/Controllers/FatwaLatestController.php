<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ContentListingService;
use Illuminate\Contracts\View\View;

/**
 * Replaces `fatawa/more.php` (`more-fatawa.htm`, `op=more_fatawa`) —
 * Roadmap task 6.1, increment 2. No `.htaccess` page parameter exists for
 * this URL (`.htaccess:309`) — legacy's own query is a flat `LIMIT 50`,
 * not paginated.
 */
class FatwaLatestController
{
    /**
     * **Confirmed sidebar bug, reproduced exactly, not fixed:** `more.php:82`
     * calls `mostdownload(0,0,$id)` where `$id` is never assigned
     * anywhere in `more.php`'s file scope — under PHP's undefined-variable
     * handling this evaluates as falsy/0, so none of `mostdownload()`'s
     * three branches (`topic_id`/`auther_id`/`channel`, all effectively
     * 0) ever fire, and the "الأكثر تحميلا" sidebar box renders **empty**
     * on this page. No sidebar data is queried or passed to the view here
     * — an empty box is the confirmed legacy behavior, not an omission.
     */
    public function index(ContentListingService $listing): View
    {
        $latestQuestions = $listing->fatwaLatestQuestions();

        return view('fatawa.latest', compact('latestQuestions'));
    }
}
