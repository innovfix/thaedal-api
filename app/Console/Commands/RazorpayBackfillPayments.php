<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\User;
use App\Services\RazorpayService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RazorpayBackfillPayments extends Command
{
    protected $signature = 'razorpay:backfill {--from=} {--to=}';
    protected $description = 'Backfill captured Razorpay payments into local DB for dashboard revenue (maps by phone/email).';

    public function handle(RazorpayService $razorpay): int
    {
        $fromOpt = $this->option('from') ?: now()->subDays(30)->toDateString();
        $toOpt = $this->option('to') ?: now()->toDateString();

        $from = strtotime($fromOpt . ' 00:00:00');
        $to = strtotime($toOpt . ' 23:59:59');

        $this->info("Backfilling Razorpay payments from {$fromOpt} to {$toOpt}...");

        $count = 100;
        $skip = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        while (true) {
            $items = $razorpay->listPayments($from, $to, $count, $skip);
            if (!$items) {
                break;
            }

            foreach ($items as $p) {
                $status = $p['status'] ?? null;
                if ($status !== 'captured') {
                    continue;
                }

                $razorpayPaymentId = $p['id'] ?? null;
                $razorpayOrderId = $p['order_id'] ?? null;
                if (!$razorpayPaymentId || !$razorpayOrderId) {
                    continue;
                }

                // Use order receipt as our order_id (starts with TH...)
                try {
                    $order = $razorpay->fetchOrder($razorpayOrderId);
                } catch (\Throwable $e) {
                    $skipped++;
                    continue;
                }

                $receipt = $order['receipt'] ?? null;
                if (!$receipt || !str_starts_with($receipt, 'TH')) {
                    continue;
                }

                // find user by contact/email
                $contact = preg_replace('/\D+/', '', (string)($p['contact'] ?? ''));
                $email = $p['email'] ?? null;

                $user = null;
                if ($contact) {
                    $last10 = substr($contact, -10);
                    $user = User::where('phone_number', 'like', "%{$last10}%")->first();
                }
                if (!$user && $email) {
                    $user = User::where('email', $email)->first();
                }

                if (!$user) {
                    $skipped++;
                    continue;
                }

                $local = Payment::where('order_id', $receipt)->first();

                if ($local) {
                    $local->update([
                        'razorpay_order_id' => $razorpayOrderId,
                        'razorpay_payment_id' => $razorpayPaymentId,
                        'payment_method' => $p['method'] ?? $local->payment_method,
                        'status' => 'success',
                        'paid_at' => now(),
                    ]);
                    $updated++;
                    continue;
                }

                Payment::create([
                    'id' => Str::uuid(),
                    'user_id' => $user->id,
                    'subscription_id' => null,
                    'order_id' => $receipt,
                    'razorpay_order_id' => $razorpayOrderId,
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'razorpay_signature' => null,
                    'amount' => ($p['amount'] ?? 0) / 100,
                    'currency' => $p['currency'] ?? 'INR',
                    'status' => 'success',
                    'payment_method' => $p['method'] ?? null,
                    'description' => 'Backfilled from Razorpay',
                    'metadata' => [
                        'backfilled' => true,
                        'email' => $email,
                        'contact' => $contact,
                    ],
                    'failure_reason' => null,
                    'paid_at' => now(),
                ]);

                $created++;
            }

            $skip += $count;
        }

        $this->info("Done. created={$created}, updated={$updated}, skipped={$skipped}");
        return self::SUCCESS;
    }
}
