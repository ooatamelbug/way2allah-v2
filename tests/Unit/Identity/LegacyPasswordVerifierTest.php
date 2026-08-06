<?php

use App\Domain\Identity\Services\LegacyPasswordVerifier;

beforeEach(function () {
    $this->verifier = new LegacyPasswordVerifier;
});

it('verifies a correct bcrypt password', function () {
    $hash = password_hash('correct horse', PASSWORD_BCRYPT);

    expect($this->verifier->verify('correct horse', $hash))->toBeTrue()
        ->and($this->verifier->verify('wrong', $hash))->toBeFalse();
});

it('verifies a correct legacy MD5 password', function () {
    $hash = md5('legacy-password');

    expect($this->verifier->verify('legacy-password', $hash))->toBeTrue()
        ->and($this->verifier->verify('wrong', $hash))->toBeFalse();
});

it('verifies a correct legacy SHA1 password', function () {
    $hash = sha1('legacy-password');

    expect($this->verifier->verify('legacy-password', $hash))->toBeTrue()
        ->and($this->verifier->verify('wrong', $hash))->toBeFalse();
});

it('rejects an unrecognized hash shape instead of falling back to plaintext comparison', function () {
    // Blueprint v1.0 §16 item 3: the plaintext-fallback branch present in
    // legacy admincp/index.php is deliberately NOT reproduced here — even
    // when the "stored value" literally equals the submitted password.
    $storedAsPlaintext = 'hunter2';

    expect($this->verifier->verify('hunter2', $storedAsPlaintext))->toBeFalse();
});
