<?php

namespace App\Http\Controllers;

use App\Models\EmployeeProfile;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canManageUsers(), 403);

        $users = User::query()
            ->with(['roles', 'employeeProfile'])
            ->orderBy('name')
            ->get();

        $records = PayrollRecord::query()
            ->with('user')
            ->latest()
            ->take(40)
            ->get();

        return Inertia::render('payroll/Index', [
            'summary' => [
                'staff' => $users->count(),
                'ready_methods' => $users->filter(fn (User $user) => filled($user->employeeProfile?->pay_method))->count(),
                'scheduled' => PayrollRecord::query()->where('status', 'scheduled')->sum('amount'),
                'sent_30_days' => PayrollRecord::query()->whereIn('status', ['sent', 'confirmed'])->where('paid_at', '>=', now()->subDays(30))->sum('amount'),
            ],
            'sendwave' => [
                'code' => 'VKTLF',
                'credit' => '$25.00',
                'url' => 'https://try.sendwave.com/kjap/y240pikp',
                'copy' => 'For overseas payroll, CreditSoft recommends Sendwave where available. The recipient must enter VKTLF to get the $25.00 first-transfer credit.',
            ],
            'staff' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_label' => $user->primaryRoleLabel(),
                'pay_method' => $user->employeeProfile?->pay_method,
                'pay_destination' => $user->employeeProfile?->pay_destination,
                'pay_currency' => $user->employeeProfile?->pay_currency ?? 'USD',
                'payroll_notes' => $user->employeeProfile?->payroll_notes,
            ]),
            'records' => $records->map(fn (PayrollRecord $record) => [
                'id' => $record->id,
                'employee_name' => $record->user?->name,
                'period_start' => optional($record->period_start)?->toDateString(),
                'period_end' => optional($record->period_end)?->toDateString(),
                'amount' => (float) $record->amount,
                'currency' => $record->currency,
                'pay_method' => $record->pay_method,
                'pay_destination' => $record->pay_destination,
                'reference' => $record->reference,
                'status' => $record->status,
                'paid_at' => optional($record->paid_at)?->toIso8601String(),
                'notes' => $record->notes,
                'created_at' => optional($record->created_at)?->toIso8601String(),
            ]),
        ]);
    }

    public function storeRecord(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canManageUsers(), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'pay_method' => ['nullable', 'string', 'max:80'],
            'pay_destination' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'scheduled', 'sent', 'confirmed'])],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        PayrollRecord::query()->create(
            collect($validated)
                ->map(fn ($value) => $value === '' ? null : $value)
                ->all(),
        );

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $validated['user_id']],
            [
                'pay_method' => $validated['pay_method'] ?: null,
                'pay_destination' => $validated['pay_destination'] ?: null,
                'pay_currency' => strtoupper($validated['currency']),
            ],
        );

        return back()->with('status', 'Payroll record saved.');
    }
}
