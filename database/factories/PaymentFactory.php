<?php

namespace Database\Factories;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reference = (string) Str::uuid();

        return [
            'order_id' => Order::factory(),
            'provider' => PaymentProvider::Manual,
            'provider_reference' => $reference,
            'idempotency_key' => $reference,
            'amount' => '0.00',
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
            'payload' => null,
        ];
    }
}
