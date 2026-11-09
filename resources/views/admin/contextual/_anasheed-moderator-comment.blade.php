{{-- OLD comment_admin.htm (Comments(), modules/Anasheed/index.php): «إضافة تعليق إداري». Shown only
     to an admin whose permissions reach the route (ContextualAdmin::visible), for an item whose
     `comments` counter is above 0, as OLD did. --}}
<div class="w2a-admin-ctx" dir="rtl" data-contextual-admin data-moderator-comment style="display:block">
    <form method="post" action="{{ route('admin.anasheed.items.comments.store', ['anasheedItem' => $item->id]) }}" class="w2a-admin-ctx__form">
        @csrf
        <b>إضافة تعليق إداري :</b><br>
        <textarea name="comment" cols="50" rows="6" class="form-control" required></textarea>
        <button type="submit" class="w2a-admin-ctx__btn" style="margin-top:6px"><i class="fa fa-comment" aria-hidden="true"></i> إضافة التعليق</button>
    </form>
</div>
