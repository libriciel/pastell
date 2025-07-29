<?php

declare(strict_types=1);

namespace Pastell\Client\IparapheurV5\Model;

class Event
{
    public const TASK_ID = 'taskId';
    public EventIdentifier $eventIdentifier;
    public string $eventType;
    public string $eventDateTime;
    public string $eventDetail;
    public EventOutcomeInformation $eventOutcomeInformation;
    public LinkingAgentIdentifier $linkingAgentIdentifier;
}
