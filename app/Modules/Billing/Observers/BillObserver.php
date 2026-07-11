<?php

namespace App\Modules\Billing\Observers;

use App\Modules\Billing\Integrations\BillingIntegrationSettings;
use App\Modules\Billing\Jobs\SyncBillToExternal;
use App\Modules\Billing\Models\Bill;
use App\Modules\Core\Enums\PaymentStatus;

/**
 * Fires the external-billing sync on the billing lifecycle. Catches every path
 * uniformly (web controllers, API, the Payment & Token counter) because it hooks
 * the model rather than any one controller. Keeps request-time work to a cheap
 * config check + queue dispatch.
 */
class BillObserver
{
    public function created(Bill $bill): void
    {
        $this->maybeDispatch($bill, 'bill.created');
    }

    public function updated(Bill $bill): void
    {
        if (! $bill->wasChanged('payment_status')) {
            return;
        }

        $status = $bill->payment_status instanceof PaymentStatus
            ? $bill->payment_status->value
            : $bill->payment_status;

        $event = match ($status) {
            'paid'      => 'bill.paid',
            'partial'   => 'bill.partial',
            'refunded'  => 'bill.refunded',
            'cancelled' => 'bill.cancelled',
            default     => null,
        };

        if ($event) {
            $this->maybeDispatch($bill, $event);
        }
    }

    private function maybeDispatch(Bill $bill, string $event): void
    {
        $bi = BillingIntegrationSettings::for($bill->hospital_id);
        if (! $bi || ! ($bi['enabled'] ?? false) || empty($bi['connector'])) {
            return;
        }

        // Empty events list = send all; otherwise only the selected events.
        $events = $bi['events'] ?? [];
        if (! empty($events) && ! in_array($event, $events, true)) {
            return;
        }

        SyncBillToExternal::dispatch($bill->id, $bill->hospital_id, $event);
    }
}
