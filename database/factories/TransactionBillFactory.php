<?php
namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransactionBillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory()->paid(),
            'bill_number' => 'BILL-' . Str::upper(Str::random(10)),
            'receipt_reference' => 'RCP-' . Str::upper(Str::random(10)),
            'issued_at' => now(),
            'payload' => ['note' => 'seed'],
        ];
    }
}
