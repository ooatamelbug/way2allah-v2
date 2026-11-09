<?php

namespace App\Domain\Content\Models\Mobile;

use Illuminate\Database\Eloquent\Model;

/**
 * OLD Admin Migration Phase 7 — a rating, optionally with a comment
 * (`nuke_downloads_votedata`). Comments are not a separate table: a vote's
 * `ratingcomments` is the comment.
 *
 * @property int $ratingdbid
 * @property int $ratinglid
 * @property string $ratinguser
 * @property int $rating
 * @property string $ratinghostname
 * @property string $ratingcomments
 * @property string|null $ratingtimestamp
 */
class MobileVote extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_downloads_votedata';

    protected $primaryKey = 'ratingdbid';

    public $timestamps = false;

    protected $guarded = ['ratingdbid'];
}
