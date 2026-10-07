<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\PlatformPaymentMethod;
use App\Models\PlatformSetting;
use App\Notifications\PlatformPaymentMethodChanged;
use App\Services\Audit\AuditLogger;
use App\Services\Media\SecureImageStore;
use App\Services\Notifications\SafeAdminNotifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PlatformPaymentMethodController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SecureImageStore $images,
        private readonly SafeAdminNotifier $notifier,
    ) {}

    public function index()
    {
        $methods = PlatformPaymentMethod::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Admin/PlatformPayments/Index', [
            'methods' => $methods,
            'types' => PaymentMethod::values(),
            'commissionPercent' => PlatformSetting::commissionPercent(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = (int) ($data['sort_order'] ?? (PlatformPaymentMethod::max('sort_order') + 1));
        $data['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('qr')) {
            $stored = $this->images->store($request->file('qr'), 'platform-payment-qr', 'public', 'qr');
            $data['qr_path'] = $stored['path'];
        }

        unset($data['qr']);
        $method = PlatformPaymentMethod::create($data);

        $this->audit->log(
            'platform_payment_method.create',
            actor: $request->user(),
            subject: $method,
            newValues: $method->only([
                'type', 'label', 'account_holder', 'account_identifier', 'note', 'is_active',
            ]),
        );

        return back()->with('success', 'Platform payment method created.');
    }

    public function update(Request $request, PlatformPaymentMethod $platformPaymentMethod)
    {
        $data = $this->validated($request, $platformPaymentMethod);
        $data['is_active'] = $request->boolean('is_active', $platformPaymentMethod->is_active);

        $old = $platformPaymentMethod->only([
            'type', 'label', 'account_holder', 'account_identifier', 'note', 'qr_path', 'is_active',
        ]);

        if ($request->boolean('remove_qr')) {
            $platformPaymentMethod->deleteQrFile();
            $data['qr_path'] = null;
        }

        if ($request->hasFile('qr')) {
            $platformPaymentMethod->deleteQrFile();
            $stored = $this->images->store($request->file('qr'), 'platform-payment-qr', 'public', 'qr');
            $data['qr_path'] = $stored['path'];
        }

        unset($data['qr'], $data['remove_qr']);
        $platformPaymentMethod->update($data);

        $new = $platformPaymentMethod->fresh()->only([
            'type', 'label', 'account_holder', 'account_identifier', 'note', 'qr_path', 'is_active',
        ]);

        $this->audit->log(
            'platform_payment_method.update',
            actor: $request->user(),
            subject: $platformPaymentMethod,
            oldValues: $old,
            newValues: $new,
        );

        $mailQueued = $this->notifier->notify(
            $request->user(),
            new PlatformPaymentMethodChanged($old, $new),
        );

        $redirect = back()->with('success', 'Platform payment method updated.');

        if (! $mailQueued) {
            $redirect->with('warning', 'Payment method saved and audited, but the admin alert email could not be queued. Check mail/queue configuration.');
        }

        return $redirect;
    }

    public function destroy(PlatformPaymentMethod $platformPaymentMethod)
    {
        $old = $platformPaymentMethod->only([
            'type', 'label', 'account_holder', 'account_identifier', 'note', 'qr_path', 'is_active',
        ]);

        $platformPaymentMethod->deleteQrFile();
        $id = $platformPaymentMethod->id;
        $platformPaymentMethod->delete();

        $this->audit->log(
            'platform_payment_method.delete',
            actor: request()->user(),
            subject: null,
            oldValues: array_merge($old, ['id' => $id]),
        );

        return back()->with('success', 'Platform payment method removed.');
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $old = PlatformSetting::commissionPercent();
        $value = number_format((float) $data['commission_percent'], 2, '.', '');
        PlatformSetting::setValue('commission_percent', $value);

        $this->audit->log(
            'platform_settings.commission',
            actor: $request->user(),
            oldValues: ['commission_percent' => $old],
            newValues: ['commission_percent' => $value],
        );

        return back()->with('success', 'Platform settings saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PlatformPaymentMethod $existing = null): array
    {
        return $request->validate([
            'type' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'label' => ['required', 'string', 'max:120'],
            'account_holder' => ['nullable', 'string', 'max:120'],
            'account_identifier' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'remove_qr' => ['sometimes', 'boolean'],
            'qr' => [
                'nullable',
                'file',
                'max:5120',
            ],
        ]);
    }
}
