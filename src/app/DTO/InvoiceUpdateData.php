<?php

namespace App\DTO;

final readonly class InvoiceUpdateData
{
    /** @param InvoiceItemData[]|null $items */
    public function __construct(
        public ?int $clientId,
        public ?string $dueDate,
        public ?array $items,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            clientId: isset($data['client_id']) ? (int) $data['client_id'] : null,
            dueDate: $data['due_date'] ?? null,
            items: isset($data['items'])
                ? array_map(
                    fn (array $item) => InvoiceItemData::fromArray($item),
                    $data['items'],
                )
                : null,
        );
    }
}
