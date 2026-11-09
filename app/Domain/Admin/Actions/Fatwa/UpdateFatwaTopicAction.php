<?php

namespace App\Domain\Admin\Actions\Fatwa;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\FatwaTopic;
use Illuminate\Support\Facades\DB;

/**
 * T2 «تعديل الموضوع» on `fatawa-group-{topic}-{cat}` — OLD `w2a_fatwa_edit`
 * (`modules/Fatwa/admin/index.php`:519-562): topic_name, description,
 * meta_keywords, meta_description, meta_index and meta_follow. The topic's
 * category (`parent_id`) is never written, as in OLD.
 *
 * Corrections: authorized (`fatwa.manage`); bound SQL (OLD interpolated the
 * raw name); one transaction with the row locked; audited.
 */
final class UpdateFatwaTopicAction
{
    private const TEXT = ['topic_name', 'description', 'meta_keywords', 'meta_description'];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @param array<string, mixed> $data the validated form */
    public function execute(AdminUser $actor, FatwaTopic $topic, array $data, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'fatwa.manage', $topic);

        DB::connection('main')->transaction(function () use ($actor, $topic, $data, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_fatwa_topics')->where('id', $topic->id)->lockForUpdate()->first();
            abort_if($row === null, 404);

            $after = [
                'topic_name' => trim((string) $data['topicname']),
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => ! empty($data['meta_index']) ? 1 : 0,
                'meta_follow' => ! empty($data['meta_follow']) ? 1 : 0,
            ];
            $before = [];
            foreach (array_keys($after) as $column) {
                $before[$column] = $row->{$column};
            }

            $main->table('nuke_fatwa_topics')->where('id', $row->id)->update($after);

            $this->audit->record($actor, 'edit_topic', 'fatwa', 'topic', (int) $row->id, [
                'category_id' => (int) $row->parent_id,
                'changes' => AuditText::changes($before, $after, self::TEXT),
            ], $ip);
        });
    }
}
