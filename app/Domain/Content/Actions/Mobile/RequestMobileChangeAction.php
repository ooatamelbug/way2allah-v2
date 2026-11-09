<?php

namespace App\Domain\Content\Actions\Mobile;

use App\Domain\Content\Models\Mobile\MobileChangeRequest;
use App\Domain\Content\Models\Mobile\MobileMaterial;
use App\Domain\Identity\Models\VbUser;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 7 — the two member requests on a material,
 * both stored in `nuke_downloads_modrequest`:
 *
 * - correction (OLD `modifydownloadrequestS`, `index.php:2091-2135`): the
 *   member's proposed values of every field, `brokendownload = 0`. Members
 *   only (`$blockunregmodify = 1`);
 * - broken link (OLD `brokendownloadS`, `:2000-2021`): `lid` and the
 *   reporter, `brokendownload = 1`. Members only (OLD offered the report to
 *   members and refused anyone else).
 *
 * Repeated requests are stored as OLD stored them (no de-duplication); staff
 * resolve them together. The requester is the member's username (OLD read a
 * PHP-Nuke cookie vBulletin members did not have).
 */
final class RequestMobileChangeAction
{
    /** @param array{cid: int, title: string, url: string, description: string, name: string, email: string, filesize: int, version: string, homepage: string} $proposed */
    public function correction(VbUser $member, MobileMaterial $material, array $proposed): int
    {
        return (int) DB::connection('main')->table('nuke_downloads_modrequest')->insertGetId($proposed + [
            'lid' => (int) $material->lid, 'sid' => 0,
            'modifysubmitter' => (string) $member->username, 'brokendownload' => MobileChangeRequest::CORRECTION,
        ], 'requestid');
    }

    public function brokenLink(VbUser $member, MobileMaterial $material): int
    {
        return (int) DB::connection('main')->table('nuke_downloads_modrequest')->insertGetId([
            'lid' => (int) $material->lid, 'cid' => 0, 'sid' => 0, 'title' => '', 'url' => '', 'description' => '',
            'modifysubmitter' => (string) $member->username, 'brokendownload' => MobileChangeRequest::BROKEN,
            'name' => '', 'email' => '', 'filesize' => 0, 'version' => '', 'homepage' => '',
        ], 'requestid');
    }
}
