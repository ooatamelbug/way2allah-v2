{{-- Phase 7 — the material fields a member submits or proposes (OLD AddDownload / modifydownloadrequest). --}}
<label style="display:block">اسم المادة <input type="text" name="title" value="{{ old('title', $values['title'] ?? '') }}" maxlength="100" required class="form-control"></label>
<label style="display:block">رابط المادة <input type="url" name="url" value="{{ old('url', $values['url'] ?? 'http://') }}" maxlength="100" required class="form-control" dir="ltr"></label>
<label style="display:block">التصنيف
    <select name="cid" class="form-control" required>
        @foreach ($categories as $option)
            <option value="{{ $option['cid'] }}" @selected((int) old('cid', $values['cid'] ?? 0) === $option['cid'])>{{ $option['label'] }}</option>
        @endforeach
    </select>
</label>
<label style="display:block">الوصف <textarea name="description" rows="8" required class="form-control">{{ old('description', $values['description'] ?? '') }}</textarea></label>
<label style="display:block">اسم صاحب المادة <input type="text" name="name" value="{{ old('name', $values['name'] ?? '') }}" maxlength="100" class="form-control"></label>
<label style="display:block">البريد الإلكتروني <input type="text" name="email" value="{{ old('email', $values['email'] ?? '') }}" maxlength="100" class="form-control" dir="ltr"></label>
<label style="display:block">حجم الملف (بالبايت) <input type="text" name="filesize" value="{{ old('filesize', $values['filesize'] ?? '') }}" maxlength="11" class="form-control" dir="ltr"></label>
<label style="display:block">الإصدار <input type="text" name="version" value="{{ old('version', $values['version'] ?? '') }}" maxlength="10" class="form-control" dir="ltr"></label>
<label style="display:block">صفحته <input type="text" name="homepage" value="{{ old('homepage', $values['homepage'] ?? 'http://') }}" maxlength="200" class="form-control" dir="ltr"></label>
