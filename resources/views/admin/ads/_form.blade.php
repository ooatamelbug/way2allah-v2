{{-- F01 / B6a — OLD newads.htm / editads.htm fields. Every value is escaped; nothing stored in an ad is ever
     rendered as markup here. The raw-code option exists only for super-admins (owner security rule); the
     share («نسبة العرض») is absent, as it was commented out in both OLD forms. --}}
@php($ads = \App\Domain\Admin\Support\LegacyAds::class)
@php($isCode = $ad !== null && (int) $ad->type === $ads::TYPE_CODE)
@php($type = (int) old('type', $ad?->type ?? $ads::TYPE_IMAGE))
@php($pathType = (int) old('path_type', $ad !== null && (int) $ad->type === $ads::TYPE_IMAGE ? ((int) $ad->path_type === $ads::PATH_UPLOAD ? $ads::PATH_UPLOAD : $ads::PATH_URL) : $ads::PATH_URL))
@php($showType = (string) old('ads_show_type', $ad?->ads_show_type ?? 'period'))
<table class="table table-light">
    <tr>
        <td style="width:220px"><label for="ad-name">اسم الاعلان:</label></td>
        <td><input type="text" id="ad-name" name="name" value="{{ old('name', $ad?->name) }}" maxlength="255" required class="form-control"></td>
    </tr>
    <tr>
        <td>نوع الاعلان:</td>
        <td>
            <label><input type="radio" name="type" value="1" @checked($type === $ads::TYPE_IMAGE)> اعلان مصور</label>
            @if ($isSuperAdmin)
                &nbsp;&nbsp;<label><input type="radio" name="type" value="0" @checked($type === $ads::TYPE_CODE)> جوجل ادسنس (شفرة HTML/JavaScript)</label>
            @else
                <p class="help-block">إعلانات الشفرة (جوجل أدسنس) يضيفها ويعدلها المدير العام فقط.</p>
            @endif
        </td>
    </tr>
    @if ($isSuperAdmin)
        <tr>
            <td><label for="ad-code">شفرة جوجل أدسنس:</label><br><small class="text-muted">لنوع «جوجل ادسنس» فقط</small></td>
            {{-- Escaped inside the textarea: the stored code is shown as text, never executed in the admin. --}}
            <td><textarea id="ad-code" name="google_code" rows="4" cols="70" class="form-control" dir="ltr">{{ old('google_code', $isCode ? $ad?->image_path : '') }}</textarea></td>
        </tr>
    @endif
    <tr>
        <td><label for="ad-path-type">مسار الصورة المراد ظهورها:</label><br><small class="text-muted">للإعلان المصور فقط</small></td>
        <td>
            <select id="ad-path-type" name="path_type" class="form-control" style="max-width:220px">
                <option value="2" @selected($pathType === $ads::PATH_URL)>URL رابط</option>
                <option value="1" @selected($pathType === $ads::PATH_UPLOAD)>تحميل ملف</option>
            </select>
            <p style="margin-top:8px">
                <label>إختر صورة الإعلان (JPG / PNG / GIF / WebP، حتى 2 ميجا):
                    <input type="file" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp"></label>
            </p>
            <p>
                <label>أكتب مسار الصورة:
                    <input type="text" name="image_url" dir="ltr" maxlength="2048" class="form-control"
                           value="{{ old('image_url', $ad !== null && ! $isCode && (int) $ad->path_type !== $ads::PATH_UPLOAD ? $ad->image_path : '') }}"></label>
            </p>
            @if ($ad !== null && ! $isCode)
                @php($preview = $ads::safeUrl($ad->image_path))
                <div>شكل الإعلان الحالي:
                    @if ($preview !== null)
                        <br><img src="{{ $preview }}" alt="" style="max-width:300px; max-height:250px; border:1px solid #ccc">
                    @else
                        <span class="text-muted" dir="ltr">{{ $ad->image_path }}</span>
                    @endif
                </div>
            @endif
        </td>
    </tr>
    <tr>
        <td><label for="ad-link">مسار موقع الاعلان:</label></td>
        <td><input type="text" id="ad-link" name="link" dir="ltr" maxlength="2048" value="{{ old('link', $ad?->link) }}" class="form-control"></td>
    </tr>
    <tr>
        <td><label for="ad-position">موقع الاعلان في الصفحة:</label></td>
        <td>
            <select id="ad-position" name="position" class="form-control" style="max-width:360px">
                @foreach ($ads::POSITIONS as $id => $label)
                    <option value="{{ $id }}" @selected((int) old('position', $ad?->position ?? 1) === $id)>{{ $label }}</option>
                @endforeach
            </select>
        </td>
    </tr>
    <tr>
        <td><label for="ad-show">اظهار:</label></td>
        <td><input type="checkbox" id="ad-show" name="show" value="1" @checked((int) old('show', $ad?->show ?? 0) === 1)></td>
    </tr>
    <tr>
        <td>طريقة حساب الإعلان</td>
        <td>
            <label><input type="radio" name="ads_show_type" value="numview" @checked($showType === 'numview')> بعدد مرات الظهور</label>
            &nbsp;&nbsp;<label><input type="radio" name="ads_show_type" value="period" @checked($showType === 'period')> بالمدة</label>
        </td>
    </tr>
    <tr>
        <td><label for="ad-numview">عدد مرات الظهور</label><br><small class="text-muted">لطريقة «بعدد مرات الظهور»</small></td>
        <td><input type="number" id="ad-numview" name="required_num_view" min="1" value="{{ old('required_num_view', $ad?->required_num_view) }}" class="form-control" style="max-width:220px"></td>
    </tr>
    <tr>
        <td>مدة الاعلان<br><small class="text-muted">لطريقة «بالمدة»</small></td>
        <td>
            من : <input type="date" name="startdate" value="{{ old('startdate', $ad?->startdate) }}" class="form-control" style="display:inline-block; max-width:180px">
            &nbsp;الى : <input type="date" name="enddate" value="{{ old('enddate', $ad?->enddate) }}" class="form-control" style="display:inline-block; max-width:180px">
        </td>
    </tr>
</table>
