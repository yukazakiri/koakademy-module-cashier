<?php

declare(strict_types=1);

use App\Models\AdminTransaction;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Transaction;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Bus;
use Modules\Cashier\Filament\Pages\Cashier;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Bus::fake();

    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);
});

/**
 * @return array{0: User, 1: Student, 2: StudentTuition}
 */
function cashierPaymentFixture(?float $overallTuition = 5000.0, float $storedBalance = 5000.0, string $status = 'Pending'): array
{
    $course = Course::factory()->create();
    $student = Student::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 2,
    ]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_year' => 2,
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);
    $tuition = StudentTuition::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'total_tuition' => 4000,
        'total_balance' => $storedBalance,
        'total_lectures' => 3000,
        'total_laboratory' => 1000,
        'total_miscelaneous_fees' => 1000,
        'discount' => 0,
        'downpayment' => 500,
        'overall_tuition' => $overallTuition,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'academic_year' => 2,
        'status' => $status,
    ]);
    $cashier = User::factory()->create();

    return [$cashier, $student, $tuition];
}

/**
 * @param  array<string, float|int>  $settlements
 */
function postCashierPayment(User $cashier, Student $student, array $settlements): void
{
    test()->actingAs($cashier);

    $page = app(Cashier::class);
    $page->data = [
        'selectedStudent' => $student,
        'description' => 'Cashier payment',
        'selectedSchoolYear' => '2026 - 2027',
        'selectedSemester' => 1,
        'settlements' => $settlements,
        'invoicenumber' => 'OR-DCC-40-1',
        'signature' => null,
    ];

    $page->create();
}

it('recomputes the tuition balance from the ledger instead of the stale row', function (): void {
    [$cashier, $student, $tuition] = cashierPaymentFixture(storedBalance: 9999.0);

    postCashierPayment($cashier, $student, ['tuition_fee' => 2000]);

    $tuition->refresh();

    expect((float) $tuition->total_balance)->toBe(3000.0)
        ->and($tuition->status)->toBe('Downpayment')
        ->and(Transaction::query()->count())->toBe(1)
        ->and(StudentTransaction::query()->count())->toBe(1)
        ->and(AdminTransaction::query()->count())->toBe(1);

    $studentTransaction = StudentTransaction::query()->sole();
    expect($studentTransaction->student_enrollment_id)->toBe($tuition->enrollment_id);

    Notification::assertNotified('Transaction Created');
});

it('marks the tuition as paid and clears the balance on a full payment', function (): void {
    [$cashier, $student, $tuition] = cashierPaymentFixture();

    postCashierPayment($cashier, $student, ['tuition_fee' => 5000]);

    $tuition->refresh();

    expect((float) $tuition->total_balance)->toBe(0.0)
        ->and($tuition->status)->toBe('Paid')
        ->and(Transaction::query()->count())->toBe(1);
});

it('posts non-tuition settlements without requiring a tuition assessment', function (): void {
    [$cashier, $student] = cashierPaymentFixture();
    StudentTuition::query()->delete();

    postCashierPayment($cashier, $student, ['others' => 500]);

    $transaction = Transaction::query()->sole();

    expect((float) $transaction->settlements['others'])->toBe(500.0)
        ->and((float) StudentTransaction::query()->sole()->amount)->toBe(500.0)
        ->and(StudentTransaction::query()->sole()->student_enrollment_id)->toBeNull();

    Notification::assertNotified('Transaction Created');
});

it('fails safely when a tuition payment has no tuition assessment', function (): void {
    [$cashier, $student] = cashierPaymentFixture();
    StudentTuition::query()->delete();

    postCashierPayment($cashier, $student, ['tuition_fee' => 1000]);

    expect(Transaction::query()->count())->toBe(0)
        ->and(StudentTransaction::query()->count())->toBe(0)
        ->and(AdminTransaction::query()->count())->toBe(0);

    Notification::assertNotified('Transaction Not Created');
});

it('rolls back every row when the admin ledger write fails', function (): void {
    [$cashier, $student, $tuition] = cashierPaymentFixture();
    $originalBalance = (float) $tuition->total_balance;

    AdminTransaction::creating(function (): void {
        throw new RuntimeException('Simulated admin ledger failure');
    });

    expect(fn () => postCashierPayment($cashier, $student, ['tuition_fee' => 2000]))
        ->toThrow(RuntimeException::class, 'Simulated admin ledger failure');

    $tuition->refresh();

    expect((float) $tuition->total_balance)->toBe($originalBalance)
        ->and($tuition->status)->toBe('Pending')
        ->and(Transaction::query()->count())->toBe(0)
        ->and(StudentTransaction::query()->count())->toBe(0)
        ->and(AdminTransaction::query()->count())->toBe(0);
});
