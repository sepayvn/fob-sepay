<?php

namespace FriendsOfBotble\SePay\Http\Controllers;

use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Models\Payment;
use FriendsOfBotble\SePay\SePay;
use Illuminate\Http\Request;

class WebhookController
{
    public function __invoke(Request $request): BaseHttpResponse
    {
        if (
            ! $request->filled('id')
            || ! $request->date('transactionDate')
            || $request->input('transferType') !== 'in'
            || ! ($transferContent = $request->input('content'))
            || ! ($transferAmount = $request->float('transferAmount'))
        ) {
            return BaseHttpResponse::make()
                ->setError()
                ->setMessage('transaction invalid.');
        }

        $chargeId = SePay::getChargeIdFrom($transferContent);

        $payment = Payment::query()->where('charge_id', $chargeId)->first();

        if (! $payment
            || $payment->payment_channel->getValue() !== SEPAY_PAYMENT_METHOD_NAME
            || $transferAmount < $payment->amount) {
            return BaseHttpResponse::make()
                ->setError()
                ->setMessage('order invalid.');
        }

        $payment->status = PaymentStatusEnum::COMPLETED;
        $payment->metadata = $request->input();
        $payment->save();

        do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
            'charge_id' => $payment->charge_id,
            'order_id' => $payment->order_id,
        ]);

        return BaseHttpResponse::make()->setMessage('received.');
    }
}
