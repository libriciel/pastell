<?php

declare(strict_types=1);

class NotificationSubscription
{
    public const ALL_ACTION = '0';

    public function __construct(
        public int $id_u,
        public int $id_e,
        public string $type,
        public string $action,
        public bool $daily_digest = false,
        public ?int $id_n = null,
        public ?string $email = null,
        public ?string $denomination = null,
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id_u'],
            (int) $row['id_e'],
            (string) $row['type'],
            (string) $row['action'],
            (bool) $row['daily_digest'],
            isset($row['id_n']) ? (int) $row['id_n'] : null,
            $row['email'] ?? null,
            $row['denomination'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id_n' => $this->id_n,
            'id_u' => $this->id_u,
            'id_e' => $this->id_e,
            'type' => $this->type,
            'action' => $this->action,
            'daily_digest' => (int) $this->daily_digest,
        ];
    }
}
