<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\DokuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DokuWebhookController extends Controller
{
    public function __invoke(Request $request, DokuService $doku, CheckoutService $checkout): JsonResponse
    {
        $clientId = (string) $request->header('Client-Id');
        $requestId = (string) $request->header('Request-Id');
        $timestamp = (string) $request->header('Request-Timestamp');
        $signature = (string) $request->header('Signature');
        $targetPath = '/'.ltrim($request->path(), '/');
        $rawBody = (string) $request->getContent();

        if (! $doku->verifyWebhookSignature($targetPath, $clientId, $requestId, $timestamp, $rawBody, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $invoiceNumber = (string) $request->input('order.invoice_number');
        $txStatus = strtoupper((string) $request->input('transaction.status'));

        if ($invoiceNumber === '') {
            return response()->json(['message' => 'Missing invoice_number.'], 400);
        }

        $order = Order::where('code', $invoiceNumber)->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->status === OrderStatus::Pending) {
            if ($txStatus === 'SUCCESS') {
                $checkout->confirmQrisPayment($order);
            } elseif (in_array($txStatus, ['FAILED', 'EXPIRED'], true)) {
                $checkout->cancelQrisPayment($order);
            }
        }

        return response()->json(['message' => 'OK']);
    }
}
