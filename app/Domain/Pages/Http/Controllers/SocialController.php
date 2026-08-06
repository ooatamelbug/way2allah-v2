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
 * Also fixes a second, independent bug while porting (not a behavior
 * change — the code's own intent was clearly a real path): every entry's
 * image referenced `media/social-images/{file}`, a directory that does not
 * exist. The real assets live in the legacy `pages/social-images/`
 * directory — Blueprint §12 names this exact path as the fix target ("same
 * relative paths preserved"), so images are referenced at
 * `/pages/social-images/{file}`, matching the real legacy directory
 * structure. (An earlier pass of this fix used `/images/social-images/`
 * instead, over-generalizing from `khotab/item.blade.php`'s `/images/flags/`
 * — corrected per docs/reviews/post-gap-closure-consistency-review.md
 * Finding 1; `pages/social-images/` was never a subfolder of `images/`, so
 * that precedent didn't actually apply here.)
 */
class SocialController
{
    public function __invoke(): View
    {
        $facebookPages = [
            ['name' => 'شبكة الطريق إلى الله - Way2Allah', 'link' => 'https://www.facebook.com/Way2allahCom', 'image' => 'w2a.jpg'],
            ['name' => 'شبكة الطريق إلى الله', 'link' => 'https://www.facebook.com/Way2Allah.Fb', 'image' => 'w2a2.jpg'],
            ['name' => 'الطفل المسلم', 'link' => 'https://www.facebook.com/bebe.musulman', 'image' => 'muslim-kid.jpg'],
            ['name' => 'شبكة الطريق إلى الله - فلسطين', 'link' => 'https://www.facebook.com/Way2Allah.Palestine', 'image' => 'w2a-palestine.png'],
            ['name' => 'غرفة الهداية الدعوية', 'link' => 'https://www.facebook.com/Alhedaya.Room', 'image' => 'hedaya.png'],
            ['name' => 'قطوف', 'link' => 'https://www.facebook.com/sa3a.leklbk', 'image' => 'ktouf.jpg'],
            ['name' => 'الطريق إلى الله - سوشيال', 'link' => 'https://www.facebook.com/way2allahpage', 'image' => 'w2a-social.png'],
        ];

        $youtubePages = [
            ['name' => 'القناة الرسمية لشبكة الطريق الى الله', 'link' => 'https://www.youtube.com/c/Way2allahPlus', 'image' => 'Way2allahPlus.jpg'],
            ['name' => 'شبكة الطريق إلى الله - Way2AllahCom', 'link' => 'https://www.youtube.com/c/Way2allahCom', 'image' => 'Way2allahCom.jpg'],
            ['name' => 'شبكة الطريق الى الله - way2allah media', 'link' => 'https://www.youtube.com/c/Way2AllahMedia1', 'image' => 'Way2AllahMedia1.jpg'],
            ['name' => 'برنامج حياة التربوي', 'link' => 'https://www.youtube.com/c/HayahProgram', 'image' => 'HayahProgram.jpg'],
            ['name' => 'شبكة الطريق إلى الله - فلسطين', 'link' => 'https://youtube.com/@Way2Allah.Palestine', 'image' => 'Way2AllahPalestine.jpg'],
        ];

        $instagramPages = [
            ['name' => 'شبكة الطريق إلى الله', 'link' => 'https://www.instagram.com/way2allahcom/', 'image' => 'instagram.jpg'],
            ['name' => 'شبكة الطريق إلى الله "ثريدز"', 'link' => 'https://www.threads.net/@way2allahcom', 'image' => 'instagram.jpg'],
        ];

        $telegramPages = [
            ['name' => 'شبكة الطريق إلى الله - Way2allah.com', 'link' => 'https://t.me/way2allahcom', 'image' => 'way2allahcom.jpg'],
            ['name' => 'بوت شبكة الطريق إلى الله', 'link' => 'https://t.me/way2allahcom_bot', 'image' => 'way2allahcom_bot.jpg'],
            ['name' => 'بوت مشروع تحفيظ القرآن الكريم', 'link' => 'https://t.me/TahfeezQuran_bot', 'image' => 'TahfeezQuran_bot.jpg'],
            ['name' => 'بوت استفسارات برنامج حياة التربوي', 'link' => 'https://t.me/hayahway2allah_bot', 'image' => 'hayahway2allah_bot.jpg'],
        ];

        $miscPages = [
            ['name' => 'تويتر', 'link' => 'https://twitter.com/way2allahcom', 'image' => 'TwitterX.png'],
            ['name' => 'تيك توك', 'link' => 'https://www.tiktok.com/@way2allahcom', 'image' => 'tiktok.png'],
            ['name' => 'تيك توك فلسطين', 'link' => 'https://www.tiktok.com/@way2allah.palestine', 'image' => 'tiktok.png'],
            ['name' => 'ساوند كلاود', 'link' => 'https://soundcloud.com/way2allahcom', 'image' => 'soundcloud.png'],
            ['name' => 'واتساب', 'link' => 'https://whatsapp.com/channel/0029Va5lZWm90x2sDeAEoR3o', 'image' => 'whatsapp.png'],
            ['name' => 'راديو غرفة الهداية', 'link' => 'https://mixlr.com/alhedaya-radio', 'image' => 'hedaya.png'],
        ];

        $podcastPages = [
            ['name' => 'Spotify', 'link' => 'https://open.spotify.com/show/65amn21YcaheOCxFpmU7Kb', 'image' => 'spotify.png'],
            ['name' => 'Spotify for Podcasters', 'link' => 'https://podcasters.spotify.com/pod/show/way2allahcom', 'image' => 'spotify_podcasters.png'],
            ['name' => 'Amazon Music', 'link' => 'https://music.amazon.com/podcasts/e15c1429-98c2-4a5f-8e3a-2215a1963076/شبكة-الطريق-إلى-الله', 'image' => 'amazon_music.png'],
            ['name' => 'Apple Podcasts', 'link' => 'https://podcasts.apple.com/us/podcast/%D8%B4%D8%A8%D9%83%D8%A9-%D8%A7%D9%84%D8%B7%D8%B1%D9%8A%D9%82-%D8%A5%D9%84%D9%89-%D8%A7%D9%84%D9%84%D9%87/id1611770405', 'image' => 'apple-podcasts.png'],
            ['name' => 'Google Podcasts', 'link' => 'https://www.google.com/podcasts?feed=aHR0cHM6Ly9hbmNob3IuZm0vcy82YWU2ZWFkYy9wb2RjYXN0L3Jzcw==', 'image' => 'google_podcasts.png'],
            ['name' => 'Castbox', 'link' => 'https://castbox.fm/ch/4786600', 'image' => 'castbox.png'],
            ['name' => 'Pocket Casts', 'link' => 'https://pca.st/lrm8ingm', 'image' => 'pocket_casts.png'],
            ['name' => 'RadioPublic', 'link' => 'https://radiopublic.com/-G4xe9O', 'image' => 'radio_public.png'],
            ['name' => 'Anghami', 'link' => 'https://play.anghami.com/podcast/1033883894', 'image' => 'anghami.png'],
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
