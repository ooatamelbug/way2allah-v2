@once
    @push('scripts')
        <script>
            function w2a_play(id, type) {
                $.ajax({
                    url: '{{ route('media-player.show') }}',
                    method: 'POST',
                    data: {
                        id: id,
                        type: type,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType: 'html',
                    success: function (data) {
                        $('#w2a_main_player').html(data);
                        $('#the_main_player').fadeIn();
                    }
                });
            }

            $(document).ready(function () {
                $('#the_main_player .clickable').click(function () {
                    $('#the_main_player').fadeOut(350, function () {
                        $('#w2a_main_player').empty();
                    });
                });
            });
        </script>
    @endpush
@endonce
