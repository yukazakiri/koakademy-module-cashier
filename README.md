# KoAkademy Cashier Module

Cashier workflows for student balance lookup and payment posting.

## Requirements

- KoAkademy 1.29 or newer with the extension SDK from core PR #486
- PHP 8.5.12 or newer in the 8.5 series
- Laravel 13, Filament 5, and nwidart/laravel-modules 13
- A host application that uses the KoAkademy module loader

Public releases are intended for [Packagist](https://packagist.org/). The
Marketplace catalog is reviewed separately and does not install PHP code into
a running container; Composer and the application image do that.

## Installation

After `v1.1.0` is tagged and the package is published on Packagist, run the
following commands from the host application's repository root:

    composer require koakademy/cashier:^1.1
    php artisan migrate --force
    php artisan optimize:clear

Commit composer.json and composer.lock; do not install the package only inside
one running replica. The command requires the version to be published first.

### Docker, Swarm, Dokploy, and Kubernetes

Install the package during the application image build, then build the normal
frontend assets:

    composer install --no-dev --prefer-dist --optimize-autoloader
    npm ci
    npm run build

Run the migration once as a release job or one-off task, clear the application
cache, and restart or roll every application worker. For Docker Compose this
is normally docker compose build followed by the platform's migration task.
For Swarm or Dokploy, push the new image to the configured registry and
redeploy the service with that image. For Kubernetes, run the migration as a
release Job and roll the Deployment. All replicas must use the same
composer.lock and image.

### Enable the module

1. Sign in as a super administrator.
2. Open **Administrators → System → Extensions & licenses**, or visit
   /administrators/module-marketplace.
3. Enable **Cashier**.
4. Restart/redeploy the application so every worker reads the new module
   status.

Fresh installations initialize optional modules disabled. Upgrades preserve
the existing modules_statuses.json choices, so an already-enabled Cashier
module stays enabled.

## Using the admin panel

Open the Filament admin panel at /admin and select **Cashier** in the
**Finance** group. Search for a student by Student ID, review the balance and
tuition details, and use the available payment/settlement actions according to
the operator's permissions.

The package also exposes authenticated web routes under /cashiers and
versioned Sanctum API routes under /api/v1/cashiers; use the Filament page for
normal administrator workflows.

## Upgrading

After releasing a newer module tag, update the package in the host repository,
rebuild the image, run migrations, and redeploy. Refreshing Marketplace alone
only refreshes catalog metadata.

## License

AGPL-3.0-or-later


## Repository ownership and the 1.1 migration

This repository owns the extension source. The application installs a reviewed
Composer version into `vendor/`; do not maintain a second copy under the host's
`Modules/` directory or rename its `Modules\Cashier` PHP namespace.

The 1.1.0 proposal carries forward the tested bundled implementation from core
commit ff3cae91. It preserves routes, tables, migration filenames, policies,
and existing module status. It also resolves provider paths from this package
rather than depending on the runtime vendor-scan flag during application boot.

For the first migration, remove `Modules/Cashier` from the application in the
same dependency change that installs `koakademy/cashier`. Keep the database and
module-installation rows. Never run `migrate:fresh` or delete school data during
an upgrade. Run `php artisan migrate --force`, `modules:sync-permissions`, and
`modules:validate --all` in the release container, then rebuild assets/caches and
roll workers. Keep `MODULE_SCAN_VENDOR=true`.

Package CI runs standalone contract checks and installs this contribution into
an isolated checkout of the real host, removing its bundled duplicate first.
It exercises package discovery and relevant host workflows, then builds browser
and SSR assets. Pin/update the tested host revision deliberately. Additional
frontend dependencies require a reviewed host npm lockfile change.

After review, merge and tag `v1.1.0` with a matching `module.json` version.
Submit the public repository to Packagist and verify that version is visible.
The registry proposes a separately reviewed, signed catalog update. A catalog entry
does not update a running school image: the application must commit its Composer
lockfile and deploy a new build. Tags have not been published by this proposal.
