<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * FlashChat's own `room` table (vendor schema, `$chatdb`/`flashchat`
 * connection, Wave 0.2) — Roadmap task 5.5. `owner`/`speaker` are
 * comma-separated vBulletin *usernames* (not ids) — reproduced as-is
 * rather than normalized into a pivot table, matching `chat/edit_room.php`'s
 * own `explode(',', $room->owner)` shape exactly.
 *
 * @property int $room_id
 * @property string|null $name
 * @property string|null $des
 * @property int|null $max_user
 * @property int $enable
 * @property int $enable_audio
 * @property int $enable_video
 * @property int $enable_white_board
 * @property int $member_only
 * @property string|null $password
 * @property string|null $welcome
 * @property string|null $owner
 * @property string|null $speaker
 * @property int $sequence
 */
class Room extends Model
{
    protected $connection = 'flashchat';

    protected $table = 'room';

    protected $primaryKey = 'room_id';

    public $timestamps = false;

    protected $guarded = ['room_id'];

    /** `chat/edit_room.php:194`'s `explode(',', $room->owner)`. */
    public function ownerUsernames(): array
    {
        return $this->splitNames($this->owner);
    }

    public function speakerUsernames(): array
    {
        return $this->splitNames($this->speaker);
    }

    public function removeOwner(string $username): void
    {
        $this->update(['owner' => implode(',', array_diff($this->ownerUsernames(), [$username]))]);
    }

    public function removeSpeaker(string $username): void
    {
        $this->update(['speaker' => implode(',', array_diff($this->speakerUsernames(), [$username]))]);
    }

    private function splitNames(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $value)), fn (string $name) => $name !== '');
    }
}
