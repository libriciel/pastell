<?php

declare(strict_types=1);

namespace Pastell\System;

class HealthCheckItem
{
    private const ERROR = 'error';
    private const INFO = 'info';
    private const SUCCESS = 'success';

    public string $label;
    public string $result;
    public ?string $description;
    private string $level;
    public ?string $expectedValue;
    /** @var HealthCheckItem[]|null */
    private ?array $details;
    private ?array $context;

    public function __construct(
        string $label,
        string $result,
        ?string $expectedValue = null,
        ?string $description = null
    ) {
        $this->label = $label;
        $this->result = $result;
        $this->level = self::INFO;
        if (isset($expectedValue)) {
            $this->expectedValue = $expectedValue;
        }
        $this->description = $description;
    }

    public function isInfo(): bool
    {
        return $this->level === self::INFO;
    }

    public function isSuccess(): bool
    {
        return $this->level === self::SUCCESS;
    }

    public function isError(): bool
    {
        return $this->level === self::ERROR;
    }

    public function setInfo(): void
    {
        $this->level = self::INFO;
    }

    public function setSuccess(bool $isSuccess): self
    {
        $this->level = $isSuccess ? self::SUCCESS : self::ERROR;
        return $this;
    }

    /**
     * @return HealthCheckItem[]|null
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    /**
     * @param HealthCheckItem[]|null $details
     */
    public function setDetails(?array $details): self
    {
        $this->details = $details;
        return $this;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): self
    {
        $this->context = $context;
        return $this;
    }
}
