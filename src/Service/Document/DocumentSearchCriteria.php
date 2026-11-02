<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

final class DocumentSearchCriteria
{
    /**
     * @param string[] $allowed_types
     * @param string[] $last_actions
     * @param array<string, ?string> $index_values
     */
    public function __construct(
        public readonly array $allowed_types,
        public readonly ?int $id_e = null,
        public readonly ?string $type = null,
        public readonly ?string $search = null,
        public readonly array $last_actions = [],
        public readonly ?string $last_action_begin = null,
        public readonly ?string $last_action_end = null,
        public readonly ?string $transit_action = null,
        public readonly ?string $transit_begin = null,
        public readonly ?string $transit_end = null,
        public readonly ?string $not_transit_action = null,
        public readonly array $index_values = [],
    ) {
    }
}
