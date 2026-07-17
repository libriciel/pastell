<?php

declare(strict_types=1);

namespace Mailsec;

use Libriciel\OfficeClients\Conversion\Client\Configuration\CloudoooServiceConfiguration;
use Libriciel\OfficeClients\Conversion\Client\Strategy\CloudoooStrategy;
use Libriciel\OfficeClients\Exception\ConnectionException;
use Libriciel\OfficeClients\Fusion\Client\Configuration\RestServiceConfiguration;
use Libriciel\OfficeClients\Fusion\Client\Strategy\RestStrategy;
use Libriciel\OfficeClients\Fusion\Type\PartType;

/**
 * HTTP boundary with the flow (document fusion) and cloudooo (PDF conversion)
 * services, injectable through the ObjectInstancier so tests can stub the
 * network calls.
 */
class OfficeClient
{
    /**
     * @throws ConnectionException
     */
    public function fusion(string $templatePath, PartType $main): string
    {
        $config = new RestServiceConfiguration('http://flow:8080');
        return (new RestStrategy($config))->fusion($templatePath, $main);
    }

    /**
     * @throws ConnectionException
     */
    public function convertToPdf(string $fileContent): string
    {
        return (new CloudoooStrategy(new CloudoooServiceConfiguration()))->conversion($fileContent);
    }
}
