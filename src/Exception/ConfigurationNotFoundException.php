<?php

declare(strict_types=1);

namespace Pastell\Exception;

use RuntimeException;

class ConfigurationNotFoundException extends RuntimeException
{
    public function __construct(string $configKey)
    {
        parent::__construct("Configuration not found for key: '{$configKey}'");
    }
}
