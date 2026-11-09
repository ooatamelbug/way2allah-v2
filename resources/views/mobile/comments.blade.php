@extends('layouts.app')

@section('title', 'تعليقات على المادة - '.$material->title)

@section('content')
    <x-page-chrome :heading="'تعليقات على المادة: '.$material->title" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '/mobiles.htm'], ['title' => $material->title, 'url' => '/mobileview-details-'.$material->lid.'.htm'], ['title' => 'تعليقات', 'url' => '']]" />
    {{-- Contextual administration (OLD viewdownloadcomments(): «edit this material»). --}}
    @contextualAdmin
        <x-admin.contextual-controls :controls="[\App\Domain\Admin\Contextual\Controls\PublicPageControls::mobileMaterial($material)]" />
    @endcontextualAdmin
    <div class="container-fluid">
        <x-content.premium-panel title="تعليقات على المادة" icon="fa-comments">
            @forelse ($comments as $comment)
                <div style="border-bottom:1px solid #eee;padding:8px 0">
                    <strong>{{ $comment->ratinguser }}</strong> <span class="text-muted">— {{ $comment->ratingtimestamp }} · التقييم: {{ (int) $comment->rating }}</span>
                    <div style="white-space:pre-line">{{ $comment->ratingcomments }}</div>
                </div>
            @empty
                <p>لا توجد تعليقات على هذه المادة.</p>
            @endforelse
        </x-content.premium-panel>
    </div>
@endsection
