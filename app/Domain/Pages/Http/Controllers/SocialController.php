<?php

namespace App\Domain\Pages\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Replaces pages/social.php (Roadmap task 2.5, added post-Wave-4 — see
 * docs/reviews/gap-closure-action-plan.md item 3). Pure static content, no
 * DB/business logic, same shape as AboutController/PrivacyController.
 *
 * Unlike this controller's Wave-2 siblings, the legacy page's pretty URL
 * (`social.htm`) was never a dead end nobody linked to — header.php's
 * account dropdown links to it twice, in permanent site nav, but no
 * .htaccess rule was ever added for it (confirmed: exhaustive grep, zero
 * matches). That standing link 404s in production today. Registering this
 * route at the exact path the nav already expects (`/social.htm`, see
 * routes/pages.php) is what makes it resolve — not a new clean path plus a
 * redirect, which is this file's siblings' pattern for legacy files nothing
 * ever linked to by pretty URL.
 *
 * Legacy-Source Reconstruction correction (supersedes this class's own
 * earlier image-path history): every entry's real source path is
 * `media/social-images/{file}` (`pages/social.php:105` etc., `$image_url .
 * 'media/social-images/' . $image`). A prior pass of this file correctly
 * found that directory didn't exist at the time and substituted
 * `pages/social-images/` as a working fix — but `legacy-project/media/`
 * has since been downloaded in full (confirmed: `media/social-images/`
 * now exists, same 30 files, byte-identical sizes to `pages/social-images/`,
 * and a fresh read-only GET of `https://way2allah.com/media/social-images/w2a.jpg`
 * returns 200 live). The literal source path is genuinely valid again —
 * restored to it rather than keeping the now-unnecessary workaround.
 *
 * Also restores the `alt` field (`pages/social.php`'s own separate `"alt"`
 * array key — a short English/transliterated identifier, distinct from
 * the full Arabic `"name"` used for the visible caption and `title`
 * attribute) — previously dropped during porting, confirmed via direct
 * source re-read and cross-checked against a live raw-path fetch.
 */
class SocialController
{
    public function __invoke(): View
    {
        $facebookPages = [
            ['name' => 'شبكة الطريق إلى الله - Way2Allah', 'link' => 'https://www.facebook.com/Way2allahCom', 'alt' => 'Way2allahCom', 'image' => 'w2a.jpg'],
            ['name' => 'شبكة الطريق إلى الله', 'link' => 'https://www.facebook.com/Way2Allah.Fb', 'alt' => 'Way2Allah.Fb', 'image' => 'w2a2.jpg'],
            ['name' => 'الطفل المسلم', 'link' => 'https://www.facebook.com/bebe.musulman', 'alt' => 'bebe.musulman', 'image' => 'muslim-kid.jpg'],
            ['name' => 'شبكة الطريق إلى الله - فلسطين', 'link' => 'https://www.facebook.com/Way2Allah.Palestine', 'alt' => 'Way2Allah.Palestine', 'image' => 'w2a-palestine.png'],
            ['name' => 'غرفة الهداية الدعوية', 'link' => 'https://www.facebook.com/Alhedaya.Room', 'alt' => 'Alhedaya.Room', 'image' => 'hedaya.png'],
            ['name' => 'قطوف', 'link' => 'https://www.facebook.com/sa3a.leklbk', 'alt' => 'sa3a.leklbk', 'image' => 'ktouf.jpg'],
            ['name' => 'الطريق إلى الله - سوشيال', 'link' => 'https://www.facebook.com/way2allahpage', 'alt' => 'way2allahpage', 'image' => 'w2a-social.png'],
        ];

        $youtubePages = [
            ['name' => 'القناة الرسمية لشبكة الطريق الى الله', 'link' => 'https://www.youtube.com/c/Way2allahPlus', 'alt' => 'Way2allahPlus', 'image' => 'Way2allahPlus.jpg'],
            ['name' => 'شبكة الطريق إلى الله - Way2AllahCom', 'link' => 'https://www.youtube.com/c/Way2allahCom', 'alt' => 'Way2allahCom', 'image' => 'Way2allahCom.jpg'],
            ['name' => 'شبكة الطريق الى الله - way2allah media', 'link' => 'https://www.youtube.com/c/Way2AllahMedia1', 'alt' => 'Way2AllahMedia1', 'image' => 'Way2AllahMedia1.jpg'],
            ['name' => 'برنامج حياة التربوي', 'link' => 'https://www.youtube.com/c/HayahProgram', 'alt' => 'HayahProgram', 'image' => 'HayahProgram.jpg'],
            ['name' => 'شبكة الطريق إلى الله - فلسطين', 'link' => 'https://youtube.com/@Way2Allah.Palestine', 'alt' => '@Way2Allah.Palestine', 'image' => 'Way2AllahPalestine.jpg'],
        ];

        $instagramPages = [
            ['name' => 'شبكة الطريق إلى الله', 'link' => 'https://www.instagram.com/way2allahcom/', 'alt' => 'way2allahcom', 'image' => 'instagram.jpg'],
            ['name' => 'شبكة الطريق إلى الله "ثريدز"', 'link' => 'https://www.threads.net/@way2allahcom', 'alt' => 'way2allahcom', 'image' => 'instagram.jpg'],
        ];

        $telegramPages = [
            ['name' => 'شبكة الطريق إلى الله - Way2allah.com', 'link' => 'https://t.me/way2allahcom', 'alt' => 'way2allahcom', 'image' => 'way2allahcom.jpg'],
            ['name' => 'بوت شبكة الطريق إلى الله', 'link' => 'https://t.me/way2allahcom_bot', 'alt' => 'way2allahcom_bot', 'image' => 'way2allahcom_bot.jpg'],
            ['name' => 'بوت مشروع تحفيظ القرآن الكريم', 'link' => 'https://t.me/TahfeezQuran_bot', 'alt' => 'TahfeezQuran_bot', 'image' => 'TahfeezQuran_bot.jpg'],
            ['name' => 'بوت استفسارات برنامج حياة التربوي', 'link' => 'https://t.me/hayahway2allah_bot', 'alt' => 'hayahway2allah_bot', 'image' => 'hayahway2allah_bot.jpg'],
        ];

        // pages/social.php:310 — a literal leading space before "https" on
        // the واتساب link (confirmed against source and a live fetch:
        // `href=" https://whatsapp.com/..."`). Harmless (browsers trim
        // whitespace in href values) but reproduced for byte fidelity,
        // same standard already applied to other confirmed-harmless
        // legacy quirks throughout this migration.
        $miscPages = [
            ['name' => 'تويتر', 'link' => 'https://twitter.com/way2allahcom', 'alt' => 'تويتر', 'image' => 'TwitterX.png'],
            ['name' => 'تيك توك', 'link' => 'https://www.tiktok.com/@way2allahcom', 'alt' => 'تيك توك', 'image' => 'tiktok.png'],
            ['name' => 'تيك توك فلسطين', 'link' => 'https://www.tiktok.com/@way2allah.palestine', 'alt' => 'تيك توك فلسطين', 'image' => 'tiktok.png'],
            ['name' => 'ساوند كلاود', 'link' => 'https://soundcloud.com/way2allahcom', 'alt' => 'ساوند كلاود', 'image' => 'soundcloud.png'],
            ['name' => 'واتساب', 'link' => ' https://whatsapp.com/channel/0029Va5lZWm90x2sDeAEoR3o', 'alt' => 'واتساب', 'image' => 'whatsapp.png'],
            ['name' => 'راديو غرفة الهداية', 'link' => 'https://mixlr.com/alhedaya-radio', 'alt' => 'راديو غرفة الهداية', 'image' => 'hedaya.png'],
        ];

        $podcastPages = [
            ['name' => 'Spotify', 'link' => 'https://open.spotify.com/show/65amn21YcaheOCxFpmU7Kb', 'alt' => 'Spotify', 'image' => 'spotify.png'],
            ['name' => 'Spotify for Podcasters', 'link' => 'https://podcasters.spotify.com/pod/show/way2allahcom', 'alt' => 'Spotify for Podcasters', 'image' => 'spotify_podcasters.png'],
            ['name' => 'Amazon Music', 'link' => 'https://music.amazon.com/podcasts/e15c1429-98c2-4a5f-8e3a-2215a1963076/شبكة-الطريق-إلى-الله', 'alt' => 'Amazon Music', 'image' => 'amazon_music.png'],
            ['name' => 'Apple Podcasts', 'link' => 'https://podcasts.apple.com/us/podcast/%D8%B4%D8%A8%D9%83%D8%A9-%D8%A7%D9%84%D8%B7%D8%B1%D9%8A%D9%82-%D8%A5%D9%84%D9%89-%D8%A7%D9%84%D9%84%D9%87/id1611770405', 'alt' => 'Apple Podcasts', 'image' => 'apple-podcasts.png'],
            ['name' => 'Google Podcasts', 'link' => 'https://www.google.com/podcasts?feed=aHR0cHM6Ly9hbmNob3IuZm0vcy82YWU2ZWFkYy9wb2RjYXN0L3Jzcw==', 'alt' => 'Google Podcasts', 'image' => 'google_podcasts.png'],
            ['name' => 'Castbox', 'link' => 'https://castbox.fm/ch/4786600', 'alt' => 'Castbox', 'image' => 'castbox.png'],
            ['name' => 'Pocket Casts', 'link' => 'https://pca.st/lrm8ingm', 'alt' => 'Pocket Casts', 'image' => 'pocket_casts.png'],
            ['name' => 'RadioPublic', 'link' => 'https://radiopublic.com/-G4xe9O', 'alt' => 'RadioPublic', 'image' => 'radio_public.png'],
            ['name' => 'Anghami', 'link' => 'https://play.anghami.com/podcast/1033883894', 'alt' => 'Anghami', 'image' => 'anghami.png'],
        ];

        return view('pages.social', compact(
            'facebookPages',
            'youtubePages',
            'instagramPages',
            'telegramPages',
            'miscPages',
            'podcastPages',
        ));
    }
}
