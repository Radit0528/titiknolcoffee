<?php

namespace Tests\Unit;

use App\Exceptions\CheckoutException;
use App\Services\QrisService;
use PHPUnit\Framework\TestCase;

class QrisServiceTest extends TestCase
{
    private QrisService $qris;

    protected function setUp(): void
    {
        parent::setUp();
        $this->qris = new QrisService;
    }

    /** A made-up static QRIS with the same structure as a real merchant sticker. */
    public static function staticPayload(): string
    {
        $body = QrisService::tlv('00', '01')
            .QrisService::tlv('01', '11')
            .QrisService::tlv('26', QrisService::tlv('00', 'COM.EXAMPLE.WWW').QrisService::tlv('01', '936000000000000001'))
            .QrisService::tlv('51', QrisService::tlv('00', 'ID.CO.QRIS.WWW').QrisService::tlv('02', 'ID1020000000001').QrisService::tlv('03', 'UMI'))
            .QrisService::tlv('52', '5812')
            .QrisService::tlv('53', '360')
            .QrisService::tlv('58', 'ID')
            .QrisService::tlv('59', 'KEDAI CONTOH')
            .QrisService::tlv('60', 'PURWOKERTO')
            .QrisService::tlv('61', '53100')
            .QrisService::tlv('62', QrisService::tlv('07', 'A01'))
            .'6304';

        return $body.QrisService::crc16($body);
    }

    public function test_crc16_matches_the_ccitt_false_check_value(): void
    {
        $this->assertSame('29B1', QrisService::crc16('123456789'));
    }

    public function test_reads_merchant_info_from_static_qris(): void
    {
        $info = $this->qris->validateStatic(self::staticPayload());

        $this->assertSame(['merchant' => 'KEDAI CONTOH', 'city' => 'PURWOKERTO', 'nmid' => 'ID1020000000001'], $info);
    }

    public function test_adds_amount_and_switches_to_dynamic(): void
    {
        $dynamic = $this->qris->withAmount(self::staticPayload(), 25000);
        $fields = $this->qris->parse($dynamic);

        $this->assertSame('12', $fields['01']);
        $this->assertSame('25000', $fields['54']);
        $this->assertSame(QrisService::crc16(substr($dynamic, 0, -4)), $fields['63']);
        // tag 54 sits right after 53, everything else unchanged
        $this->assertSame(['00', '01', '26', '51', '52', '53', '54', '58', '59', '60', '61', '62', '63'], array_map(fn ($k) => str_pad((string) $k, 2, '0', STR_PAD_LEFT), array_keys($fields)));
        $this->assertStringContainsString('5303360540525000', $dynamic);
    }

    public function test_rejects_tampered_or_wrong_payloads(): void
    {
        $static = self::staticPayload();

        foreach ([
            substr($static, 0, -4).'0000' => 'checksum',
            str_replace('KEDAI CONTOH', 'KEDAI PALSU!', $static) => 'checksum',
            'bukan qris' => 'tidak dikenali',
            $this->qris->withAmount($static, 1000) => 'bukan QRIS statis',
        ] as $payload => $expected) {
            try {
                $this->qris->validateStatic($payload);
                $this->fail("Payload should be rejected: {$expected}");
            } catch (CheckoutException $e) {
                $this->assertStringContainsString($expected, $e->getMessage());
            }
        }
    }

    public function test_amount_must_be_positive(): void
    {
        $this->expectException(CheckoutException::class);
        $this->qris->withAmount(self::staticPayload(), 0);
    }
}
