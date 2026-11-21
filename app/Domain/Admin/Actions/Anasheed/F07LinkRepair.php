<?php

namespace App\Domain\Admin\Actions\Anasheed;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * B2 / F07-B2 (owner-approved 2026-11-16, decision-log #148) — the approved, byte-exact correction of 46 Variety
 * (Anasheed) media links: the HIGH rows of `docs/reports/admincp-b2-f07b-data-repair-plan.md` §1.1 minus the cosmetic
 * trims and item 16759. Nothing is discovered at run time: only `MANIFEST` rows are ever read or written.
 *
 * **Byte-exact (the critical part).** `link` is `utf8_unicode_ci` TEXT: case-insensitive and PAD SPACE, so on MySQL
 * `link = 'x'` also matches `'x '` and `'X'`. Every comparison here is on `HEX(link)` — the stored bytes — which is
 * byte-exact on MySQL / MariaDB and on SQLite alike. The expected old values are kept as hex in `MANIFEST`, so no
 * editor, formatter or trimming middleware can alter them. Writes are `UPDATE … SET link = ? WHERE id = ? AND
 * HEX(link) = ?` and must affect exactly one row; anything else rolls the whole transaction back.
 *
 * **Rows are independent:** each is `pending` (holds exactly its old bytes), `applied` (holds exactly its new value),
 * or blocking (`missing`, `parent_changed`, `conflict`). A mix of applied and pending rows is valid (each pending row
 * still matches byte-exactly); any blocking row stops the whole run.
 *
 * **Writes:** only `link` of the manifest rows, one `admin_audit_log` row per changed record (the exact old value and
 * its hex), inside one transaction, after a verified backup file. **Rollback** restores a row only while it holds
 * exactly the repaired value; a later legitimate edit is a conflict and is never overwritten.
 */
final class F07LinkRepair
{
    public const BACKUP_FORMAT = 'w2a.anasheed.f07b2.link-backup';

    public const BACKUP_VERSION = 1;

    /** Per record type: the permission an actor needs (super-admin always passes). */
    public const PERMISSIONS = ['item' => 'anasheed.editanasheed', 'mirror' => 'anasheed.editmirror'];

    /**
     * [type, id, parent (item: group_id; mirror: khid), expected OLD value as upper-case hex, approved NEW value, reason].
     * Generated from the local copy's stored bytes (2026-11-16); see the F07-B2 results report for every row.
     *
     * @var list<array{0: string, 1: int, 2: int, 3: string, 4: string, 5: string}>
     */
    public const MANIFEST = [
        ['item', 4860, 308, '20687474703A2F2F766172732E77617932616C6C61682E636F6D2F636172746F6F6E2F332F33617A6F7A5F322E726D7662', 'http://vars.way2allah.com/cartoon/3/3azoz_2.rmvb', 'leading_space'],
        ['item', 4868, 304, '20687474703A2F2F766172732E77617932616C6C61682E636F6D2F636172746F6F6E2F332F73686168645F31302E726D7662', 'http://vars.way2allah.com/cartoon/3/shahd_10.rmvb', 'leading_space'],
        ['item', 4869, 304, '20687474703A2F2F766172732E77617932616C6C61682E636F6D2F636172746F6F6E2F332F73686168645F31312E726D7662', 'http://vars.way2allah.com/cartoon/3/shahd_11.rmvb', 'leading_space'],
        ['item', 4892, 300, '20687474703A2F2F766172732E77617932616C6C61682E636F6D2F636172746F6F6E2F332F68617964795F34392E726D7662', 'http://vars.way2allah.com/cartoon/3/haydy_49.rmvb', 'leading_space'],
        ['item', 4893, 300, '20687474703A2F2F766172732E77617932616C6C61682E636F6D2F636172746F6F6E2F332F68617964795F35302E726D7662', 'http://vars.way2allah.com/cartoon/3/haydy_50.rmvb', 'leading_space'],
        ['item', 8703, 164, '2F646F776E6C6F61642F54686553756D6D6572486F6C6964617948442F54686525323073756D6D6572253230686F6C696461795F48442E776D76', 'https://archive.org/download/TheSummerHolidayHD/The%20summer%20holiday_HD.wmv', 'missing_archive_host'],
        ['item', 8870, 720, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D3231352F686D6C615F336D726B5F616C626C61655F73746573686E2E574D56', 'https://archive.org/download/media-way2Allah-215/hmla_3mrk_alblae_steshn.WMV', 'missing_archive_host'],
        ['item', 8871, 720, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D3231352F686D6C615F336D726B5F6B616E64655F6B7273682E574D56', 'https://archive.org/download/media-way2Allah-215/hmla_3mrk_kande_krsh.WMV', 'missing_archive_host'],
        ['item', 8872, 720, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D3231352F686D6C615F336D726B5F6C615F7467336C5F616C66657362776B5F656465335F336D726B2E574D56', 'https://archive.org/download/media-way2Allah-215/hmla_3mrk_la_tg3l_alfesbwk_ede3_3mrk.WMV', 'missing_archive_host'],
        ['item', 9075, 16, '2F646F776E6C6F61642F6D656469612D77617932616C6C61682D33332F6B776C5F2D656E5F616C6C685F776D6C6165616B74685F65736C776E5F336C655F616C6E62652E2E2E66655F6B687462615F616C676D33612D5F353935342E574D56', 'https://archive.org/download/media-way2allah-33/kwl_-en_allh_wmlaeakth_eslwn_3le_alnbe...fe_khtba_algm3a-_5954.WMV', 'missing_archive_host'],
        ['item', 9106, 732, '2F636172746F6F6E2F616D696E332F746162693369612D7265686C61742D6261687269612D7265686C612D656C612D616C6D6F6869742D616C686164692D322D382D323031342E574D56', 'http://vars.way2allah.com/cartoon/amin3/tabi3ia-rehlat-bahria-rehla-ela-almohit-alhadi-2-8-2014.WMV', 'missing_vars_host_exact_copy'],
        ['item', 9360, 750, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D2D2D382F62656E5F746133746B5F6C6C685F77746133615F616C6B68656C5F5F2D2D5F642E5F336264616C6D68736E5F616C61686D642D3531362E574D56', 'https://archive.org/download/media-way2Allah---8/ben_ta3tk_llh_wta3a_alkhel__--_d._3bdalmhsn_alahmd-516.WMV', 'missing_archive_host'],
        ['item', 9483, 16, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D2D2D33322F617368686E5F727365646B5F66655F726D64616E2D385F5F2E574D56', 'https://archive.org/download/media-way2Allah---32/ashhn_rsedk_fe_rmdan-8__.WMV', 'missing_archive_host'],
        ['item', 9498, 16, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D2D2D33335F3230313430382F643377615F74616561622E2D37335F2E574D56', 'https://archive.org/download/media-way2Allah---33_201408/d3wa_taeab.-73_.WMV', 'missing_archive_host'],
        ['item', 9589, 751, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D2D2D31352F6E736568615F6C73686172625F616C646B68616E5F6D6E5F616C7368656B685F616C73686E6B6574652D333139382E574D56', 'https://archive.org/download/media-way2Allah---15/nseha_lsharb_aldkhan_mn_alshekh_alshnkete-3198.WMV', 'missing_archive_host'],
        ['item', 9601, 751, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D2D2D31362F6C6D6E5F61736867686C74685F616D77725F616C646E65615F6D6B74335F6D6F73725F6764615F6C6C7368656B685F6D686D645F616C73686E6B6574652D3639342E574D56', 'https://archive.org/download/media-way2Allah---16/lmn_ashghlth_amwr_aldnea_mkt3_mosr_gda_llshekh_mhmd_alshnkete-694.WMV', 'missing_archive_host'],
        ['item', 9604, 751, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D2D2D31362F6D6862615F616C6C685F6C6C3362645F616C7368656B685F6D686D645F616C73686E6B6574652D3231382E574D56', 'https://archive.org/download/media-way2Allah---16/mhba_allh_ll3bd_alshekh_mhmd_alshnkete-218.WMV', 'missing_archive_host'],
        ['item', 9642, 727, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D2D2D35322F365F5F5F375F5F2D5F6B73735F775F3362725F5F5F5F5F6E62656C5F616C337764652D3132302E574D56', 'https://archive.org/download/media-way2Allah---52/6___7__-_kss_w_3br_____nbel_al3wde-120.WMV', 'missing_archive_host'],
        ['item', 9688, 16, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D2D2D36352F6B65665F746F64655F73686B725F616C65776D5F77616C6C656C612D325F3131342E574D56', 'https://archive.org/download/media-way2Allah---65/kef_tode_shkr_alewm_wallela-2_114.WMV', 'missing_archive_host'],
        ['item', 9724, 751, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D2D2D37352F616C676D335F62656E5F686465735F5F6B6C5F616D74655F6D336166655F5F7762656E5F6D6E5F657A615F6B686C655F626D6861726D5F616C6C685F616E74686B68615F6C6C7368656B685F6D686D645F616C73686E6B6574652D315F2D2E574D56', 'https://archive.org/download/media-way2Allah---75/algm3_ben_hdes__kl_amte_m3afe__wben_mn_eza_khle_bmharm_allh_anthkha_llshekh_mhmd_alshnkete-1_-.WMV', 'missing_archive_host'],
        ['item', 9826, 16, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D2D2D39372F6C615F747A68725F616C73686D6174615F62616B68656B2E2E2E645F336264616C6D68736E5F616C61686D642D5F332E574D56', 'https://archive.org/download/media-way2Allah---97/la_tzhr_alshmata_bakhek...d_3bdalmhsn_alahmd-_3.WMV', 'missing_archive_host'],
        ['item', 9869, 772, '2F646F776E6C6F61642F6865656B6D61613730322F74616768617269642D6E657368612D62616472792D73616C6F6D2D616C73616C6F6D2E574D56', 'https://archive.org/download/heekmaa702/tagharid-nesha-badry-salom-alsalom.WMV', 'missing_archive_host'],
        ['item', 9870, 772, '2F646F776E6C6F61642F6865656B6D61613730322F74616768617269642D736133717261322D617961742D616C6B6F7273692E574D56', 'https://archive.org/download/heekmaa702/tagharid-sa3qra2-ayat-alkorsi.WMV', 'missing_archive_host'],
        ['item', 9871, 772, '2F646F776E6C6F61642F6865656B6D61613730322F74616768617269642D736179617274692D616C736167686972612E574D56', 'https://archive.org/download/heekmaa702/tagharid-sayarti-alsaghira.WMV', 'missing_archive_host'],
        ['item', 9872, 772, '2F646F776E6C6F61642F6865656B6D61613730322F74616768617269642D7368696B6F6C6174612D6162642D6D616769642D646F736172792E574D56', 'https://archive.org/download/heekmaa702/tagharid-shikolata-abd-magid-dosary.WMV', 'missing_archive_host'],
        ['item', 9873, 772, '2F646F776E6C6F61642F6865656B6D61613730322F74616768617269642D74613265722D616C616D616C2D6E6F722D336F64612E574D56', 'https://archive.org/download/heekmaa702/tagharid-ta2er-alamal-nor-3oda.WMV', 'missing_archive_host'],
        ['item', 9874, 772, '2F646F776E6C6F61642F6865656B6D61613730322F74616768617269642D7461712D74617169612E574D56', 'https://archive.org/download/heekmaa702/tagharid-taq-taqia.WMV', 'missing_archive_host'],
        ['item', 9875, 772, '2F646F776E6C6F61642F6865656B6D61613730322F74616768617269642D746F74612D746F74612D33616269722D686567617A792E574D56', 'https://archive.org/download/heekmaa702/tagharid-tota-tota-3abir-hegazy.WMV', 'missing_archive_host'],
        ['item', 9876, 772, '2F646F776E6C6F61642F6865656B6D61613730322F74616768617269642D79616C612D6E6F6C6177696E2E574D56', 'https://archive.org/download/heekmaa702/tagharid-yala-nolawin.WMV', 'missing_archive_host'],
        ['item', 9877, 772, '2F646F776E6C6F61642F6865656B6D61613730322F746167687269642D616C726F64612D6D6F68616D65642D61626F2D616C6D6F66616C66696C2E574D56', 'https://archive.org/download/heekmaa702/taghrid-alroda-mohamed-abo-almofalfil.WMV', 'missing_archive_host'],
        ['item', 9955, 430, '2F636172746F6F6E2F616D696E332F746162693369612D33616C616D2D616C68616977616E2D616C6768616D69642D6768616269742D616C6768697A6C616E2D332D392D323031342E574D56', 'http://vars.way2allah.com/cartoon/amin3/tabi3ia-3alam-alhaiwan-alghamid-ghabit-alghizlan-3-9-2014.WMV', 'missing_vars_host_exact_copy'],
        ['item', 10467, 796, '2F646F776E6C6F61642F6D6564692D77617932416C6C61682D2D3136362F61337A6D5F616C6672685F616C6672685F62616C6C685F2D5F642E5F68617A6D5F7368776D616E5F5F6D6F73725F6764612D345F302E574D56', 'https://archive.org/download/medi-way2Allah--166/a3zm_alfrh_alfrh_ballh_-_d._hazm_shwman__mosr_gda-4_0.WMV', 'missing_archive_host'],
        ['item', 10547, 796, '2F646F776E6C6F61642F6D656469612D77617932416C6C61682D2D3136382F616C6B72616E5F676865726E655F645F68617A6D5F7368776D616E2D38375F2E574D56', 'https://archive.org/download/media-way2Allah--168/alkran_gherne_d_hazm_shwman-87_.WMV', 'missing_archive_host'],
        ['item', 11172, 420, '2F646F776E6C6F61642F70726F6D6F5F616461625F7368656B682E61686D65645F48442F70726F6D6F5F616461625F7368656B682E61686D65645F4D45442E776D76', 'https://archive.org/download/promo_adab_shekh.ahmed_HD/promo_adab_shekh.ahmed_MED.wmv', 'missing_archive_host'],
        ['item', 14823, 1082, 'D8B1687474703A2F2F766172732E77617932616C6C61682E636F6D2F636172746F6F6E2F616D696E382F636172746F6F6E2D66617269712D616C736537612D6661777A2D676869722D6D6F746177617161332D31312D323032315F783236342E6D7034', 'http://vars.way2allah.com/cartoon/amin8/cartoon-fariq-alse7a-fawz-ghir-motawaqa3-11-2021_x264.mp4', 'stray_character'],
        ['mirror', 10659, 8562, '68747470733A2F2F617263686976652E6F72672F646F776E6C6F61642F6865656B6D61613730312F6D6168626F62612D7261773365742D616C68656A61622D73616D612D6F73616D615F506F636B657450635667612E41564968747470733A2F2F617263686976652E6F72672F646F776E6C6F61642F6865656B6D61613730312F6D6168626F62612D7261773365742D616C68656A61622D73616D612D6F73616D615F506F636B657450635667612E415649', 'https://archive.org/download/heekmaa701/mahboba-raw3et-alhejab-sama-osama_PocketPcVga.AVI', 'duplicated_url'],
        ['mirror', 15966, 12309, '2068747470733A2F2F736F756E64636C6F75642E636F6D2F77617932616C6C6168636F6D2F616C3761736872', 'https://soundcloud.com/way2allahcom/al7ashr', 'leading_space'],
        ['mirror', 15969, 12308, '2068747470733A2F2F736F756E64636C6F75642E636F6D2F77617932616C6C6168636F6D2F77613779', 'https://soundcloud.com/way2allahcom/wa7y', 'leading_space'],
        ['mirror', 15970, 12311, '2068747470733A2F2F736F756E64636C6F75642E636F6D2F77617932616C6C6168636F6D2F6A6F6D3361', 'https://soundcloud.com/way2allahcom/jom3a', 'leading_space'],
        ['mirror', 15971, 12312, '2068747470733A2F2F736F756E64636C6F75642E636F6D2F77617932616C6C6168636F6D2F7A656B722D616C6C6168', 'https://soundcloud.com/way2allahcom/zekr-allah', 'leading_space'],
        ['mirror', 15978, 12313, '2068747470733A2F2F736F756E64636C6F75642E636F6D2F77617932616C6C6168636F6D2F6F71666E65626E776765626E', 'https://soundcloud.com/way2allahcom/oqfnebnwgebn', 'leading_space'],
        ['mirror', 15979, 12314, '2068747470733A2F2F736F756E64636C6F75642E636F6D2F77617932616C6C6168636F6D2F7766716D376538666372697520', 'https://soundcloud.com/way2allahcom/wfqm7e8fcriu', 'leading_space'],
        ['mirror', 15981, 12318, '2068747470733A2F2F736F756E64636C6F75642E636F6D2F77617932616C6C6168636F6D2F6E617361622D6F6D617220', 'https://soundcloud.com/way2allahcom/nasab-omar', 'leading_space'],
        ['mirror', 15986, 12319, '2068747470733A2F2F736F756E64636C6F75642E636F6D2F77617932616C6C6168636F6D2F6168616D7961742D7365657261', 'https://soundcloud.com/way2allahcom/ahamyat-seera', 'leading_space'],
        ['mirror', 15987, 12320, '2068747470733A2F2F736F756E64636C6F75642E636F6D2F77617932616C6C6168636F6D2F646177616265742D7365657261', 'https://soundcloud.com/way2allahcom/dawabet-seera', 'leading_space'],
        ['mirror', 15997, 12323, '2068747470733A2F2F736F756E64636C6F75642E636F6D2F77617932616C6C6168636F6D2F6D616E616865672D73697261', 'https://soundcloud.com/way2allahcom/manaheg-sira', 'leading_space'],
    ];

    /** Row states. */
    public const PENDING = 'pending';

    public const APPLIED = 'applied';

    public const MISSING = 'missing';

    public const PARENT_CHANGED = 'parent_changed';

    public const CONFLICT = 'conflict';

    /** @param array{item?: string, mirror?: string} $tables test seam for the MySQL byte-semantics tests only */
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly array $tables = [],
    ) {}

    public function table(string $type): string
    {
        return $this->tables[$type] ?? ($type === 'item' ? 'nuke_anasheed_anasheed' : 'nuke_anasheed_mirror');
    }

    /** The stored bytes of one link (null when the row does not exist), read through HEX() so nothing is trimmed or folded. */
    public static function storedBytes(ConnectionInterface $db, string $table, int $id, bool $lock = false): ?string
    {
        $query = $db->table($table)->where('id', $id)->selectRaw('HEX(link) AS link_hex');
        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();
        if ($row === null) {
            return null;
        }

        return $row->link_hex === null ? '' : (string) hex2bin((string) $row->link_hex);
    }

    /** `UPDATE {table} SET link = :to WHERE id = :id AND HEX(link) = HEX(:from)` — byte-exact; returns affected rows. */
    public static function replaceExact(ConnectionInterface $db, string $table, int $id, string $from, string $to): int
    {
        return $db->table($table)->where('id', $id)
            ->whereRaw('HEX(link) = ?', [strtoupper(bin2hex($from))])
            ->update(['link' => $to]);
    }

    /**
     * Read-only comparison of every manifest row with the stored bytes.
     *
     * @return array{verdict: string, rows: list<array<string, mixed>>, counts: array<string, int>, problems: list<string>}
     */
    public function plan(bool $lock = false): array
    {
        $db = DB::connection('main');
        $rows = [];
        $problems = [];
        foreach (self::MANIFEST as [$type, $id, $parent, $oldHex, $new, $reason]) {
            $old = (string) hex2bin($oldHex);
            $stored = self::storedBytes($db, $this->table($type), $id, $lock);
            $parentColumn = $type === 'item' ? 'group_id' : 'khid';
            $storedParent = $stored === null ? null : (int) $db->table($this->table($type))->where('id', $id)->value($parentColumn);

            $state = match (true) {
                $stored === null => self::MISSING,
                $storedParent !== $parent => self::PARENT_CHANGED,
                $stored === $old => self::PENDING,
                $stored === $new => self::APPLIED,
                default => self::CONFLICT,
            };
            if (! in_array($state, [self::PENDING, self::APPLIED], true)) {
                $problems[] = "{$type} {$id}: {$state}";
            }
            $rows[] = ['type' => $type, 'id' => $id, 'reason' => $reason, 'state' => $state, 'current' => $stored, 'expected' => $old, 'new' => $new];
        }

        $counts = array_count_values(array_column($rows, 'state'));
        $verdict = match (true) {
            $problems !== [] => 'blocked',
            ($counts[self::PENDING] ?? 0) === 0 => 'already_applied',
            default => 'ready',
        };

        return ['verdict' => $verdict, 'rows' => $rows, 'counts' => $counts, 'problems' => $problems];
    }

    /**
     * Back up, then apply every pending row in one transaction, re-validated under row locks.
     *
     * @return array{verdict: string, backup: string|null, updated: int, plan: array<string, mixed>}
     */
    public function apply(AdminUser $actor, string $backupDirectory, ?string $ip = null): array
    {
        $this->authorizeAll($actor);

        $plan = $this->plan();
        if ($plan['verdict'] !== 'ready') {
            return ['verdict' => $plan['verdict'], 'backup' => null, 'updated' => 0, 'plan' => $plan];
        }

        $backup = $this->writeBackup($actor, $backupDirectory, $plan);
        $db = DB::connection('main');

        $updated = $db->transaction(function () use ($db, $actor, $ip, $backup) {
            $locked = $this->plan(lock: true);
            if ($locked['verdict'] !== 'ready') {
                throw new RuntimeException('F07-B2: the data changed after the dry run; nothing was written ('.implode('; ', $locked['problems']).').');
            }

            $updated = 0;
            foreach ($locked['rows'] as $row) {
                if ($row['state'] !== self::PENDING) {
                    continue;
                }
                if (self::replaceExact($db, $this->table($row['type']), $row['id'], $row['expected'], $row['new']) !== 1) {
                    throw new RuntimeException("F07-B2: {$row['type']} {$row['id']} did not hold its exact expected value at write time; nothing was written.");
                }
                $this->audit->record($actor, 'repair_link', 'anasheed', $row['type'], $row['id'], [
                    'from' => $row['expected'], 'from_hex' => strtoupper(bin2hex($row['expected'])), 'to' => $row['new'],
                    'reason' => $row['reason'], 'ticket' => 'b2_f07b2', 'backup_sha256' => $backup['sha256'],
                ], $ip);
                $updated++;
            }

            return $updated;
        });

        $after = $this->plan();
        if ($after['verdict'] !== 'already_applied') {
            throw new RuntimeException('F07-B2: post-check failed after commit ('.implode('; ', $after['problems']).'). Roll back with: '.$backup['path']);
        }

        return ['verdict' => 'applied', 'backup' => $backup['path'], 'updated' => $updated, 'plan' => $after];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, restorable: int, restored: int, conflicts: int, backup: array<string, mixed>}
     */
    public function planRollback(string $backupPath, bool $lock = false): array
    {
        $backup = self::readBackup($backupPath);
        $db = DB::connection('main');
        $rows = [];
        foreach ($backup['rows'] as $entry) {
            $stored = self::storedBytes($db, $this->table($entry['type']), $entry['id'], $lock);
            $state = match (true) {
                $stored === null => 'conflict',
                $stored === $entry['new'] => 'restorable',
                $stored === $entry['old'] => 'restored',
                default => 'conflict',
            };
            $rows[] = ['type' => $entry['type'], 'id' => $entry['id'], 'state' => $state, 'current' => $stored, 'new' => $entry['new'], 'old' => $entry['old']];
        }
        $states = array_count_values(array_column($rows, 'state'));

        return ['rows' => $rows, 'restorable' => $states['restorable'] ?? 0, 'restored' => $states['restored'] ?? 0,
            'conflicts' => $states['conflict'] ?? 0, 'backup' => $backup];
    }

    /** @return array{restored: int, skipped_conflicts: int, plan: array<string, mixed>} */
    public function rollback(AdminUser $actor, string $backupPath, bool $skipConflicts = false, ?string $ip = null): array
    {
        $this->authorizeAll($actor);
        $db = DB::connection('main');

        return $db->transaction(function () use ($db, $actor, $backupPath, $skipConflicts, $ip) {
            $plan = $this->planRollback($backupPath, lock: true);
            if ($plan['conflicts'] > 0 && ! $skipConflicts) {
                return ['restored' => 0, 'skipped_conflicts' => $plan['conflicts'], 'plan' => $plan];
            }

            $restored = 0;
            foreach ($plan['rows'] as $row) {
                if ($row['state'] !== 'restorable') {
                    continue;
                }
                if (self::replaceExact($db, $this->table($row['type']), $row['id'], $row['new'], $row['old']) !== 1) {
                    throw new RuntimeException("F07-B2 rollback: {$row['type']} {$row['id']} changed during the rollback; nothing was written.");
                }
                $this->audit->record($actor, 'rollback_link', 'anasheed', $row['type'], $row['id'], [
                    'from' => $row['new'], 'to' => $row['old'], 'to_hex' => strtoupper(bin2hex($row['old'])),
                    'ticket' => 'b2_f07b2', 'backup_sha256' => $plan['backup']['sha256'],
                ], $ip);
                $restored++;
            }

            return ['restored' => $restored, 'skipped_conflicts' => $plan['conflicts'], 'plan' => $plan];
        });
    }

    private function authorizeAll(AdminUser $actor): void
    {
        foreach (array_unique(array_column(self::MANIFEST, 0)) as $type) {
            $this->authorizer->authorize($actor, self::PERMISSIONS[$type]);
        }
    }

    // ─── backup file ───────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $plan
     * @return array{path: string, sha256: string}
     */
    private function writeBackup(AdminUser $actor, string $directory, array $plan): array
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("F07-B2: cannot create the backup directory {$directory}; nothing was written.");
        }
        if (! is_writable($directory)) {
            throw new RuntimeException("F07-B2: the backup directory {$directory} is not writable; nothing was written.");
        }

        $payload = [
            'format' => self::BACKUP_FORMAT,
            'version' => self::BACKUP_VERSION,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'actor_aid' => (string) $actor->aid,
            'database' => (string) DB::connection('main')->getDatabaseName(),
            'manifest_sha256' => self::manifestSha256(),
            // Every manifest row, with the exact bytes found now (hex) and the approved new value.
            'rows' => array_map(fn (array $r) => ['type' => $r['type'], 'id' => $r['id'], 'state' => $r['state'],
                'old_hex' => strtoupper(bin2hex($r['expected'])), 'new' => $r['new']], $plan['rows']),
        ];
        $sha = hash('sha256', self::canonical($payload));
        $json = json_encode($payload + ['sha256' => $sha], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";

        $path = rtrim($directory, '/').'/anasheed-links-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4)).'.json';
        $temporary = $path.'.tmp';
        try {
            $handle = @fopen($temporary, 'xb');
            if ($handle === false) {
                throw new RuntimeException('cannot open the temporary backup file');
            }
            $written = fwrite($handle, $json);
            $flushed = fflush($handle) && (! function_exists('fsync') || fsync($handle));
            fclose($handle);
            if ($written !== strlen($json) || ! $flushed || ! @rename($temporary, $path)) {
                throw new RuntimeException('incomplete write');
            }
            @chmod($path, 0640);
            if (self::readBackup($path)['sha256'] !== $sha) {
                throw new RuntimeException('read-back mismatch');
            }
        } catch (Throwable $e) {
            @unlink($temporary);
            throw new RuntimeException('F07-B2: the backup could not be written and verified ('.$e->getMessage().'); nothing was written.', 0, $e);
        }

        return ['path' => $path, 'sha256' => $sha];
    }

    /**
     * Verify a backup: format, version, checksum, and that every row is a manifest row whose old bytes and new value
     * equal the approved ones (a tampered or foreign file is refused).
     *
     * @return array{rows: list<array{type: string, id: int, old: string, new: string}>, sha256: string, path: string}
     */
    public static function readBackup(string $path): array
    {
        $raw = is_file($path) ? @file_get_contents($path) : false;
        if ($raw === false) {
            throw new RuntimeException("F07-B2: backup file {$path} cannot be read.");
        }
        $data = json_decode($raw, true);
        if (! is_array($data) || ($data['format'] ?? null) !== self::BACKUP_FORMAT || ($data['version'] ?? null) !== self::BACKUP_VERSION
            || ! isset($data['sha256'], $data['rows']) || ! is_array($data['rows'])) {
            throw new RuntimeException("F07-B2: {$path} is not an F07-B2 link backup.");
        }
        $sha = (string) $data['sha256'];
        unset($data['sha256']);
        if (! hash_equals(hash('sha256', self::canonical($data)), $sha)) {
            throw new RuntimeException("F07-B2: backup {$path} failed its checksum.");
        }

        $approved = [];
        foreach (self::MANIFEST as [$type, $id, , $oldHex, $new]) {
            $approved[$type.':'.$id] = [$oldHex, $new];
        }
        $rows = [];
        foreach ($data['rows'] as $row) {
            $key = ($row['type'] ?? '').':'.(int) ($row['id'] ?? 0);
            if (! isset($approved[$key]) || $approved[$key] !== [(string) ($row['old_hex'] ?? ''), (string) ($row['new'] ?? '')]) {
                throw new RuntimeException("F07-B2: backup {$path} does not match the approved manifest ({$key}).");
            }
            $rows[] = ['type' => (string) $row['type'], 'id' => (int) $row['id'], 'old' => (string) hex2bin($approved[$key][0]), 'new' => $approved[$key][1]];
        }
        if (count($rows) !== count(self::MANIFEST)) {
            throw new RuntimeException("F07-B2: backup {$path} does not list exactly the approved rows.");
        }

        return ['rows' => $rows, 'sha256' => $sha, 'path' => $path];
    }

    public static function manifestSha256(): string
    {
        return hash('sha256', self::canonical(self::MANIFEST));
    }

    /** @param array<mixed> $data */
    private static function canonical(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
