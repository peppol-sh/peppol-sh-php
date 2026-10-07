<?php

declare(strict_types=1);

namespace PeppolSh\Tests;

/**
 * Request payloads that agree with the shapes in `\PeppolSh\Generated\Types`,
 * for tests where the content of the payload is not the subject.
 *
 * @phpstan-import-type SendDocumentParams from \PeppolSh\Generated\Types
 */
final class Payloads
{
    private function __construct()
    {
    }

    /**
     * The smallest invoice that `documents()->send()` accepts.
     *
     * @return SendDocumentParams
     */
    public static function invoice(): array
    {
        return [
            'company_id' => 'com_1',
            'number' => 'INV-1',
            'issue_date' => '2026-01-15',
            'from' => ['name' => 'Acme BV', 'tax_id' => 'BE0123456789'],
            'to' => ['name' => 'Globex NV', 'tax_id' => 'BE0987654321'],
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 21],
            ],
        ];
    }
}
