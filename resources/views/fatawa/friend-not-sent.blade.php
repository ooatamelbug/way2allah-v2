@extends('layouts.app')

{{-- #106 (2026-11-08): send-to-friend not sent — rate limit reached (429) or a mail-transport
     failure (503). Same layout as fatawa/friend-sent; the message names no recipient. --}}

@section('title', 'لم يتم الإرسال')

@section('content')
    <p>{{ $message }}</p>
@endsection
