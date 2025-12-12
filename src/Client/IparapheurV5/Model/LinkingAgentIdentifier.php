<?php

declare(strict_types=1);

namespace Pastell\Client\IparapheurV5\Model;

class LinkingAgentIdentifier
{
    public string $linkingAgentIdentifierType;
    public string $linkingAgentIdentifierValue;
    /** @var string[]|null */
    public ?array $linkingAgentRole;
}
