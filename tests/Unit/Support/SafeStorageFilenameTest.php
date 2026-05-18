<?php

use App\Support\SafeStorageFilename;

describe('SafeStorageFilename', function () {
    it('strips soft hyphen macOS screenshots may use', function () {
        $withSoftHyphen = 'Scherm'."\u{AD}".'afbeelding.png';

        expect(SafeStorageFilename::forPathSegment($withSoftHyphen))
            ->toBe('Schermafbeelding.png');
    });

    it('uses basename ignoring directory segments in the input filename', function () {
        expect(SafeStorageFilename::forPathSegment('/tmp/hello.png'))
            ->toBe('hello.png');
    });

    it('falls back when only control characters remain', function () {
        expect(SafeStorageFilename::forPathSegment("\u{FEFF}"))
            ->toBe('attachment');
    });
});
