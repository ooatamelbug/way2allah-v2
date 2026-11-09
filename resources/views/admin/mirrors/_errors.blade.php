{{-- Phase 1 — the admin layout renders session('success')/('error') but not
     validation errors, so mirror forms render their own. --}}
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="list-unstyled" style="margin:0">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
