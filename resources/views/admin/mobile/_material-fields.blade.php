{{-- Phase 7 — staff material fields (OLD add / approve / edit forms). --}}
<label style="display:block">العنوان <input type="text" name="title" value="{{ old('title', $values['title'] ?? '') }}" maxlength="100" required class="form-control"></label>
<label style="display:block">الرابط <input type="text" name="url" value="{{ old('url', $values['url'] ?? '') }}" maxlength="100" required class="form-control" dir="ltr"></label>
<label style="display:block">التصنيف
    <select name="cid" class="form-control" required>
        @foreach ($categories as $o)<option value="{{ $o['cid'] }}" @selected((int) old('cid', $values['cid'] ?? 0) === $o['cid'])>{{ $o['label'] }}</option>@endforeach
    </select>
</label>
<label style="display:block">الوصف <textarea name="description" rows="6" required class="form-control">{{ old('description', $values['description'] ?? '') }}</textarea></label>
<div class="row">
    <div class="col-sm-4"><label style="display:block">صاحب المادة <input type="text" name="name" value="{{ old('name', $values['name'] ?? '') }}" maxlength="100" class="form-control"></label></div>
    <div class="col-sm-4"><label style="display:block">البريد <input type="text" name="email" value="{{ old('email', $values['email'] ?? '') }}" maxlength="100" class="form-control" dir="ltr"></label></div>
    <div class="col-sm-4"><label style="display:block">صفحته <input type="text" name="homepage" value="{{ old('homepage', $values['homepage'] ?? '') }}" maxlength="200" class="form-control" dir="ltr"></label></div>
    <div class="col-sm-4"><label style="display:block">الحجم (بايت) <input type="text" name="filesize" value="{{ old('filesize', $values['filesize'] ?? '') }}" class="form-control" dir="ltr"></label></div>
    <div class="col-sm-4"><label style="display:block">الإصدار <input type="text" name="version" value="{{ old('version', $values['version'] ?? '') }}" maxlength="10" class="form-control" dir="ltr"></label></div>
    @if (array_key_exists('hits', $values))
        <div class="col-sm-4"><label style="display:block">مرات التحميل <input type="number" name="hits" min="0" value="{{ old('hits', $values['hits']) }}" class="form-control" dir="ltr"></label></div>
    @endif
</div>
