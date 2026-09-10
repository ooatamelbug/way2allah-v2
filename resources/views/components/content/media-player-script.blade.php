@once
    @push('scripts')
        <script>
            (function () {
                var request = null;
                var opener = null;
                var currentMedia = null;
                var sequence = 0;
                var background = [];

                function panel() { return document.getElementById('the_main_player'); }
                function body() { return document.getElementById('w2a_main_player'); }

                function message(text, retry) {
                    body().replaceChildren();
                    var status = document.createElement('div');
                    status.className = 'w2a-player-status';
                    status.setAttribute('role', 'status');
                    var copy = document.createElement('p');
                    copy.textContent = text;
                    status.appendChild(copy);
                    if (retry) {
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'w2a-action-btn w2a-action-play';
                        button.textContent = 'إعادة المحاولة';
                        button.addEventListener('click', function () {
                            window.w2a_play(currentMedia.id, currentMedia.type);
                        });
                        status.appendChild(button);
                    }
                    body().appendChild(status);
                }

                function closePlayer() {
                    sequence++;
                    if (request) request.abort();
                    request = null;
                    body().replaceChildren();
                    panel().classList.remove('is-open');
                    document.body.classList.remove('w2a-player-open');
                    background.forEach(function (entry) { entry.element.inert = entry.inert; });
                    background = [];
                    if (opener && opener.isConnected) opener.focus({ preventScroll: true });
                }

                window.w2a_play = function (id, type) {
                    var dialog = panel();
                    if (!dialog) return;
                    var version = ++sequence;
                    if (request) request.abort();
                    currentMedia = { id: id, type: type };
                    if (!dialog.classList.contains('is-open')) {
                        opener = document.activeElement;
                        // Keep the overlay outside cards that clip or transform their children.
                        document.body.appendChild(dialog);
                        background = Array.from(document.body.children)
                            .filter(function (element) { return element !== dialog && !['SCRIPT', 'STYLE', 'LINK'].includes(element.tagName); })
                            .map(function (element) {
                                var entry = { element: element, inert: element.inert };
                                element.inert = true;
                                return entry;
                            });
                    }
                    dialog.classList.add('is-open');
                    document.body.classList.add('w2a-player-open');
                    message('جارٍ تحميل المادة…', false);
                    dialog.querySelector('.w2a-player-close-btn').focus({ preventScroll: true });
                    request = $.ajax({
                        url: '{{ route('media-player.show') }}',
                        method: 'POST',
                        data: { id: id, type: type, _token: $('meta[name="csrf-token"]').attr('content') },
                        dataType: 'html',
                        timeout: 20000,
                        success: function (data) {
                            if (version !== sequence) return;
                            if (!data.trim()) {
                                message('هذه المادة غير متاحة للتشغيل حالياً. يمكنك تجربة جودة أخرى أو إعادة المحاولة.', true);
                                return;
                            }
                            $('#w2a_main_player').html(data);
                            var media = body().querySelector('video, audio');
                            if (media) {
                                media.addEventListener('error', function () {
                                    if (version === sequence) message('تعذّر تحميل الملف. جرّب جودة أخرى أو أعد المحاولة.', true);
                                });
                                var bars = body().querySelector('.w2a-audio-anim-bars');
                                if (bars) {
                                    function updateBars() { bars.classList.toggle('is-playing', !media.paused && !media.ended); }
                                    ['play', 'pause', 'ended'].forEach(function (event) { media.addEventListener(event, updateBars); });
                                    updateBars();
                                }
                            }
                        },
                        error: function (_, status) {
                            if (status !== 'abort' && version === sequence) message('تعذّر الاتصال بالمشغل. يرجى إعادة المحاولة.', true);
                        }
                    });
                };

                $(function () {
                    var dialog = panel();
                    if (!dialog) return;
                    dialog.querySelector('.w2a-player-close-btn').addEventListener('click', closePlayer);
                    dialog.addEventListener('click', function (event) {
                        if (event.target === dialog) closePlayer();
                    });
                    dialog.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') { event.preventDefault(); closePlayer(); }
                        if (event.key === 'Tab') {
                            var controls = Array.from(dialog.querySelectorAll('button, a[href], audio[controls], video[controls], iframe, [tabindex="0"]'))
                                .filter(function (element) { return !element.disabled && element.getClientRects().length; });
                            var first = controls[0], last = controls[controls.length - 1];
                            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                        }
                    });
                });
            })();
        </script>
    @endpush
@endonce
