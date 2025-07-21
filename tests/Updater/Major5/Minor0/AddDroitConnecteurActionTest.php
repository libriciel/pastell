<?php

declare(strict_types=1);

namespace Pastell\Tests\Updater\Major5\Minor0;

use Pastell\Updater\Major5\Minor0\AddDroitConnecteurAction;
use PastellTestCase;
use RoleDroit;
use RoleSQL;

class AddDroitConnecteurActionTest extends PastellTestCase
{
    public function testAddDroitConnecteurActionWithoutExistingRole(): void
    {
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleDroit = $this->getObjectInstancier()->getInstance(RoleDroit::class);

        $all_role = $roleSQL->getAllRole();
        foreach ($all_role as $role) {
            $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), $role['role']);
            $droit['connecteur:action'] = false;
            $roleSQL->updateDroit($role['role'], array_keys($droit, true));
        }

        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit['connecteur:edition']);

        $this->getObjectInstancier()->getInstance(AddDroitConnecteurAction::class)->update();
        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit['connecteur:action']);
    }

    public function testAddDroitConnecteurActionWithExistingRole(): void
    {
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleDroit = $this->getObjectInstancier()->getInstance(RoleDroit::class);

        $all_role = $roleSQL->getAllRole();
        foreach ($all_role as $role) {
            $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), $role['role']);
            $droit['connecteur:action'] = false;
            $roleSQL->updateDroit($role['role'], array_keys($droit, true));
        }

        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        $droit['connecteur:action'] = true;
        $roleSQL->updateDroit('admin', array_keys($droit, true));
        static::assertTrue($droit['connecteur:edition']);

        $this->getObjectInstancier()->getInstance(AddDroitConnecteurAction::class)->update();
        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit['connecteur:action']);
    }
}
