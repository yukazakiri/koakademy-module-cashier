<?php

declare(strict_types=1);

$GLOBALS['manifestPath'] = dirname(__DIR__, 2).'/module.json';

it('publishes a valid Composer module contract', function (): void {
    $manifest = json_decode((string) file_get_contents($GLOBALS['manifestPath']), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['composer_package'])->toBe('koakademy/cashier')
        ->and($manifest['license'])->toBe('AGPL-3.0-or-later')
        ->and($manifest['providers'])->not->toBeEmpty();
});
