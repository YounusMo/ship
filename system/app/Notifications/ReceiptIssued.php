<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use App\Notifications\Messages\FcmMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Fired when Controller::issueReceipt() successfully writes a receipts row
 * for a client counterparty. One hook point in the base controller covers
 * every issuing path (clients deposit/withdraw/commission/transfer, plus
 * the approveReject flow that issues deferred receipts on approval).
 *
 * ShouldQueue: receipt issuance is on the user's request hot path; we
 * don't want an FCM round-trip in front of the redirect response. With
 * QUEUE_CONNECTION=database (current .env), jobs land in the jobs table
 * and php artisan queue:work drains them out-of-band.
 */
class ReceiptIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int    $receiptId,
        public string $receiptNumber,   // synthesized series_letter + series_number
        public string $kind,
        public string $currency,
        public float  $amount,
        public ?string $transactionNumber = null,
        public ?string $sourceTable = null,
        public ?int    $sourceId = null,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'category'           => 'receipt',
            'receipt_id'         => $this->receiptId,
            'receipt_number'     => $this->receiptNumber,
            'kind'               => $this->kind,
            'currency'           => $this->currency,
            'amount'             => $this->amount,
            'transaction_number' => $this->transactionNumber,
            'source_table'       => $this->sourceTable,
            'source_id'          => $this->sourceId,
        ];
    }

    public function toFcm(mixed $notifiable): FcmMessage
    {
        $title = 'Receipt ' . $this->receiptNumber;
        $body  = sprintf('%s %s · %s', number_format($this->amount, 2), strtoupper($this->currency), $this->kind);

        return FcmMessage::make($title, $body)->withData([
            'category'           => 'receipt',
            'receipt_id'         => $this->receiptId,
            'receipt_number'     => $this->receiptNumber,
            'kind'               => $this->kind,
            'currency'           => $this->currency,
            'amount'             => $this->amount,
            'transaction_number' => (string) $this->transactionNumber,
        ]);
    }
}
