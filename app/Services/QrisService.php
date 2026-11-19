<?php

namespace App\Services;

use App\Exceptions\CheckoutException;

/**
 * Turns the shop's static QRIS (printed sticker) into a QRIS with the order
 * amount filled in, so the customer does not have to type it.
 *
 * QRIS is an EMVCo "merchant presented" payload: a flat list of
 * tag (2 digits) + length (2 digits) + value, ending with tag 63 = CRC16.
 * Conversion: tag 01 "11" (static) → "12" (dynamic), add tag 54 (amount)
 * after tag 53, drop any tip/fee tags (55–57), recompute the CRC.
 *
 * Important: the result is still the shop's own QR. The bank/e-wallet does
 * not notify SIMKOTOL, so the cashier confirms each payment manually after
 * checking the merchant app.
 */
class QrisService
{
    /**
     * Split a payload into [tag => value] (top level only, in order).
     * Note: PHP stores numeric keys such as "53" as integers; look them up
     * with string literals ($fields['53']), which PHP converts the same way.
     *
     * @return array<int|string, string>
     *
     * @throws CheckoutException when the payload is not valid TLV
     */
    public function parse(string $payload): array
    {
        $payload = trim($payload);
        $fields = [];
        $offset = 0;
        $length = strlen($payload);

        while ($offset < $length) {
            $tag = substr($payload, $offset, 2);
            $size = substr($payload, $offset + 2, 2);

            if (strlen($tag) !== 2 || ! ctype_digit($tag) || strlen($size) !== 2 || ! ctype_digit($size)) {
                throw new CheckoutException('Isi QRIS tidak dikenali. Pastikan gambar yang diunggah adalah QRIS kedai.', 'qris_static_payload');
            }

            $value = substr($payload, $offset + 4, (int) $size);

            if (strlen($value) !== (int) $size) {
                throw new CheckoutException('Isi QRIS terpotong. Coba unggah gambar yang lebih jelas.', 'qris_static_payload');
            }

            $fields[$tag] = $value;
            $offset += 4 + (int) $size;
        }

        return $fields;
    }

    /**
     * Check that a payload is a complete, unmodified static QRIS.
     *
     * @return array{merchant: string, city: string, nmid: ?string}
     *
     * @throws CheckoutException
     */
    public function validateStatic(string $payload): array
    {
        $payload = trim($payload);
        $fields = $this->parse($payload);
        $field = 'qris_static_payload';

        if (($fields['00'] ?? null) !== '01' || ! isset($fields['63'])) {
            throw new CheckoutException('Ini bukan QRIS yang valid.', $field);
        }
        if (! hash_equals(self::crc16(substr($payload, 0, -4)), strtoupper($fields['63']))) {
            throw new CheckoutException('Kode QRIS rusak (checksum tidak cocok). Unggah ulang gambar aslinya.', $field);
        }
        if (($fields['01'] ?? null) !== '11') {
            throw new CheckoutException('QRIS ini bukan QRIS statis. Gunakan QRIS stiker kedai (yang tanpa nominal).', $field);
        }
        if (($fields['53'] ?? null) !== '360' || ($fields['58'] ?? null) !== 'ID') {
            throw new CheckoutException('QRIS harus untuk Rupiah di Indonesia.', $field);
        }
        if (! isset($fields['59'])) {
            throw new CheckoutException('QRIS tidak memuat nama merchant.', $field);
        }

        preg_match('/0215(ID\d{13})/', $fields['51'] ?? '', $nmid);

        return [
            'merchant' => trim($fields['59']),
            'city' => trim($fields['60'] ?? ''),
            'nmid' => $nmid[1] ?? null,
        ];
    }

    /**
     * Static payload + amount → payload with the amount filled in.
     *
     * @throws CheckoutException
     */
    public function withAmount(string $staticPayload, int $amount): string
    {
        if ($amount < 1) {
            throw new CheckoutException('Nominal QRIS harus lebih dari 0.', 'method');
        }

        $this->validateStatic($staticPayload);
        $fields = $this->parse($staticPayload);
        unset($fields['63'], $fields['54'], $fields['55'], $fields['56'], $fields['57']);

        $fields['01'] = '12';

        $body = '';
        foreach ($fields as $tag => $value) {
            // PHP turns keys like "53" into integers, so normalise back to two digits.
            $tag = str_pad((string) $tag, 2, '0', STR_PAD_LEFT);
            $body .= self::tlv($tag, $value);
            if ($tag === '53') {
                $body .= self::tlv('54', (string) $amount);
            }
        }

        $body .= '6304';

        return $body.self::crc16($body);
    }

    public static function tlv(string $tag, string $value): string
    {
        return $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /**
     * CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF), as required by QRIS.
     */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;

        foreach (str_split($data) as $char) {
            $crc ^= ord($char) << 8;
            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }
}
