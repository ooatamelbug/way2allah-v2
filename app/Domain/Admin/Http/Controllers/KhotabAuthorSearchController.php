<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Admin\Support\ArabicSearchText;
use App\Domain\Admin\Support\Constraints\KhotabMediaTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The additional-sheikh search of «إضافة مادة» (multi-author creation, 2026-11-25) —
 * `GET /admincp/khotab/author-search/{media}?q=&exclude=&page=`.
 *
 * Gated like the three forms: `khotab.additem` (route) in the page's section (`admin.khotab-media` reads
 * `{media}`; checked again here). Visible sheikhs only, never the primary, matched on the name with Arabic
 * spelling folding ({@see ArabicSearchText}), `admin_khotab.additional_authors.search_page_size` per page plus a
 * `more` flag. Only `id` and `name` are returned. Nothing here is trusted by the submission, which re-validates
 * every id under the author locks.
 */
final class KhotabAuthorSearchController
{
    public function __invoke(Request $request, string $media, AdminRecordAuthorizer $authorizer): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        $authorizer->authorize($admin, 'khotab.additem', KhotabMediaTarget::fromMedia($media));

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'exclude' => ['nullable', 'integer', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $query = ArabicSearchText::normalize($data['q'] ?? '');
        if (mb_strlen($query) < max(1, (int) config('admin_khotab.additional_authors.search_min_length', 2))) {
            return response()->json(['results' => [], 'more' => false]);
        }

        $size = max(1, (int) config('admin_khotab.additional_authors.search_page_size', 20));
        $page = (int) ($data['page'] ?? 1);
        [$name, $bindings] = ArabicSearchText::sqlExpression('name');

        $rows = DB::connection('main')->table('nuke_islamic_authors')
            ->where('hidden', 0)
            ->where('id', '<>', (int) ($data['exclude'] ?? 0))
            ->whereRaw("{$name} LIKE ? ESCAPE '".ArabicSearchText::LIKE_ESCAPE."'", [...$bindings, ArabicSearchText::containsPattern($query)])
            ->orderBy('name')->orderBy('id')
            ->offset(($page - 1) * $size)->limit($size + 1)
            ->get(['id', 'prename', 'name']);

        return response()->json([
            'results' => $rows->take($size)->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => trim(((string) $row->prename).' '.((string) $row->name)),
            ])->values(),
            'more' => $rows->count() > $size,
        ]);
    }
}
