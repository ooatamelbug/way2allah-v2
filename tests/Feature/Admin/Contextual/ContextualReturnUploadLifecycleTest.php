<?php

/*
 * Anasheed T1 manual review (2026-10-23): a real browser upload succeeded,
 * then the response crashed with FileNotFoundException for PHP's temporary
 * upload file. HonorContextualReturn ran after the controller and called
 * $request->has('return_to'), which walks allFiles() on the BASE request and
 * re-wraps its Symfony UploadedFile — whose temporary file the FormRequest
 * controller had already move()d.
 *
 * Laravel's HTTP test client puts Illuminate UploadedFile objects in the
 * files bag, which are never re-wrapped, so ordinary HTTP tests cannot see
 * this. These tests rebuild the real lifecycle instead: a base request whose
 * files bag holds a SYMFONY UploadedFile, a FormRequest copy pointing at the
 * same temporary path, the real upload action, then the middleware.
 */

use App\Domain\Admin\Actions\Anasheed\UploadAnasheedGroupIconAction;
use App\Domain\Admin\Actions\Anasheed\UploadAnasheedItemFrameAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Contextual\HonorContextualReturn;
use App\Domain\Admin\Http\Requests\Anasheed\UploadAnasheedGroupIconRequest;
use App\Domain\Admin\Http\Requests\Anasheed\UploadAnasheedItemFrameRequest;
use App\Domain\Content\Models\AnasheedGroup;
use App\Domain\Content\Models\AnasheedItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $this->tmp = sys_get_temp_dir().'/w2a-lifecycle-'.uniqid();
    config(['admin_anasheed.media_root' => $this->tmp.'/media']);
});

afterEach(function () {
    if (is_dir($this->tmp)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->tmp);
    }
});

/** A genuine JPEG in a temporary file, as PHP leaves an upload in /tmp/php*. */
function lifecycleJpeg(): string
{
    $path = tempnam(sys_get_temp_dir(), 'phpUpload');
    imagejpeg(imagecreatetruecolor(4, 4), $path);

    return $path;
}

/**
 * The real lifecycle: the BASE request holds what PHP + Symfony produce for an
 * upload (a Symfony UploadedFile); the FormRequest the container builds from it
 * gets a converted, movable copy of the SAME temporary path.
 *
 * @param  class-string<FormRequest>  $formRequest
 * @return array{0: Request, 1: FormRequest}
 */
function lifecycleRequests(string $formRequest, string $field, string $path): array
{
    $base = Request::create('/admincp/upload', 'POST', [], [], [$field => new SymfonyUploadedFile($path, 'upload.jpg', 'image/jpeg', null, true)]);
    $form = $formRequest::createFrom($base);
    $form->files->set($field, new UploadedFile($path, 'upload.jpg', null, null, true));

    return [$base, $form];
}

function runHonorContextualReturn(Request $request, string $to = '/'): mixed
{
    return (new HonorContextualReturn)->handle($request, fn () => new RedirectResponse($to));
}

it('never re-reads the uploaded-files bag, so a stale (already moved) upload cannot make it throw', function () {
    $path = lifecycleJpeg();
    $request = Request::create('/admincp/upload', 'POST', [], [], ['upload' => new SymfonyUploadedFile($path, 'upload.jpg', 'image/jpeg', null, true)]);
    unlink($path); // what move_uploaded_file() did to PHP's temporary file

    // Without return_to — the T1 case.
    $response = runHonorContextualReturn($request, '/var-item-1.htm');
    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toEndWith('/var-item-1.htm');

    // With return_to on a contextual route after a successful operation: the full path runs and redirects.
    $withReturn = Request::create('/admincp/upload', 'POST', [ContextualReturn::PARAM => '/khotab-item-1.htm'], [], ['upload' => new SymfonyUploadedFile(lifecycleJpeg(), 'upload.jpg', 'image/jpeg', null, true)]);
    unlink($withReturn->files->get('upload')->getPathname());
    $route = (new Route('POST', 'admincp/upload', fn () => null))->name('admin.mirrors.khotab.store');
    $withReturn->setRouteResolver(fn () => $route);
    $session = app('session')->driver();
    $session->start();
    $session->flash('success', 'تم');
    $withReturn->setLaravelSession($session);

    $redirected = runHonorContextualReturn($withReturn, '/admincp/mirrors');
    expect($redirected->getTargetUrl())->toEndWith('/khotab-item-1.htm');

    // The base requests' uploads were never converted (re-wrapped).
    $converted = fn (Request $r) => (fn () => $this->convertedFiles)->call($r);
    expect($converted($request))->toBeNull()->and($converted($withReturn))->toBeNull();
});

it('Anasheed item frame: the action moves the upload, the middleware then runs without error, the frame is stored', function () {
    $item = AnasheedItem::query()->findOrFail(H::insert('nuke_anasheed_anasheed', ['title' => 'نشيد', 'group_id' => 0]));
    $path = lifecycleJpeg();
    [$base, $form] = lifecycleRequests(UploadAnasheedItemFrameRequest::class, 'frame', $path);

    app(UploadAnasheedItemFrameAction::class)->execute(H::superAdmin(), $item, $form->file('frame'));
    expect(is_file($path))->toBeFalse(); // move() consumed the temporary upload, as in production

    $response = runHonorContextualReturn($base, "/var-item-{$item->id}.htm");

    expect($response->getTargetUrl())->toEndWith("/var-item-{$item->id}.htm")
        ->and(is_file($this->tmp."/media/anasheed/frame/0/{$item->id}.jpg"))->toBeTrue()
        ->and((int) DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $item->id)->value('frame'))->toBe(1);
});

it('Anasheed group icon: the same lifecycle runs without error and the icon is stored', function () {
    $group = AnasheedGroup::query()->findOrFail(H::insert('nuke_anasheed_groups', ['title' => 'قسم', 'parent_id' => 0]));
    $path = lifecycleJpeg();
    [$base, $form] = lifecycleRequests(UploadAnasheedGroupIconRequest::class, 'icon', $path);

    app(UploadAnasheedGroupIconAction::class)->execute(H::superAdmin(), $group, $form->file('icon'));
    expect(is_file($path))->toBeFalse();

    $response = runHonorContextualReturn($base, "/var-group-{$group->id}.htm");

    expect($response->getTargetUrl())->toEndWith("/var-group-{$group->id}.htm")
        ->and(is_file($this->tmp."/media/anasheed/icons/0/{$group->id}.jpg"))->toBeTrue()
        ->and((int) DB::connection('main')->table('nuke_anasheed_groups')->where('id', $group->id)->value('icon'))->toBe(1);
});
