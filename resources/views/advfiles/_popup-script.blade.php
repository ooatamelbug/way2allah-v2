{{-- OLD user_khotab_head.htm / Khotab detailes.htm: `.various` opens the
     promotional file in an iframe popup (fancybox, already loaded by the layout). --}}
@once
    @push('scripts')
        <script>
            jQuery(function ($) {
                if ($.fancybox) {
                    $('.various').fancybox({ fitToView: true, closeBtn: false, closeClick: true, width: '800px', height: '450px', scrolling: 'auto' });
                }
            });
        </script>
    @endpush
@endonce
