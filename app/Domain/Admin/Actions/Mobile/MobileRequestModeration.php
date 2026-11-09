<?php

namespace App\Domain\Admin\Actions\Mobile;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\Mobile\MobileChangeRequest;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 7 — moderation of member requests
 * (`nuke_downloads_modrequest`). Replaces OLD `DownloadsListModRequests`,
 * `DownloadsChangeModRequests`, `DownloadsChangeIgnoreRequests`,
 * `DownloadsListBrokenDownloads`, `DownloadsDelBrokenDownloads` and
 * `DownloadsIgnoreBrokenDownloads`:
 *
 * - correction, accept: the proposed values replace the material's
 *   category, title, URL, description, author, e-mail, size, version and
 *   home page; the request is removed;
 * - correction, ignore: the request is removed;
 * - broken link, delete material: the material and **every** request on it
 *   are removed (OLD deleted all of the material's requests);
 * - broken link, ignore: the material's broken-link reports are removed.
 */
final class MobileRequestModeration extends MobileAdminAction
{
    private const CORRECTABLE = ['cid', 'title', 'url', 'description', 'name', 'email', 'filesize', 'version', 'homepage'];

    public function acceptCorrection(AdminUser $actor, MobileChangeRequest $request, ?string $ip = null): void
    {
        $this->authorize($actor, $request);

        DB::connection('main')->transaction(function () use ($actor, $request, $ip) {
            $main = DB::connection('main');
            $req = (array) ($main->table('nuke_downloads_modrequest')->where('requestid', $request->getKey())
                ->where('brokendownload', MobileChangeRequest::CORRECTION)->lockForUpdate()->first() ?? abort(404));
            $material = (array) ($main->table('nuke_downloads_downloads')->where('lid', $req['lid'])->lockForUpdate()->first() ?? abort(404));

            $new = array_intersect_key($req, array_flip(self::CORRECTABLE));
            $changes = array_filter($new, fn ($v, $k) => (string) $material[$k] !== (string) $v, ARRAY_FILTER_USE_BOTH);
            if ($changes !== []) {
                $main->table('nuke_downloads_downloads')->where('lid', $material['lid'])->update($changes + ['sid' => 0]);
            }
            $main->table('nuke_downloads_modrequest')->where('requestid', $req['requestid'])->delete();

            $this->record($actor, 'accept_mobile_correction', 'mobile_material', (int) $material['lid'], [
                'request_id' => (int) $req['requestid'],
                'requester' => self::text($req['modifysubmitter']),
                'before' => self::describe(array_intersect_key($material, $changes)),
                'after' => self::describe($changes),
            ], $ip);
        });
    }

    public function ignoreCorrection(AdminUser $actor, MobileChangeRequest $request, ?string $ip = null): void
    {
        $this->authorize($actor, $request);

        DB::connection('main')->transaction(function () use ($actor, $request, $ip) {
            $main = DB::connection('main');
            $req = $main->table('nuke_downloads_modrequest')->where('requestid', $request->getKey())
                ->where('brokendownload', MobileChangeRequest::CORRECTION)->lockForUpdate()->first();
            abort_if($req === null, 404);

            $main->table('nuke_downloads_modrequest')->where('requestid', $req->requestid)->delete();
            $this->record($actor, 'ignore_mobile_correction', 'mobile_request', (int) $req->requestid, [
                'lid' => (int) $req->lid, 'requester' => self::text($req->modifysubmitter),
            ], $ip);
        });
    }

    /** @return list<int> the requests removed with the material */
    public function deleteReportedMaterial(AdminUser $actor, int $lid, ?string $ip = null): array
    {
        $this->authorize($actor);

        return DB::connection('main')->transaction(function () use ($actor, $lid, $ip) {
            $main = DB::connection('main');
            $reports = $main->table('nuke_downloads_modrequest')->where('lid', $lid)->orderBy('requestid')->lockForUpdate()
                ->pluck('requestid')->map(fn ($r) => (int) $r)->all();
            $material = $main->table('nuke_downloads_downloads')->where('lid', $lid)->lockForUpdate()->first();
            abort_if($reports === [] && $material === null, 404);

            $main->table('nuke_downloads_modrequest')->where('lid', $lid)->delete();
            $main->table('nuke_downloads_downloads')->where('lid', $lid)->delete();

            $this->record($actor, 'del_mobile_broken', 'mobile_material', $lid, [
                'deleted_material' => $material === null ? null : [
                    'title' => self::text($material->title), 'url' => self::text($material->url), 'cid' => (int) $material->cid,
                ],
                'deleted_requests' => $reports,
            ], $ip);

            return $reports;
        });
    }

    public function ignoreBrokenReports(AdminUser $actor, int $lid, ?string $ip = null): int
    {
        $this->authorize($actor);

        return DB::connection('main')->transaction(function () use ($actor, $lid, $ip) {
            $main = DB::connection('main');
            $reports = $main->table('nuke_downloads_modrequest')->where('lid', $lid)->where('brokendownload', MobileChangeRequest::BROKEN)
                ->orderBy('requestid')->lockForUpdate()->pluck('requestid')->map(fn ($r) => (int) $r)->all();
            if ($reports === []) {
                return 0;
            }

            $main->table('nuke_downloads_modrequest')->whereIn('requestid', $reports)->delete();
            $this->record($actor, 'ignore_mobile_broken', 'mobile_material', $lid, ['ignored_requests' => $reports], $ip);

            return count($reports);
        });
    }
}
