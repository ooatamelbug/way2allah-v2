<?php

namespace App\Domain\Admin\Actions\Fatwa;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * C1 «اضافة موضوع للتصنيف» on `fatawa-topics-{cat}` — OLD `w2a_fatwa_add`
 * (`modules/Fatwa/admin/index.php`:409-507, production-hash-verified).
 *
 * OLD's insert: topic_name, description, meta_keywords, meta_description,
 * meta_index, meta_follow and parent_id = the category; a topic of the same
 * name under the same category is refused with OLD's message.
 *
 * `q_count` correction: OLD ran `q_count + 1` on the category and then
 * `update_parents()` — which adds 1 to the category again and to every
 * ancestor — so the category got +2. Laravel keeps OLD's walk once: +1 on
 * the category and +1 on each ancestor. Laravel reads `q_count` only as a
 * visibility filter (`> 0`) and an activity order, which this preserves.
 * The walk stops at the root, at a missing parent, and on a cycle.
 *
 * Corrections: authorized (`fatwa.manage`); bound SQL; the duplicate check,
 * insert, counters and audit row in one transaction with the category locked.
 */
final class InsertFatwaTopicAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated form
     *
     * @throws ValidationException a topic of that name already exists in the category
     */
    public function execute(AdminUser $actor, Category $category, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'fatwa.manage', $category);

        return DB::connection('main')->transaction(function () use ($actor, $category, $data, $ip) {
            $main = DB::connection('main');
            $categoryId = (int) $main->table('nuke_w2a_cat')->where('id', $category->id)->lockForUpdate()->value('id');
            abort_if($categoryId < 1, 404);

            $title = trim((string) $data['title']);
            $duplicate = $main->table('nuke_fatwa_topics')->where('topic_name', $title)->where('parent_id', $categoryId)->value('id');
            if ($duplicate !== null) {
                throw ValidationException::withMessages(['title' => '(*)هذا الموضوع موجود من قبل برقم كودى هو : '.$duplicate]);
            }

            $id = (int) $main->table('nuke_fatwa_topics')->insertGetId([
                'topic_name' => $title,
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => ! empty($data['meta_index']) ? 1 : 0,
                'meta_follow' => ! empty($data['meta_follow']) ? 1 : 0,
                'parent_id' => $categoryId,
            ]);

            $counted = self::countUp($categoryId);

            $this->audit->record($actor, 'add_topic', 'fatwa', 'topic', $id, [
                'category_id' => $categoryId,
                'title' => AuditText::digest($title),
                'q_count_incremented' => $counted,
            ], $ip);

            return $id;
        });
    }

    /**
     * OLD `update_parents()`, once: +1 on the category and on each ancestor.
     *
     * @return list<int> the categories incremented
     */
    private static function countUp(int $categoryId): array
    {
        $main = DB::connection('main');
        $seen = [];

        for ($id = $categoryId; $id > 0 && ! in_array($id, $seen, true) && count($seen) < 32;) {
            $parent = $main->table('nuke_w2a_cat')->where('id', $id)->value('main_cat');
            if ($parent === null) {
                break;
            }
            $main->table('nuke_w2a_cat')->where('id', $id)->increment('q_count');
            $seen[] = $id;
            $id = (int) $parent;
        }

        return $seen;
    }
}
