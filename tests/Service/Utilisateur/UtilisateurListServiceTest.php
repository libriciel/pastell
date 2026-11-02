<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Pastell\Service\Utilisateur\UtilisateurListService;
use PastellTestCase;

class UtilisateurListServiceTest extends PastellTestCase
{
    public function testGetAllWithRoles(): void
    {
        $all = $this->getObjectInstancier()->getInstance(UtilisateurListService::class)->getAllWithRoles(0);
        static::assertNotEmpty($all);
        static::assertArrayHasKey('libelle', $all[0]['all_role'][0]);
    }

    public function testGetAllWithSearch(): void
    {
        static::assertSame(
            [],
            $this->getObjectInstancier()->getInstance(UtilisateurListService::class)
                ->getAllWithRoles(0, true, '', 'inexistant')
        );
    }
}
