{{-- OLD LeechImages() output: the thumbnails to choose the still frame from, and the
     #more_info_feed block (size, duration, type, the chosen frame and the animated GIF). --}}
@if ($data['found'])
    @foreach ($data['images'] as $image)
        <img class="archive_frame" src="{{ $image }}" alt="" style="cursor:pointer;max-width:180px">
    @endforeach
    <div id="more_info_feed" style="display:none;">
        <div class="required">
            <label> حجم المادة :</label>
            <input class="inputText" type="text" value="{{ $data['size'] }}" size="8" disabled="disabled">
            <input type="hidden" value="{{ $data['original_size'] }}" name="original_size">
        </div>
        <div class="required">
            <label> وقت العرض :</label>
            <input class="inputText" type="text" value="{{ $data['duration'] }}" size="8" disabled="disabled">
        </div>
        <div class="required">
            <label>نوع المادة :</label>
            <input class="inputText" type="text" value="{{ $data['format'] }}" size="8" disabled="disabled">
        </div>
        <div class="required">
            <table width="100%">
                <tr>
                    <td width="50%">
                        @if ($data['frame'] !== '')
                            <label>صورة ثابتة :</label><br><br><img id="archive_frame_img" src="{{ $data['frame'] }}" alt="">
                            <input type="hidden" value="{{ $data['frame'] }}" name="archive_frame" id="archive_frame">
                        @endif
                    </td>
                    <td width="50%">
                        @if ($data['gif'] !== '')
                            <label>صورة متحركة:</label><br><br><img src="{{ $data['gif'] }}" alt="">
                            <input type="hidden" value="{{ $data['gif'] }}" name="archive_gif" id="archive_gif">
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </div>
@endif
