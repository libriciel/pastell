<?php

declare(strict_types=1);

namespace Pastell\ViewModel;

final class DeleteConfirmation
{
    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, string|callable> $columns
     * @param array<string, scalar|array<scalar>> $formData
     */
    public function __construct(
        public readonly string $actionUrl,
        public readonly string $cancelUrl,
        public readonly array $items,
        public readonly array $columns,
        public readonly array $formData,
    ) {
    }
}
