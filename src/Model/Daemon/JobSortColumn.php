<?php

declare(strict_types=1);

namespace Pastell\Model\Daemon;

enum JobSortColumn: string
{
    case FIRST_TRY = 'first_try';
    case LAST_TRY = 'last_try';
    case NEXT_TRY = 'next_try';

    public function label(): string
    {
        return match ($this) {
            self::FIRST_TRY => 'Premier essai',
            self::LAST_TRY => 'Dernier essai',
            self::NEXT_TRY => 'Prochain essai',
        };
    }

    public function sqlColumn(): string
    {
        return 'job_queue.' . $this->value;
    }

    public static function tryFromParam(mixed $value): ?self
    {
        return \is_string($value) ? self::tryFrom($value) : null;
    }
}
