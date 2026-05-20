<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use App\Notifications\Messages\FcmMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired whenever an approved deposit/withdraw/transfer/commission is
 * posted to a client's ledger. Goes to:
 *   - database channel: appears in the in-app notifications feed
 *   - fcm channel: push to all the client's registered devices
 *
 * The publisher hands us the bare data — kind, currency, amount, txn
 * number, optional purpose — and we shape it for both surfaces. We
 * never include the client's full balance here because (a) it's stale
 * the moment we serialize and (b) the app fetches /api/balances on
 * receipt anyway.
 */
class ClientTransactionPosted extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,            // 'deposit' | 'withdraw' | 'commission' | 'transfer'
        public string $currency,        // 'usd' | 'eur' | 'den' | 'cny'
        public float  $amount,          // always positive; kind disambiguates direction
        public ?string $transactionNumber = null,
        public ?string $purpose = null,
        public ?int    $sourceId = null,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'category'           => 'transaction',
            'kind'               => $this->kind,
            'currency'           => $this->currency,
            'amount'             => $this->amount,
            'transaction_number' => $this->transactionNumber,
            'purpose'            => $this->purpose,
            'source_id'          => $this->sourceId,
        ];
    }

    public function toFcm(mixed $notifiable): FcmMessage
    {
        $title = match ($this->kind) {
            'deposit'    => 'Deposit posted',
            'withdraw'   => 'Withdrawal posted',
            'commission' => 'Commission charged',
            'transfer'   => 'Transfer posted',
            default      => 'Transaction posted',
        };
        $body = sprintf('%s %s', $this->formatAmount(), strtoupper($this->currency));
        if ($this->transactionNumber) {
            $body .= ' · ' . $this->transactionNumber;
        }

        return FcmMessage::make($title, $body)->withData([
            'category'           => 'transaction',
            'kind'               => $this->kind,
            'currency'           => $this->currency,
            'amount'             => $this->amount,
            'transaction_number' => (string) $this->transactionNumber,
            'source_id'          => (string) ($this->sourceId ?? ''),
        ]);
    }

    private function formatAmount(): string
    {
        $sign = in_array($this->kind, ['withdraw', 'commission'], true) ? '−' : '+';
        return $sign . number_format($this->amount, 2);
    }
}
