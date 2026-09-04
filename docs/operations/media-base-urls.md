# Media base URLs

Public media URLs are controlled by three environment variables:

```dotenv
ASSET_BASE_URL=https://newapp.way2allah.com
MEDIA_BASE_URL="${ASSET_BASE_URL}/media"
THUMBNAIL_BASE_URL="${ASSET_BASE_URL}/thumbnails.php"
```

`ASSET_BASE_URL` is the parent host. In the normal setup, changing this one
value moves both direct media files and generated thumbnails to another host.
The other two values can be set independently when media files and thumbnail
processing are served by different hosts.

For production, edit the server's `.env`, for example:

```dotenv
ASSET_BASE_URL=https://way2allah.com
MEDIA_BASE_URL="${ASSET_BASE_URL}/media"
THUMBNAIL_BASE_URL="${ASSET_BASE_URL}/thumbnails.php"
```

Then refresh Laravel's cached configuration:

```bash
php artisan optimize:clear
php artisan config:cache
```

Do not add a trailing slash to these values. `MEDIA_BASE_URL` must point at
the media directory, while `THUMBNAIL_BASE_URL` must point at the complete
thumbnail endpoint. If all three variables are omitted, the application uses
the same-origin `/media` and `/thumbnails.php` paths.
