<?php

declare(strict_types=1);

namespace Pastell\Model\Daemon;

use Recuperateur;

final readonly class JobAdvancedFilters
{
    /**
     * @param string[] $type
     * @param string[] $job_status
     * @param string[] $id_verrou
     */
    public function __construct(
        public array $type = [],
        public array $job_status = [],
        public string $id_e = '',
        public string $include_children = '',
        public array $id_verrou = [],
        public string $late = '',
        public string $late_before = '',
    ) {
    }

    public static function fromRecuperateur(Recuperateur $recuperateur): self
    {
        return new self(
            type: (array)$recuperateur->get('job_type', []),
            job_status: (array)$recuperateur->get('job_status', []),
            id_e: self::toScalarString($recuperateur->get('search_id_e', '')),
            include_children: self::toScalarString($recuperateur->get('include_children', '')),
            id_verrou: (array)$recuperateur->get('id_verrou', []),
            late: self::toScalarString($recuperateur->get('late', '')),
            late_before: self::toDateString($recuperateur->get('late_before', '')),
        );
    }

    public function withLateBefore(string $late_before): self
    {
        return new self(...[...get_object_vars($this), 'late_before' => $late_before]);
    }

    private static function toDateString(mixed $value): string
    {
        $value = self::toScalarString($value);
        $date = \DateTimeImmutable::createFromFormat(\Date::DATE_ISO, $value);
        return $date !== false && $date->format(\Date::DATE_ISO) === $value ? $value : '';
    }

    private static function toScalarString(mixed $value): string
    {
        return \is_array($value) ? '' : (string)$value;
    }
}
