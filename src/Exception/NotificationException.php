<?php

declare(strict_types=1);

namespace Pastell\Exception;

use Throwable;
use UnrecoverableException;

class NotificationException extends UnrecoverableException
{
    public function __construct(
        string $message,
        private readonly string $subject,
        private readonly ?string $htmlTemplate = null,
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getHtmlTemplate(): ?string
    {
        return $this->htmlTemplate;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
