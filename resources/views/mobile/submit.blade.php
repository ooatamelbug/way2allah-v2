@extends('layouts.app')

@section('title', 'الجوال الدعوي - أضف مادة جديدة')

@section('content')
    <x-page-chrome heading="أضف مادة جديدة" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '/mobiles.htm'], ['title' => 'أضف مادة جديدة', 'url' => '']]" />
    <div class="container-fluid">
        @include('mobile._flash')
        <x-content.premium-panel title="أضف مادة جديدة" icon="fa-plus" description="ستتم مراجعة المادة قبل نشرها.">
            <form method="post" action="{{ route('mobile.submit') }}">
                @csrf
                @include('mobile._fields', ['values' => []])
                <button type="submit" class="btn btn-primary">أضف المادة</button>
            </form>
        </x-content.premium-panel>
    </div>
@endsection
