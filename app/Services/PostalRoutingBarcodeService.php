<?php

namespace App\Services;

use App\Models\Client;
use App\Support\MailingAddress;

class PostalRoutingBarcodeService
{
    /**
     * @return array{barcode: string, symbology: string, payload: string}|null
     */
    public function forClient(Client $client): ?array
    {
        $address = MailingAddress::normalizeFields([
            'address_line_1' => $client->address_line_1,
            'city' => $client->city,
            'state' => $client->state,
            'postal_code' => $client->postal_code,
        ]);
        $payload = $this->payloadForAddress($address['postal_code'] ?? null, $address['address_line_1'] ?? null);

        if ($payload === null) {
            return null;
        }

        return [
            'barcode' => $payload['routing_code'],
            'symbology' => $payload['symbology'],
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @return array{
     *     routing_code: string,
     *     routing_code_length: int,
     *     symbology: string,
     *     delivery_point_source: string,
     *     postnet_digits: string,
     *     postnet_check_digit: int,
     *     imb_routing_code_component: string,
     *     full_imb_requires_mailer_id: bool
     * }|null
     */
    public function payloadForAddress(mixed $postalCode, mixed $addressLine1 = null): ?array
    {
        $postalDigits = preg_replace('/\D+/', '', (string) $postalCode) ?? '';

        if (strlen($postalDigits) < 5) {
            return null;
        }

        $deliveryPoint = $this->deliveryPointFromAddress($addressLine1);
        $source = 'zip';

        if (strlen($postalDigits) >= 11) {
            $routingCode = substr($postalDigits, 0, 11);
            $source = 'provided_delivery_point_routing_code';
        } elseif (strlen($postalDigits) >= 9) {
            $routingCode = substr($postalDigits, 0, 9);
            $source = 'zip4';

            if ($deliveryPoint !== null) {
                $routingCode .= $deliveryPoint;
                $source = 'zip4_plus_address_primary_number';
            }
        } else {
            $routingCode = substr($postalDigits, 0, 5);
        }

        $checkDigit = $this->postnetCheckDigit($routingCode);

        return [
            'routing_code' => $routingCode,
            'routing_code_length' => strlen($routingCode),
            'symbology' => $this->symbologyForRoutingCode($routingCode),
            'delivery_point_source' => $source,
            'postnet_digits' => $routingCode.$checkDigit,
            'postnet_check_digit' => $checkDigit,
            'imb_routing_code_component' => $routingCode,
            'full_imb_requires_mailer_id' => true,
        ];
    }

    public function deliveryPointFromAddress(mixed $addressLine1): ?string
    {
        $address = trim((string) $addressLine1);

        if ($address === '') {
            return null;
        }

        if (preg_match('/\b(?:P(?:OST)?\.?\s*O(?:FFICE)?\.?\s*BOX|P\.?\s*O\.?\s*BOX|BOX)\s*#?\s*(\d+)/i', $address, $match) === 1) {
            return str_pad(substr($match[1], -2), 2, '0', STR_PAD_LEFT);
        }

        if (preg_match('/\d+/', $address, $match) !== 1) {
            return null;
        }

        return str_pad(substr($match[0], -2), 2, '0', STR_PAD_LEFT);
    }

    public function postnetCheckDigit(string $digits): int
    {
        $sum = array_sum(array_map('intval', str_split($digits)));

        return (10 - ($sum % 10)) % 10;
    }

    protected function symbologyForRoutingCode(string $routingCode): string
    {
        return match (strlen($routingCode)) {
            11 => 'usps-delivery-point-routing-code',
            9 => 'usps-zip4-routing-code',
            default => 'usps-zip-routing-code',
        };
    }
}
