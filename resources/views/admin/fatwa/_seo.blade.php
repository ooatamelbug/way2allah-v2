{{-- OLD's description + «SEO :» block (fatwa_add.htm / fatwa_edit.htm / fatwa_general_question_*.htm). New records default to Index / Follow checked, as OLD's add forms. --}}
@php($seoIndex = old('_seo') !== null ? old('meta_index') !== null : (bool) ($record->meta_index ?? true))
@php($seoFollow = old('_seo') !== null ? old('meta_follow') !== null : (bool) ($record->meta_follow ?? true))
<input type="hidden" name="_seo" value="1">
<p><label>الوصف :</label><br><textarea cols="80" rows="10" name="description" class="form-control">{{ old('description', $record->description ?? '') }}</textarea></p>
<hr>
<p><b>SEO :</b></p>
<p><textarea cols="80" rows="2" name="meta_keywords" placeholder="Meta Keywords" class="form-control" dir="auto">{{ old('meta_keywords', $record->meta_keywords ?? '') }}</textarea></p>
<p><textarea cols="80" rows="5" name="meta_description" placeholder="Meta Description" class="form-control" dir="auto">{{ old('meta_description', $record->meta_description ?? '') }}</textarea></p>
<p><label><input type="checkbox" name="meta_index" value="1" @checked($seoIndex)> Index</label></p>
<p><label><input type="checkbox" name="meta_follow" value="1" @checked($seoFollow)> Follow</label></p>
