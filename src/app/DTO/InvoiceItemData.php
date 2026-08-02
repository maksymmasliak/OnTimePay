<?php

namespace App\DTO;

final readonly class InvoiceItemData
{
    public function __construct(
        public string $description,
        public float $quantity,
        public float $unitPrice,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            description: $data['description'],
            quantity: (float) $data['quantity'],
            unitPrice: (float) $data['unit_price'],
        );
    }
}
