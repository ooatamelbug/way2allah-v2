<?php

use App\Support\Downloads\DownloadRejectionRecorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3D origin tracking — ONE additive, nullable column on `download_rejections`: `resource_title`, the
 * material's title at the moment of refusal (Khotab items: `nuke_islamic_khotab.title`, varchar(250); for a mirror,
 * its parent item's title). Every existing column is preserved; existing rows get NULL.
 *
 * Compatibility: a trailing nullable VARCHAR is an in-place / instant `ADD COLUMN` on MariaDB 10.3+ (verified on a
 * disposable 10.11: ALGORITHM=INSTANT accepted), so no table copy and no long lock. Same conventions as the table's
 * creating migration: `main` connection, and skipped under tests (the fixture MainSchema::downloadRejections() carries
 * the column; DownloadRejectionSchemaDriftTest keeps both in agreement). Idempotent: does nothing if the column
 * already exists. `down()` drops the column only while no row holds a title, so a rollback cannot destroy data.
 */
return new class extends Migration
{
    private const CONNECTION = 'main';

    public function up(): void
    {
        if (app()->runningUnitTests() || Schema::connection(self::CONNECTION)->hasColumn(DownloadRejectionRecorder::TABLE, 'resource_title')) {
            return;
        }

        Schema::connection(self::CONNECTION)->table(DownloadRejectionRecorder::TABLE, function (Blueprint $table) {
            $table->string('resource_title', 255)->nullable();
        });
    }

    public function down(): void
    {
        if (app()->runningUnitTests() || ! Schema::connection(self::CONNECTION)->hasColumn(DownloadRejectionRecorder::TABLE, 'resource_title')) {
            return;
        }
        if (DB::connection(self::CONNECTION)->table(DownloadRejectionRecorder::TABLE)->whereNotNull('resource_title')->exists()) {
            throw new RuntimeException('Refusing to drop download_rejections.resource_title: rows hold titles. Dropping it is a separate, owner-approved DBA action.');
        }

        Schema::connection(self::CONNECTION)->table(DownloadRejectionRecorder::TABLE, function (Blueprint $table) {
            $table->dropColumn('resource_title');
        });
    }
};
