<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Admin\Support\Constraints\KhotabMediaTarget;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Multi-author creation (owner decisions 2026-11-25): «إضافة مادة» on the author, group and series pages may also
 * create an independent copy of the new item for up to `admin_khotab.additional_authors.max` other sheikhs.
 *
 * Shared by the three creation actions, which keep their own primary-item behaviour. Each copy is an ordinary
 * `nuke_islamic_khotab` row: the primary's content columns (title, description, meta, link, size, notes, section,
 * channel, location, time), its own author, outside any group or series, not on the home «new» list and not pinned,
 * `down = 2` like every new item (the per-item backup lifecycle is unchanged), its own counters. Nothing links the
 * copies afterwards except the primary's id in each copy's audit row (`created_with`).
 *
 * Concurrency protocol (all three flows, single- and multi-author): inside one transaction on `main`, lock the
 * group / series row (as before), then every author row the submission writes — ascending id, one locking read —
 * and only then run the duplicate-link check. Locking reads do not open InnoDB's REPEATABLE-READ read view, so the
 * check (the transaction's first consistent read) sees every submission that committed before the locks were
 * granted. Rules: no consistent read before the author locks, and never inside an outer transaction on `main`
 * ({@see transaction()}). Edit and Move do not take part (owner decision); external writers are outside it.
 */
final class KhotabAdditionalAuthorCopies
{
    /** Deadlock / lock-wait retries of the whole creation transaction. The callback only writes to `main`. */
    public const ATTEMPTS = 3;

    /** Session key of the post-commit image warning (multi-author submissions only), shown by the contextual flash. */
    public const IMAGE_WARNING_FLASH = 'khotab_additional_authors_image_warning';

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabLocationCounts $locations,
        private readonly KhotabArchiveImages $images,
    ) {}

    public static function max(): int
    {
        return max(0, (int) config('admin_khotab.additional_authors.max', 10));
    }

    /**
     * The additional author ids of a validated form, ascending.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    public static function requestedIds(array $data): array
    {
        $ids = array_map('intval', (array) ($data['additional_authors'] ?? []));
        $ids = array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
        sort($ids);

        return $ids;
    }

    /**
     * The creation transaction: on `main`, retried on a deadlock / lock wait, never nested — an outer transaction
     * could already hold an older read view and defeat the duplicate check.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function transaction(Closure $callback): mixed
    {
        $main = DB::connection('main');
        if ($main->transactionLevel() > 0) {
            throw new LogicException('Khotab item creation must not run inside an open transaction on `main`.');
        }

        return $main->transaction($callback, self::ATTEMPTS);
    }

    /**
     * `khotab.additem` for every destination, in the section the item is created in. The route middleware only sees
     * the route's own record, never these body-supplied authors.
     *
     * @param  list<int>  $authorIds
     */
    public function authorize(AdminUser $actor, int $vedio, array $authorIds): void
    {
        foreach ($authorIds as $ignored) {
            $this->authorizer->authorize($actor, 'khotab.additem', new KhotabMediaTarget($vedio));
        }
    }

    /**
     * Locks the primary and additional author rows (one locking read, ascending id). Must be the first read of the
     * transaction after the group / series lock.
     *
     * @param  list<int>  $additional
     * @return array<int, object{id: int|string, hidden: int|string|null, prename: ?string, name: ?string}> by id
     */
    public function lockAuthors(int $primaryId, array $additional): array
    {
        $ids = array_values(array_unique([$primaryId, ...$additional]));
        sort($ids);

        return DB::connection('main')->table('nuke_islamic_authors')
            ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()
            ->get(['id', 'hidden', 'prename', 'name'])
            ->keyBy(fn ($row) => (int) $row->id)->all();
    }

    /**
     * Every additional author must exist, be visible and differ from the primary.
     *
     * @param  list<int>  $additional
     * @param  array<int, object>  $locked  {@see lockAuthors()}
     */
    public function assertSelectable(int $primaryId, array $additional, array $locked): void
    {
        if (count($additional) > self::max()) {
            throw ValidationException::withMessages(['additional_authors' => 'يمكن اختيار '.self::max().' دعاة مشاركين على الأكثر.']);
        }

        foreach ($additional as $id) {
            if ($id === $primaryId) {
                throw ValidationException::withMessages(['additional_authors' => 'الداعية صاحب المادة لا يُختار ضمن الدعاة المشاركين.']);
            }
            $row = $locked[$id] ?? null;
            if ($row === null || (int) $row->hidden === 1) {
                throw ValidationException::withMessages(['additional_authors' => 'أحد الدعاة المختارين غير موجود أو غير متاح.']);
            }
        }
    }

    /**
     * Multi-author duplicate-link check: one indexed query per author (`author = ? AND link = ?` uses the author
     * index; `author IN (…)` made the optimizer scan the whole table). Every conflict is named; nothing is written.
     *
     * @param  list<int>  $additional
     * @param  array<int, object>  $locked
     */
    public function assertLinkFree(string $link, int $primaryId, array $additional, array $locked): void
    {
        $main = DB::connection('main');
        $conflicts = [];

        foreach ([$primaryId, ...$additional] as $authorId) {
            $existing = $main->table('nuke_islamic_khotab')->where('link', $link)->where('author', $authorId)->first(['id', 'title']);
            if ($existing !== null) {
                $row = $locked[$authorId] ?? null;
                $name = $row === null ? '#'.$authorId : trim(((string) $row->prename).' '.((string) $row->name));
                $conflicts[] = $name.' (المادة #'.$existing->id.' '.$existing->title.')';
            }
        }

        if ($conflicts !== []) {
            throw ValidationException::withMessages(['link' => 'هذه الوصلة مضافة بالفعل لدى: '.implode('، ', $conflicts)]);
        }
    }

    /**
     * Inserts the copies inside the caller's transaction, after the primary.
     *
     * @param  array<string, mixed>  $columns  the primary's inserted columns
     * @param  list<int>  $additional
     * @return array<int, int> author id => new item id
     */
    public function insert(AdminUser $actor, array $columns, int $primaryId, array $additional, ?string $ip): array
    {
        $main = DB::connection('main');
        $vedio = (int) $columns['vedio'];
        $type = $vedio === 1 ? 'vedio' : 'audio';
        $location = (int) $columns['location_id'];
        $copies = [];

        foreach ($additional as $authorId) {
            $main->table('nuke_islamic_authors')->where('id', $authorId)->update([$type => DB::raw($type.' + 1')]);

            $id = (int) $main->table('nuke_islamic_khotab')->insertGetId(array_merge($columns, [
                'author' => $authorId,
                'ser_id' => 0,
                'group_id' => 0,
                'newslist' => 0,
                'fixed' => 0,
            ]));
            $main->table('nuke_islamic_khotab')->where('id', $id)->where('weight', 0)->update(['weight' => $id]);

            if ($location > 0) {
                $this->locations->update('author', $authorId, $location);
            }

            $this->audit->record($actor, 'add_item', 'khotab', 'item', $id, [
                'series_id' => 0,
                'group_id' => 0,
                'author_id' => $authorId,
                'vedio' => $vedio,
                'channel_id' => (int) $columns['channel_id'],
                'link' => (string) $columns['link'],
                'linksize' => (int) $columns['linksize'],
                'location_id' => $location,
                'title' => AuditText::digest((string) $columns['title']),
                'created_with' => $primaryId,
            ], $ip);

            $copies[$authorId] = $id;
        }

        return $copies;
    }

    /**
     * After the commit: copy the primary's downloaded archive.org images to every copy (one download per image,
     * whatever the number of copies), set the copies' flags, and report what is missing where. Only called when
     * there are copies; a single-author creation keeps its existing silent behaviour.
     *
     * @param  array{gif?: int, frame?: int}  $primaryFlags  what the primary actually stored
     * @param  array<int, int>  $copies  author id => item id
     * @param  array<string, mixed>  $data  the validated form (which images were offered)
     */
    public function finishImages(int $primaryId, array $primaryFlags, array $copies, array $data): KhotabItemCreation
    {
        $copyIds = array_values($copies);
        $stored = $this->images->copyFromItem($primaryId, $primaryFlags, $copyIds);

        foreach ($stored as $copyId => $flags) {
            if ($flags !== []) {
                DB::connection('main')->table('nuke_islamic_khotab')->where('id', $copyId)->update($flags);
            }
        }

        $requested = array_keys(array_filter([
            'gif' => trim((string) ($data['archive_gif'] ?? '')) !== '',
            'frame' => trim((string) ($data['archive_frame'] ?? '')) !== '',
        ]));

        $missing = [];
        // `+`, not a spread: the keys are item ids and must not be renumbered.
        foreach ([$primaryId => $primaryFlags] + $this->byId($stored, $copyIds) as $itemId => $flags) {
            $kinds = array_values(array_filter($requested, fn (string $kind) => ! isset($flags[$kind])));
            if ($kinds !== []) {
                $missing[$itemId] = $kinds;
            }
        }

        return new KhotabItemCreation($primaryId, $copies, $missing);
    }

    /**
     * @param  array<int, array{gif?: int, frame?: int}>  $stored
     * @param  list<int>  $copyIds
     * @return array<int, array{gif?: int, frame?: int}>
     */
    private function byId(array $stored, array $copyIds): array
    {
        $out = [];
        foreach ($copyIds as $id) {
            $out[$id] = $stored[$id] ?? [];
        }

        return $out;
    }

    /** The flash after a successful submission; unchanged for a single-author creation. */
    public static function successMessage(KhotabItemCreation $result): string
    {
        if (! $result->hasCopies()) {
            return 'تمت إضافة المادة';
        }

        return 'تمت إضافة المادة لـ '.count($result->ids()).' دعاة (المواد: #'.implode('، #', $result->ids()).')';
    }

    /** The separate post-commit image warning (multi-author only), or null. */
    public static function imageWarning(KhotabItemCreation $result): ?string
    {
        if (! $result->hasCopies() || $result->missingImages === []) {
            return null;
        }

        $labels = ['gif' => 'الصورة المتحركة', 'frame' => 'الصورة الثابتة'];
        $parts = [];
        foreach ($result->missingImages as $id => $kinds) {
            $parts[] = '#'.$id.' ('.implode(' و', array_map(fn (string $kind) => $labels[$kind] ?? $kind, $kinds)).')';
        }

        return 'أُضيفت المواد بنجاح، لكن تعذّر حفظ صور archive.org للمواد: '.implode('، ', $parts)
            .' — استخدم «سحب الصور» من صفحة كل مادة.';
    }

    /** Adds the post-commit image warning to a creation redirect when there is one (multi-author only). */
    public static function withImageWarning(RedirectResponse $redirect, KhotabItemCreation $result): RedirectResponse
    {
        $warning = self::imageWarning($result);

        return $warning === null ? $redirect : $redirect->with(self::IMAGE_WARNING_FLASH, $warning);
    }

    /**
     * The previously selected authors (after a failed submission) for the form — at most the limit, visible only.
     * The page never loads the author list itself.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function selectedForForm(mixed $old, int $primaryId): array
    {
        $ids = array_slice(self::requestedIds(['additional_authors' => is_array($old) ? $old : []]), 0, self::max());
        $ids = array_values(array_filter($ids, fn (int $id) => $id !== $primaryId));
        if ($ids === []) {
            return [];
        }

        return DB::connection('main')->table('nuke_islamic_authors')
            ->whereIn('id', $ids)->where('hidden', 0)->orderBy('id')->get(['id', 'prename', 'name'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => trim(((string) $row->prename).' '.((string) $row->name))])
            ->values()->all();
    }
}
