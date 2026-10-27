<?php

declare(strict_types=1);

it('publishes a consistent Cashier Composer module contract', function (): void {
    $path = dirname(__DIR__, 2);
    $manifest = json_decode((string) file_get_contents($path.'/module.json'), true, 512, JSON_THROW_ON_ERROR);
    $composer = json_decode((string) file_get_contents($path.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['composer_package'])->toBe('koakademy/cashier');
    expect($manifest['license'])->toBe('AGPL-3.0-or-later');
    expect($manifest['providers'])->not->toBeEmpty();
    expect($composer['name'])->toBe($manifest['composer_package']);
    expect($composer['extra']['laravel']['providers'])->toBe($manifest['providers']);
    expect($manifest['version'])->toMatch('/^\d+\.\d+\.\d+$/');
    expect($composer['require']['php'])->toBe($manifest['requires']['php']);
});
