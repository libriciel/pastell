<?php

namespace Updater\Major4\Minor0;

use Pastell\Updater\Major4\Minor0\AddDroitDaemon;
use PastellTestCase;
use PHPUnit\Framework\TestCase;
use RoleDroit;
use RoleSQL;

class AddDroitDaemonTest extends PastellTestCase
{
    public function testAddDroitDaemonWithoutExistingDaemonRole(): void
    {
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleDroit = $this->getObjectInstancier()->getInstance(RoleDroit::class);

        $all_role = $roleSQL->getAllRole();
        foreach ($all_role as $role) {
            $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), $role['role']);
            $droit['daemon:lecture'] = false;
            $droit['daemon:edition'] = false;
            $roleSQL->updateDroit($role['role'], array_keys($droit, true));
        }

        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit['system:lecture']);
        static::assertTrue($droit['system:edition']);

        $this->getObjectInstancier()->getInstance(AddDroitDaemon::class)->update();
        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit['daemon:lecture']);
        static::assertTrue($droit['daemon:edition']);
    }

    public function testAddDroitDaemonWithExistingDaemonRole(): void
    {
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleDroit = $this->getObjectInstancier()->getInstance(RoleDroit::class);

        $all_role = $roleSQL->getAllRole();
        foreach ($all_role as $role) {
            $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), $role['role']);
            $droit['daemon:lecture'] = false;
            $droit['daemon:edition'] = false;
            $roleSQL->updateDroit($role['role'], array_keys($droit, true));
        }

        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        $droit['daemon:lecture'] = true;
        $droit['daemon:edition'] = false;
        $roleSQL->updateDroit('admin', array_keys($droit, true));
        static::assertTrue($droit['system:lecture']);
        static::assertTrue($droit['system:edition']);

        $this->getObjectInstancier()->getInstance(AddDroitDaemon::class)->update();
        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit['daemon:lecture']);
        static::assertFalse($droit['daemon:edition']);
    }
}
