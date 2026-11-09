<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\AnasheedMirror;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * OLD Admin Migration Phase 2 — resolves where each audit entry's entity
 * can be opened, and whether it still exists, for one page of entries.
 *
 * Batched: one `whereIn` query per (module, entity type) pair that actually
 * appears on the page — at most four today — never one query per row.
 *
 * Only the four pairs Phase 1 writes are known. Any other pair renders as
 * plain text with no link and no existence claim, rather than guessing a
 * route for a module this class has never been told about.
 */
final class AuditEntityLinks
{
    /** @var array<string, array{0: class-string<Model>, 1: string}> */
    private const KNOWN = [
        'khotab|mirror' => [Mirror::class, 'admin.mirrors.khotab.edit'],
        'khotab|item' => [KhotabItem::class, 'admin.mirrors.khotab.show'],
        'anasheed|mirror' => [AnasheedMirror::class, 'admin.mirrors.anasheed.edit'],
        'anasheed|item' => [AnasheedItem::class, 'admin.mirrors.anasheed.show'],
    ];

    /**
     * @param  Collection<int, AdminAuditEntry>  $entries
     * @return array<int, array{known: bool, exists: bool|null, url: string|null}> keyed by audit entry id
     */
    public static function resolve(Collection $entries): array
    {
        $existing = [];

        foreach ($entries->groupBy(fn (AdminAuditEntry $e) => $e->module.'|'.$e->entity_type) as $pair => $group) {
            if (! isset(self::KNOWN[$pair])) {
                continue;
            }

            [$model] = self::KNOWN[$pair];
            $ids = $group->pluck('entity_id')->unique()->values()->all();

            $existing[$pair] = array_flip(
                $model::query()->whereKey($ids)->pluck((new $model)->getKeyName())->map(fn ($id) => (int) $id)->all()
            );
        }

        $out = [];

        foreach ($entries as $entry) {
            $pair = $entry->module.'|'.$entry->entity_type;

            if (! isset(self::KNOWN[$pair])) {
                $out[$entry->id] = ['known' => false, 'exists' => null, 'url' => null];

                continue;
            }

            $exists = isset($existing[$pair][(int) $entry->entity_id]);

            $out[$entry->id] = [
                'known' => true,
                'exists' => $exists,
                'url' => $exists ? route(self::KNOWN[$pair][1], (int) $entry->entity_id) : null,
            ];
        }

        return $out;
    }
}
