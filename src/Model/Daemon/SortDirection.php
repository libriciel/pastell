<?php

declare(strict_types=1);

namespace Pastell\Model\Daemon;

enum SortDirection: string
{
    case ASC = 'asc';
    case DESC = 'desc';

    public function sql(): string
    {
        return $this === self::ASC ? 'ASC' : 'DESC';
    }

    public static function fromParam(mixed $value): self
    {
        return \is_string($value) && \strtolower($value) === self::ASC->value
            ? self::ASC
            : self::DESC;
    }
}
