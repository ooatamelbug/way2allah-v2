@props(['variant' => 'bar'])

{{--
    Admin session indicator (owner request 2026-11-23; responsive fixes approved 2026-11-23): who is signed in to Laravel
    AdminCP, a link to it, and a secure logout — on public pages only, for an authenticated administrator only (always
    call it inside @contextualAdmin; visitors get no markup at all). State is the one at render time: no polling.

    Exactly one control is visible at any width:
    - variant "bar"  (inside the existing top bar, .pre-header)
        >= 1200px     the full pill, centred between the two top-bar columns;
        768-1199px    a compact dropdown, absolutely centred in the top bar — the visitor's columns keep their widths,
                      so the top bar is never taller than the visitor's (the full pill did not fit at 992-1199px);
    - variant "menu" (in the header, beside the mobile menu button)
        <= 767px      the same compact dropdown (the top bar itself is hidden there by the existing CSS).

    The dropdowns are native <details> (admin/contextual/_session-menu); a few lines of inline JS, no dependency, close
    an open one on Escape, on a pointer press outside it, and when keyboard focus leaves it. Logout is the existing POST
    admin.logout route with CSRF; `return_to` brings the admin back to this page and is validated server-side
    (ContextualReturn::safeTarget — same host, never /admincp). The response is made private by
    KeepContextualPagesPrivate, not here.
--}}
@php
    $sessionAdmin = app(\App\Domain\Admin\Contextual\ContextualAdmin::class)->admin();
    $sessionAdminName = $sessionAdmin === null ? '' : (trim((string) $sessionAdmin->name) !== '' ? (string) $sessionAdmin->name : (string) $sessionAdmin->aid);
    $sessionReturn = \App\Domain\Admin\Contextual\ContextualReturn::currentPage(request());
@endphp
{{-- A logout form needs the session (CSRF token); a session-less response gets no indicator. --}}
@if ($sessionAdmin !== null && request()->hasSession())
    @once
        <style>
            .w2a-admin-session-slot{direction:rtl}
            .w2a-admin-session{display:none;align-items:center;gap:6px;max-width:100%;margin:0 8px;padding:2px 10px;border:1px solid #cfe3d4;border-radius:999px;background:#f3faf5;font-size:12px;line-height:1.6;white-space:nowrap}
            .w2a-admin-session__dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#1f9d55;box-shadow:0 0 0 2px rgba(31,157,85,.18);flex:0 0 auto}
            .w2a-admin-session__who{overflow:hidden;text-overflow:ellipsis;max-width:180px}
            .w2a-admin-session__sep{color:#9bb5a2}
            .w2a-admin-session form{display:inline;margin:0}
            .w2a-admin-session button,.w2a-admin-session-menu button{padding:0;border:0;background:none;color:inherit;font:inherit;cursor:pointer}
            .w2a-admin-session a:focus-visible,.w2a-admin-session button:focus-visible,.w2a-admin-session-menu summary:focus-visible,.w2a-admin-session-menu .w2a-admin-session-menu__action:focus-visible{outline:2px solid #1f9d55;outline-offset:2px}

            .w2a-admin-session-menu{display:none;position:relative;direction:rtl}
            .w2a-admin-session-menu summary{list-style:none;display:inline-flex;align-items:center;gap:6px;padding:0 10px;border:1px solid #cfe3d4;border-radius:10px;background:#f3faf5;font-size:13px;cursor:pointer;white-space:nowrap;-webkit-user-select:none;user-select:none}
            .w2a-admin-session-menu summary::-webkit-details-marker{display:none}
            .w2a-admin-session-menu--header summary{height:46px}
            .w2a-admin-session-menu--bar summary{height:28px;border-radius:999px;font-size:12px}
            .w2a-admin-session-menu__panel{position:absolute;top:calc(100% + 6px);z-index:1050;width:max-content;min-width:200px;max-width:min(280px,calc(100vw - 30px));padding:6px;border:1px solid #d9e2dc;border-radius:10px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.12);font-size:13px;text-align:right}
            /* header dropdown: the summary starts 69px from the left edge (15px gutter + 46px menu button + 8px gap), so the panel is capped to keep a 15px margin on the right */
            .w2a-admin-session-menu--header .w2a-admin-session-menu__panel{left:0;min-width:min(200px,calc(100vw - 84px));max-width:min(280px,calc(100vw - 84px))}
            .w2a-admin-session-menu--bar .w2a-admin-session-menu__panel{left:50%;transform:translateX(-50%)}
            .w2a-admin-session-menu__panel ul{margin:0;padding:0;list-style:none}
            .w2a-admin-session-menu__name{padding:8px 10px;font-weight:700;overflow-wrap:anywhere;border-bottom:1px solid #eef2ef;margin-bottom:4px}
            .w2a-admin-session-menu form{margin:0}
            .w2a-admin-session-menu .w2a-admin-session-menu__action{display:flex;align-items:center;width:100%;min-height:40px;padding:0 10px;border-radius:8px;color:#1f2937;text-align:right;text-decoration:none}
            .w2a-admin-session-menu .w2a-admin-session-menu__action:hover{background:#f3faf5}
            /* the top-bar dropdown sits inside .pre-header, whose existing li / link rules (separator borders, padding, forced colour and weight) must not reach the panel */
            .w2a-admin-session-menu__panel{font-weight:400;line-height:1.5}
            .w2a-admin-session-menu__panel li{float:none !important;display:block !important;margin:0 !important;padding:0 !important;border:0 !important}
            .w2a-admin-session-menu__panel li.w2a-admin-session-menu__name{padding:8px 10px !important;margin-bottom:4px !important;border-bottom:1px solid #eef2ef !important}
            .w2a-admin-session-menu .w2a-admin-session-menu__action{color:#1f2937 !important;font-weight:500 !important}
            .w2a-admin-session-menu .w2a-admin-session-menu__action:hover{color:#1f9d55 !important;text-decoration:none !important}

            @media (min-width:1200px){
                .w2a-admin-session{display:inline-flex}
                div.pre-header .row:has(> .w2a-admin-session-slot) > div:not(.w2a-admin-session-slot){width:auto !important;flex:1 1 0 !important;min-width:0}
                .w2a-admin-session-slot{display:flex;justify-content:center;flex:0 1 auto;max-width:46%;min-width:0}
            }
            @media (min-width:768px) and (max-width:1199px){
                div.pre-header .row:has(> .w2a-admin-session-slot){position:relative}
                .w2a-admin-session-slot{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:2}
                .w2a-admin-session-menu--bar{display:block}
                /* the header (z-index 999) would paint over the open panel: lift the top bar only while it is open */
                div.pre-header:has(details[data-admin-session-menu="bar"][open]){position:relative;z-index:1000}
            }
            @media (max-width:767px){
                .w2a-admin-session-menu--header{display:inline-block;margin-inline-start:auto;margin-inline-end:8px}
            }
        </style>
        <script>
            (function () {
                var openMenus = function () { return document.querySelectorAll('details[data-admin-session-menu][open]'); };
                var closeOutside = function (target) {
                    openMenus().forEach(function (menu) { if (!menu.contains(target)) { menu.removeAttribute('open'); } });
                };
                document.addEventListener('pointerdown', function (event) { closeOutside(event.target); });
                document.addEventListener('focusin', function (event) { closeOutside(event.target); });
                document.addEventListener('keydown', function (event) {
                    if (event.key !== 'Escape') { return; }
                    openMenus().forEach(function (menu) {
                        var hadFocus = menu.contains(document.activeElement);
                        menu.removeAttribute('open');
                        if (hadFocus) { menu.querySelector('summary').focus(); }
                    });
                });
            })();
        </script>
    @endonce

    @if ($variant === 'menu')
        @include('admin.contextual._session-menu', ['where' => 'header', 'name' => $sessionAdminName, 'returnTo' => $sessionReturn])
    @else
        <div class="w2a-admin-session-slot" data-admin-session-slot>
            <div class="w2a-admin-session" role="status" aria-label="أنت مسجل الدخول كمشرف" data-admin-session>
                <span class="w2a-admin-session__dot" aria-hidden="true"></span>
                <span class="w2a-admin-session__who">مشرف: <strong>{{ $sessionAdminName }}</strong></span>
                <span class="w2a-admin-session__sep" aria-hidden="true">|</span>
                <a href="{{ route('admin.entry') }}">لوحة التحكم</a>
                <span class="w2a-admin-session__sep" aria-hidden="true">|</span>
                <form method="post" action="{{ route('admin.logout') }}">
                    @csrf
                    @if ($sessionReturn !== null)
                        <input type="hidden" name="{{ \App\Domain\Admin\Contextual\ContextualReturn::PARAM }}" value="{{ $sessionReturn }}">
                    @endif
                    <button type="submit">تسجيل الخروج</button>
                </form>
            </div>
            @include('admin.contextual._session-menu', ['where' => 'bar', 'name' => $sessionAdminName, 'returnTo' => $sessionReturn])
        </div>
    @endif
@endif
