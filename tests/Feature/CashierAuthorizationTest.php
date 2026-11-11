<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Modules\Cashier\Filament\Pages\Cashier;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Permission::findOrCreate('page_Cashier', 'web');
    Permission::findOrCreate('View:Cashier', 'web');
});

it('denies Cashier page access to unauthenticated callers', function (): void {
    Auth::logout();

    expect(Cashier::canAccess())->toBeFalse();
});

it('denies Cashier page access to users without cashier permissions', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::ITSupport,
    ]);
    Auth::login($user);

    expect(Cashier::canAccess())->toBeFalse();
});

it('allows Cashier page access to users with page_Cashier permission', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::AdministrativeAssistant,
    ]);
    $user->givePermissionTo('page_Cashier');
    Auth::login($user);

    expect(Cashier::canAccess())->toBeTrue();
});

it('allows Cashier page access to users with View:Cashier permission', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Cashier,
    ]);
    $user->givePermissionTo('View:Cashier');
    Auth::login($user);

    expect(Cashier::canAccess())->toBeTrue();
});

it('allows Cashier page access to super administrators', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::SuperAdmin,
    ]);
    Auth::login($user);

    expect(Cashier::canAccess())->toBeTrue();
});
