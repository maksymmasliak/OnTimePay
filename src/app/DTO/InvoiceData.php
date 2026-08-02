<?php

namespace App\DTO;

final readonly class InvoiceData
{
    /** @param InvoiceItemData[] $items */
    public function __construct(
        public int $clientId,
        public string $dueDate,
        public array $items,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            clientId: (int) $data['client_id'],
            dueDate: $data['due_date'],
            items: array_map(
                fn (array $item) => InvoiceItemData::fromArray($item),
                $data['items'],
            ),
        );
    }
}
