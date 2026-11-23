{{--
    The compact administrator dropdown of the admin session indicator (components/admin/session-indicator.blade.php): $where is "bar"
    (top bar, 768-1199px) or "header" (beside the mobile menu button, <= 767px). Only rendered by that component, which
    has already checked the signed-in administrator and the session. Styles and the close-on-outside / Escape script
    live in the component.
--}}
<details class="w2a-admin-session-menu w2a-admin-session-menu--{{ $where }}" data-admin-session-menu="{{ $where }}">
    <summary aria-label="حساب المشرف: {{ $name }}">
        <span class="w2a-admin-session__dot" aria-hidden="true"></span>
        <span>حساب المشرف</span>
        <i class="fa fa-caret-down" aria-hidden="true"></i>
    </summary>
    <div class="w2a-admin-session-menu__panel" role="group" aria-label="حساب المشرف">
        <ul>
            <li class="w2a-admin-session-menu__name">مشرف: {{ $name }}</li>
            <li><a class="w2a-admin-session-menu__action" href="{{ route('admin.entry') }}">لوحة التحكم</a></li>
            <li>
                <form method="post" action="{{ route('admin.logout') }}">
                    @csrf
                    @if ($returnTo !== null)
                        <input type="hidden" name="{{ \App\Domain\Admin\Contextual\ContextualReturn::PARAM }}" value="{{ $returnTo }}">
                    @endif
                    <button type="submit" class="w2a-admin-session-menu__action">تسجيل الخروج</button>
                </form>
            </li>
        </ul>
    </div>
</details>
