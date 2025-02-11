<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Connecteur;

use ConnecteurException;
use PastellTestCase;

class ConnecteurCreationServiceTest extends PastellTestCase
{
    public function testCreateConnecteurGlobalOnEntite(): void
    {
        $this->expectException(ConnecteurException::class);
        $this->expectExceptionMessage("Il n'est pas possible de créer un connecteur global au niveau d'une entité");
        $this->createConnector('horodateur-interne', 'Horodateur interne', 1, 1);
    }
}
