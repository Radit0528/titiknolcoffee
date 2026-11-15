<?php

namespace App\Services;

use App\Exceptions\CheckoutException;
use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DokuService
{
    private string $clientId;

    private string $secretKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->clientId = trim((string) config('services.doku.client_id'));
        $this->secretKey = trim((string) config('services.doku.secret_key'));
        $this->baseUrl = rtrim(trim((string) config('services.doku.base_url', 'https://api.doku.com')), '/');
    }

    public function isConfigured(): bool
    {
        return filled($this->clientId) && filled($this->secretKey);
    }

    /**
     * Create a DOKU Checkout payment session (/checkout/v1/payment).
     *
     * @return array{payment_url: string, reference: string, expires_at: Carbon}
     *
     * @throws CheckoutException
     */
    public function createCheckout(Order $order): array
    {
        if (! $this->isConfigured()) {
            throw new CheckoutException('Kredensial DOKU (DOKU_CLIENT_ID / DOKU_SECRET_KEY) belum diatur di .env.', 'method');
        }

        $targetPath = '/checkout/v1/payment';
        $requestId = (string) Str::uuid();
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $dueMinutes = 15;

        $body = [
            'order' => [
                'amount' => (int) round((float) $order->grand_total),
                'invoice_number' => $order->code,
                'currency' => 'IDR',
                'callback_url' => route('orders.show', $order),
                'auto_redirect' => true,
            ],
            'payment' => [
                'payment_due_date' => $dueMinutes,
            ],
            'customer' => [
                'name' => $order->customer_name ?: 'Pelanggan Titik Nol',
            ],
        ];

        $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES);
        $signature = $this->generatePostSignature($targetPath, $requestId, $timestamp, $jsonBody);

        try {
            $response = $this->http(20)
                ->withHeaders([
                    'Client-Id' => $this->clientId,
                    'Request-Id' => $requestId,
                    'Request-Timestamp' => $timestamp,
                    'Signature' => $signature,
                    'Content-Type' => 'application/json',
                ])
                ->withBody($jsonBody, 'application/json')
                ->post($this->baseUrl.$targetPath);
        } catch (Throwable $e) {
            throw new CheckoutException('Tidak dapat terhubung ke server DOKU: '.$e->getMessage(), 'method');
        }

        $json = $response->json();

        if (! $response->successful()) {
            $errMsg = data_get($json, 'error.message')
                ?? data_get($json, 'message.0')
                ?? data_get($json, 'message')
                ?? ('HTTP '.$response->status());

            if (is_array($errMsg)) {
                $errMsg = implode(', ', $errMsg);
            }

            throw new CheckoutException('DOKU menolak permintaan pembayaran: '.$errMsg, 'method');
        }

        $paymentUrl = data_get($json, 'response.payment.url');
        $tokenId = data_get($json, 'response.payment.token_id')
            ?? data_get($json, 'response.order.session_id')
            ?? $order->code;

        if (blank($paymentUrl)) {
            throw new CheckoutException('Respons DOKU tidak menyertakan URL pembayaran.', 'method');
        }

        return [
            'payment_url' => (string) $paymentUrl,
            'reference' => (string) $tokenId,
            'expires_at' => now()->addMinutes($dueMinutes),
        ];
    }

    /**
     * Check transaction status directly on DOKU (/orders/v1/status/{invoice_number}).
     * Returns 'SUCCESS', 'FAILED', 'EXPIRED', or 'PENDING'.
     */
    public function checkStatus(Order $order): string
    {
        if (! $this->isConfigured() || blank($order->code)) {
            return 'PENDING';
        }

        $targetPath = '/orders/v1/status/'.rawurlencode($order->code);
        $requestId = (string) Str::uuid();
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $signature = $this->generateGetSignature($targetPath, $requestId, $timestamp);

        try {
            $response = $this->http(15)
                ->withHeaders([
                    'Client-Id' => $this->clientId,
                    'Request-Id' => $requestId,
                    'Request-Timestamp' => $timestamp,
                    'Signature' => $signature,
                ])
                ->get($this->baseUrl.$targetPath);

            if (! $response->successful()) {
                return 'PENDING';
            }

            $json = $response->json();
            $txStatus = strtoupper((string) data_get($json, 'transaction.status', 'PENDING'));
            $orderStatus = strtoupper((string) data_get($json, 'order.status', ''));

            if ($txStatus === 'SUCCESS') {
                return 'SUCCESS';
            }
            if ($txStatus === 'FAILED') {
                return 'FAILED';
            }
            if ($txStatus === 'EXPIRED' || $orderStatus === 'EXPIRED') {
                return 'EXPIRED';
            }

            return 'PENDING';
        } catch (Throwable) {
            return 'PENDING';
        }
    }

    /**
     * Generate HMAC-SHA256 signature for POST requests (with Digest).
     */
    public function generatePostSignature(string $targetPath, string $requestId, string $timestamp, string $rawBody): string
    {
        $digest = base64_encode(hash('sha256', $rawBody, true));

        $component = "Client-Id:{$this->clientId}\n"
            ."Request-Id:{$requestId}\n"
            ."Request-Timestamp:{$timestamp}\n"
            ."Request-Target:{$targetPath}\n"
            ."Digest:{$digest}";

        $hmac = base64_encode(hash_hmac('sha256', $component, $this->secretKey, true));

        return "HMACSHA256={$hmac}";
    }

    /**
     * Generate HMAC-SHA256 signature for GET requests (without Digest).
     */
    public function generateGetSignature(string $targetPath, string $requestId, string $timestamp): string
    {
        $component = "Client-Id:{$this->clientId}\n"
            ."Request-Id:{$requestId}\n"
            ."Request-Timestamp:{$timestamp}\n"
            ."Request-Target:{$targetPath}";

        $hmac = base64_encode(hash_hmac('sha256', $component, $this->secretKey, true));

        return "HMACSHA256={$hmac}";
    }

    /**
     * Verify incoming HTTP Notification (Webhook) signature from DOKU.
     */
    public function verifyWebhookSignature(string $targetPath, string $clientId, string $requestId, string $timestamp, string $rawBody, string $signatureHeader): bool
    {
        if (! $this->isConfigured() || $clientId !== $this->clientId) {
            return false;
        }

        $expected = $this->generatePostSignature($targetPath, $requestId, $timestamp, $rawBody);

        return hash_equals($expected, trim($signatureHeader));
    }

    private function http(int $timeout): PendingRequest
    {
        $caInfo = (string) ini_get('curl.cainfo');
        $options = [
            'force_ip_resolve' => 'v4',
            'curl' => [
                CURLOPT_DNS_CACHE_TIMEOUT => 300,
                CURLOPT_TCP_KEEPALIVE => 1,
            ],
        ];

        if ($caInfo !== '' && ! is_file($caInfo)) {
            $laragonCa = 'D:/laragon/etc/ssl/cacert.pem';
            $options['verify'] = is_file($laragonCa) ? $laragonCa : false;
        }

        return Http::connectTimeout(15)
            ->timeout($timeout)
            ->retry(2, 250, fn ($exception) => $exception instanceof \Illuminate\Http\Client\ConnectionException, throw: false)
            ->withOptions($options);
    }
}
