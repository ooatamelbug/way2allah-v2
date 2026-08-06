<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * `estebian` (da'wah-caller registration/questionnaire responses) —
 * Roadmap task 5.8. The only table in `admincp/` that breaks the app's
 * `nuke_`-prefix convention (`admincp.md` §8) — reproduced with its real
 * name, not renamed to fit the pattern.
 *
 * `remarks1`-`remarks11` are free-text qualification/experience fields,
 * each with its own fixed label in the legacy detail view
 * (`questionnaire/index.php:121-197`) — kept as individually-named
 * columns, matching the legacy schema exactly, not normalized into a
 * generic key/value shape that would lose each field's distinct meaning.
 *
 * @property int $id
 * @property string|null $username
 * @property string|null $mobile
 * @property string|null $email
 * @property string|null $facebook
 */
class QuestionnaireResponse extends Model
{
    protected $connection = 'main';

    protected $table = 'estebian';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** The 11 labeled remarks fields, in the legacy detail view's own display order (`index.php:121-196`). */
    public const REMARK_LABELS = [
        'remarks1' => 'المؤهلات الدراسية الاكاديمية الشرعية وغيرها',
        'remarks2' => 'المواد الشرعية التي تم دراستها',
        'remarks3' => 'التخصص العلمي',
        'remarks4' => 'الخبرة العلمية الشرعية',
        'remarks5' => 'التخصص الدعوي',
        'remarks6' => 'الخبرة الدعوية',
        'remarks7' => 'الاجازات والتزكيات ان وجدت',
        'remarks8' => 'المواد الشرعية التى تود فضيلتكم ان تُدرِّسها ان تيسرت الفرصة لذلك',
        'remarks9' => 'الفئات الدعوية المستهدفة بالنسبة لك',
        'remarks10' => 'المادة الدعوية التى تود ان تُلقيها ان تيسرت الفرصة لذلك',
        'remarks11' => 'اقتراحات فضيلتكم لاهم الافكار و المشروعات الدعوية',
    ];
}
