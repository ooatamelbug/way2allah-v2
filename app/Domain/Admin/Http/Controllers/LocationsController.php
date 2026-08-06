<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Models\Location;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Replaces `admincp/locations/*.php` — Roadmap task 5.4. `index`/`edit`
 * are confirmed working ports. `store()` is a real, working add-flow —
 * `locations/add.php`'s own INSERT is confirmed dead (`$w2adb->query($sql)`
 * commented out, `admincp.md` §5 Pattern D: the legacy page shows a false
 * "added successfully" confirmation with no row ever inserted) — rebuilt
 * here, not ported broken, per ADR-0010.
 *
 * The hardcoded Google Maps Geocoding API key found in `add.php`'s
 * `getaddress()` (Blueprint §16 item 9, "move to .env") is not
 * reproduced at all — that function's own client-side map-click-to-fill
 * convenience isn't part of the core add/edit capability, and the form
 * fields it would have populated (`address`, `country`, `lng`, `lat`) are
 * still real, directly-editable fields here either way.
 */
class LocationsController
{
    public function index(): View
    {
        $locations = Location::orderByRaw($this->titleOrderClause())->get();

        return view('admin.locations.index', compact('locations'));
    }

    /** `locations/index.php:22-30` — delete is blocked while `count` > 0 (referencing items exist). */
    public function destroy(Location $location): RedirectResponse
    {
        if ($location->count > 0) {
            return back()->with('error', "{$location->title} لم يتم حذفه لارتباطه بـ{$location->count} مادة");
        }

        $location->delete();

        return redirect()->route('admin.locations.index')->with('success', 'تم الحذف بنجاح');
    }

    public function create(): View
    {
        return view('admin.locations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Location::create($this->validated($request));

        return redirect()->route('admin.locations.index')->with('success', 'تمت الإضافة بنجاح');
    }

    public function edit(Location $location): View
    {
        return view('admin.locations.edit', compact('location'));
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $location->update($this->validated($request));

        return redirect()->route('admin.locations.index')->with('success', 'تم التعديل بنجاح');
    }

    private function validated(Request $request): array
    {
        return [
            'title' => $request->string('name'),
            'lng' => $request->input('loc_long'),
            'lat' => $request->input('loc_lat'),
            'des' => $request->string('comment'),
            'hidden' => $request->boolean('hidden') ? 1 : 0,
            'address' => $request->string('address'),
            'country' => $request->string('country'),
            'type' => $request->boolean('virtual') ? 2 : 1,
        ];
    }

    /**
     * `locations/index.php:72`'s `ORDER BY BINARY title ASC` forces
     * byte-exact (case/accent-sensitive) sorting. SQLite has no BINARY
     * keyword in this position (syntax error, not a no-op) — driver-aware
     * so the test suite can execute this query at all, same pattern as
     * `LiveStreamController::titleOrderClause()`. Not a behavior change in
     * production, the only environment where the difference is observable.
     * Previously untested via HTTP (no prior test exercised `index()` with
     * data present) — caught incidentally by a new Finding-2 test, not a
     * regression introduced by this change.
     */
    private function titleOrderClause(): string
    {
        return DB::connection('main')->getDriverName() === 'sqlite'
            ? 'title ASC'
            : 'BINARY title ASC';
    }
}
