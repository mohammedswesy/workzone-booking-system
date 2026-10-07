<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\PlatformPaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformPaymentMethod>
 */
class PlatformPaymentMethodFactory extends Factory
{
    protected $model = PlatformPaymentMethod::class;

    public function definition(): array
    {
        return [
            'type' => PaymentMethod::BankTransfer->value,
            'label' => 'Platform Bank',
            'account_holder' => 'WorkZone Platform',
            'account_identifier' => 'PS92'.fake()->numerify('##############'),
            'note' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn () => [
            'type' => PaymentMethod::Cash->value,
            'label' => 'Cash',
            'account_identifier' => null,
        ]);
    }
}
