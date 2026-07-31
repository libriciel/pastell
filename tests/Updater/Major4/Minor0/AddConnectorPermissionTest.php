<?php

namespace Pastell\Tests\Updater\Major4\Minor0;

use Exception;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Pastell\Updater\Major4\Minor0\AddConnectorPermission;
use PastellTestCase;
use RoleDroit;
use RoleSQL;

class AddConnectorPermissionTest extends PastellTestCase
{
    /**
     * @throws Exception
     */
    public function testAddConnectorPermission()
    {
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleDroit = $this->getObjectInstancier()->getInstance(RoleDroit::class);

        $connecteur_lecture = DroitService::getDroitFor(DroitService::DROIT_CONNECTEUR, DroitType::LECTURE);
        $connecteur_edition = DroitService::getDroitFor(DroitService::DROIT_CONNECTEUR, DroitType::EDITION);

        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit[$connecteur_lecture]);
        static::assertTrue($droit[$connecteur_edition]);

        unset($droit[$connecteur_lecture]);
        unset($droit[$connecteur_edition]);
        $roleSQL->updateDroit('admin', array_keys($droit, true));

        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertFalse($droit[$connecteur_lecture]);
        static::assertFalse($droit[$connecteur_edition]);

        $this->getObjectInstancier()->getInstance(AddConnectorPermission::class)->update();
        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit[$connecteur_lecture]);
        static::assertTrue($droit[$connecteur_edition]);
    }
}
