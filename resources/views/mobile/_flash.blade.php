{{-- Phase 7 — the public layout renders no flash messages; Mobile pages render their own. --}}
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger"><ul class="list-unstyled" style="margin:0">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul></div>
@endif
