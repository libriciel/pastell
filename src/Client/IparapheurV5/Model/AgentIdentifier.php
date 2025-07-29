<?php

declare(strict_types=1);

namespace Pastell\Client\IparapheurV5\Model;

class AgentIdentifier
{
    public const USER_ID = 'userId';
    public string $agentIdentifierType;
    public string $agentIdentifierValue;
}
