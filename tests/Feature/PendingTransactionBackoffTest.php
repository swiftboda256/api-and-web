<?php

use App\Jobs\ResolvePendingTransactionJob;
use App\Models\Configuration;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\YoPaymentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->freezeSecond();
    Http::preventStrayRequests();
    config(['services.yo.base_url' => 'https://yo.test/api']);
    app()->bind(PaymentGateway::class, YoPaymentService::class);

    $user = User::factory()->create();
    $wallet = Wallet::query()->create(['user_id' => $user->id, 'balance' => 1000, 'currency_code' => 'UGX', 'status' => 'active']);
    $this->transaction = Transaction::query()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'method' => 'mobile_money', 'direction' => 'debit',
        'transaction_type' => 'withdrawal', 'amount' => 500, 'currency_code' => 'UGX',
        'gateway_reference' => 'yo-1', 'status' => 'pending',
    ]);
});

function runStatusCheck(Transaction $transaction): void
{
    app()->call([new ResolvePendingTransactionJob($transaction->id), 'handle']);
}

function yoXml(string $fields): string
{
    return "<?xml version=\"1.0\"?><AutoCreate><Response>{$fields}</Response></AutoCreate>";
}

test('unresolved checks stay pending and record what the gateway said', function (string $body, int $http, string $expected) {
    Http::fake(['*' => Http::response($body, $http)]);

    runStatusCheck($this->transaction);

    expect($this->transaction->fresh())
        ->status->toBe('pending')
        ->last_gateway_status->toBe($expected)
        ->status_check_attempts->toBe(1);
})->with([
    'pending' => [yoXml('<Status>OK</Status><TransactionStatus>PENDING</TransactionStatus>'), 200, 'pending'],
    'indeterminate' => [yoXml('<Status>OK</Status><TransactionStatus>INDETERMINATE</TransactionStatus>'), 200, 'indeterminate'],
    'indeterminate with error status' => [yoXml('<Status>ERROR</Status><StatusCode>-9</StatusCode><TransactionStatus>INDETERMINATE</TransactionStatus>'), 200, 'indeterminate'],
    'not found' => [yoXml('<Status>ERROR</Status><StatusCode>-30</StatusCode>'), 200, 'not_found'],
    'other error' => [yoXml('<Status>ERROR</Status><StatusCode>-1</StatusCode>'), 200, 'error'],
    'not xml' => ['oops', 200, 'error'],
    'server error' => ['', 503, 'unreachable'],
]);

test('a connection failure is recorded and backed off', function () {
    Http::fake(['*' => Http::failedConnection()]);
    $this->travel(10)->minutes();

    runStatusCheck($this->transaction);

    expect($this->transaction->fresh())
        ->status->toBe('pending')
        ->last_gateway_status->toBe('unreachable')
        ->next_status_check_at->getTimestamp()->toBe(now()->addMinute()->getTimestamp());
});

test('the wait grows with the transaction age', function (int $ageSeconds, int $delay) {
    Http::fake(['*' => Http::response(yoXml('<Status>OK</Status><TransactionStatus>PENDING</TransactionStatus>'))]);
    $this->travel($ageSeconds)->seconds();

    runStatusCheck($this->transaction);

    expect($this->transaction->fresh()->next_status_check_at->getTimestamp())->toBe(now()->addSeconds($delay)->getTimestamp());
})->with([
    [0, 0], [119, 0], [120, 60], [3599, 60], [3600, 900], [86399, 900], [86400, 21600],
]);

test('the command only queues transactions that are due', function () {
    Queue::fake();

    $this->artisan('transactions:resolve-pending');
    Queue::assertPushed(ResolvePendingTransactionJob::class, 1);

    // Release the first dispatch's unique lock, as a worker would.
    cache()->flush();
    $this->transaction->update(['next_status_check_at' => now()->addMinute()]);
    $this->artisan('transactions:resolve-pending');
    Queue::assertPushed(ResolvePendingTransactionJob::class, 1);

    $this->travel(1)->minutes();
    $this->artisan('transactions:resolve-pending');
    Queue::assertPushed(ResolvePendingTransactionJob::class, 2);
});

test('a backed off transaction still resolves when checked', function () {
    Http::fake(['*' => Http::response(yoXml('<Status>OK</Status><TransactionStatus>SUCCEEDED</TransactionStatus>'))]);
    $this->transaction->update(['next_status_check_at' => now()->addHours(6)]);

    runStatusCheck($this->transaction);

    expect($this->transaction->fresh()->status)->toBe('completed');
});

test('only gateway transactions pending over 24 hours are overdue', function () {
    expect(Transaction::query()->gatewayOverdue()->count())->toBe(0)
        ->and($this->transaction->isGatewayOverdue())->toBeFalse();

    $this->travel(25)->hours();

    expect(Transaction::query()->gatewayOverdue()->count())->toBe(1)
        ->and($this->transaction->isGatewayOverdue())->toBeTrue();
});

test('the stages come from configurations, ignoring invalid values', function (string $key, mixed $value, int $ageSeconds, int $delay) {
    Http::fake(['*' => Http::response(yoXml('<Status>OK</Status><TransactionStatus>PENDING</TransactionStatus>'))]);
    Configuration::query()->updateOrCreate(['key' => $key], ['value' => $value, 'value_type' => 'number']);
    $this->travel($ageSeconds)->seconds();

    runStatusCheck($this->transaction);

    expect($this->transaction->fresh()->next_status_check_at->getTimestamp())->toBe(now()->addSeconds($delay)->getTimestamp());
})->with([
    'first interval' => ['transaction_check_stage_1_interval_seconds', 5, 0, 5],
    'first stage age' => ['transaction_check_stage_1_max_age_minutes', 10, 300, 0],
    'skipped stage' => ['transaction_check_stage_1_max_age_minutes', 0, 0, 60],
    'second interval' => ['transaction_check_stage_2_interval_seconds', 30, 300, 30],
    'third interval' => ['transaction_check_stage_3_interval_seconds', 600, 7200, 600],
    'final interval' => ['transaction_check_final_interval_seconds', 3600, 90000, 3600],
    'zero final interval' => ['transaction_check_final_interval_seconds', 0, 90000, 21600],
    'non-numeric' => ['transaction_check_stage_2_interval_seconds', 'abc', 300, 60],
]);
