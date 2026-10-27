<?php

declare(strict_types=1);

use App\Modules\ModuleManifestRepository;

uses(Tests\TestCase::class);

it('loads Cashier from its installed Composer package without a bundled duplicate', function (): void {
    $module = app(ModuleManifestRepository::class)->installedModule('Cashier');

    expect($module)->not->toBeNull();
    expect($module->manifest->composerPackage)->toBe('koakademy/cashier');
    expect(realpath($module->path))->toBe(realpath(base_path('vendor/koakademy/cashier')));
    expect(is_dir(base_path('Modules/Cashier')))->toBeFalse();

    $provider = new ReflectionClass($module->manifest->providers[0]);
    expect($module->containsFile((string) $provider->getFileName()))->toBeTrue();
    expect(app('migrator')->paths())->toContain($module->path.'/database/migrations');
});

it('boots its installed provider when optional module scanning is disabled', function (): void {
    $module = app(ModuleManifestRepository::class)->installedModule('Cashier');
    $provider = $module->manifest->providers[0];
    config(['modules.scan.enabled' => false, 'modules.cache.enabled' => false]);
    $repository = new Nwidart\Modules\Laravel\LaravelFileRepository($this->app, base_path('Modules'));
    $this->app->instance('modules', $repository);
    $this->app->instance(Nwidart\Modules\Contracts\RepositoryInterface::class, $repository);
    Nwidart\Modules\Facades\Module::clearResolvedInstance('modules');
    Nwidart\Modules\Facades\Module::clearResolvedInstance(Nwidart\Modules\Contracts\RepositoryInterface::class);

    expect($repository->find('Cashier'))->toBeNull();
    (new $provider($this->app))->boot();
    expect(app('migrator')->paths())->toContain($module->path.'/database/migrations');
});
