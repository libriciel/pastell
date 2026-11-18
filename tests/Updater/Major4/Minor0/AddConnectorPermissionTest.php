<?php

namespace Pastell\Tests\Updater\Major4\Minor0;

use Exception;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Utilisateur\RoleService;
use Pastell\Updater\Major4\Minor0\AddConnectorPermission;
use PastellTestCase;
use RoleDroit;

class AddConnectorPermissionTest extends PastellTestCase
{
    /**
     * @throws Exception
     */
    public function testAddConnectorPermission()
    {
        $roleDroit = $this->getObjectInstancier()->getInstance(RoleDroit::class);
        $roleService = $this->getObjectInstancier()->getInstance(RoleService::class);

        $connecteur_lecture = DroitService::getDroitFor(DroitService::DROIT_CONNECTEUR, DroitType::LECTURE);
        $connecteur_edition = DroitService::getDroitFor(DroitService::DROIT_CONNECTEUR, DroitType::EDITION);

        $droit = $roleService->getDroitMap($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit[$connecteur_lecture]);
        static::assertTrue($droit[$connecteur_edition]);

        unset($droit[$connecteur_lecture]);
        unset($droit[$connecteur_edition]);
        $roleService->updateDroit('admin', array_keys($droit, true));

        $droit = $roleService->getDroitMap($roleDroit->getAllDroit(), 'admin');
        static::assertFalse($droit[$connecteur_lecture]);
        static::assertFalse($droit[$connecteur_edition]);

        $this->getObjectInstancier()->getInstance(AddConnectorPermission::class)->update();
        $droit = $roleService->getDroitMap($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit[$connecteur_lecture]);
        static::assertTrue($droit[$connecteur_edition]);
    }
}
