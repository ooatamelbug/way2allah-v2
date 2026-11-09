@extends('layouts.app')

@section('title', 'تقييم المادة - '.$material->title)

@section('content')
    <x-page-chrome :heading="'تقييم: '.$material->title" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '/mobiles.htm'], ['title' => $material->title, 'url' => '/mobileview-details-'.$material->lid.'.htm'], ['title' => 'تقييم', 'url' => '']]" />
    <div class="container-fluid">
        @include('mobile._flash')
        <x-content.premium-panel title="قيّم المادة" icon="fa-star">
            <ul>
                <li>الرجاء عدم تقييم نفس المادة أكثر من مرة.</li>
                <li>التقييم يكون من 1 - 10. بحيث 1 معناها ردئ و10 تعني ممتاز.</li>
                <li>الرجاء أن تكون عادلاً في تقييمك.</li>
            </ul>
            <form method="post" action="{{ route('mobile.rate', $material->lid) }}">
                @csrf
                <select name="rating" class="form-control" style="width:auto;display:inline-block" required>
                    <option value="">--</option>
                    @for ($i = 10; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }}</option>@endfor
                </select>
                @if ($member)
                    <label style="display:block;margin-top:8px">تعليق (اختياري)
                        <textarea name="comment" rows="4" class="form-control">{{ old('comment') }}</textarea>
                    </label>
                @endif
                <button type="submit" class="btn btn-primary" style="margin-top:8px">قيّم المادة</button>
            </form>
        </x-content.premium-panel>
    </div>
@endsection
