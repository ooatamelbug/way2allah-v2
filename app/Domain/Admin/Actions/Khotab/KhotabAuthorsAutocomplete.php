<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Content\Support\AutocompleteFiles;
use Illuminate\Support\Facades\DB;

/**
 * OLD `update_ajax_files_with_data('authors.txt', select_authors_ajax())`,
 * which «تعديل بيانات الداعية» and «حذف الداعية» ran after their write: every
 * author's name, `ORDER BY BINARY name ASC`, in OLD's file format
 * ({@see AutocompleteFiles}). Written to this application's configured
 * autocomplete directory — never to OLD's file.
 */
final class KhotabAuthorsAutocomplete
{
    public function refresh(): void
    {
        $main = DB::connection('main');
        $order = $main->getDriverName() === 'mysql' ? 'BINARY name ASC' : 'name ASC';

        AutocompleteFiles::write('authors.txt', $main->table('nuke_islamic_authors')->orderByRaw($order)->pluck('name')->map(fn ($name) => (string) $name));
    }
}
