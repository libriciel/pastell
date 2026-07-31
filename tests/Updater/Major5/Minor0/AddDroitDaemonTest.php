<?php

declare(strict_types=1);

namespace Pastell\Tests\Updater\Major5\Minor0;

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Pastell\Updater\Major5\Minor0\AddDroitDaemon;
use PastellTestCase;
use RoleDroit;
use RoleSQL;

class AddDroitDaemonTest extends PastellTestCase
{
    public function testAddDroitDaemonWithoutExistingDaemonRole(): void
    {
        $droit_daemon_lecture = DroitService::getDroitFor(DroitService::DROIT_DAEMON, DroitType::LECTURE);
        $droit_daemon_edition = DroitService::getDroitFor(DroitService::DROIT_DAEMON, DroitType::EDITION);
        $droit_system_lecture = DroitService::getDroitFor(DroitService::DROIT_SYSTEM, DroitType::LECTURE);
        $droit_system_edition = DroitService::getDroitFor(DroitService::DROIT_SYSTEM, DroitType::EDITION);

        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleDroit = $this->getObjectInstancier()->getInstance(RoleDroit::class);

        $all_role = $roleSQL->getAllRole();
        foreach ($all_role as $role) {
            $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), $role['role']);
            $droit[$droit_daemon_lecture] = false;
            $droit[$droit_daemon_edition] = false;
            $roleSQL->updateDroit($role['role'], array_keys($droit, true));
        }

        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit[$droit_system_lecture]);
        static::assertTrue($droit[$droit_system_edition]);

        $this->getObjectInstancier()->getInstance(AddDroitDaemon::class)->update();
        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit[$droit_daemon_lecture]);
        static::assertTrue($droit[$droit_daemon_edition]);
    }

    public function testAddDroitDaemonWithExistingDaemonRole(): void
    {
        $droit_daemon_lecture = DroitService::getDroitFor(DroitService::DROIT_DAEMON, DroitType::LECTURE);
        $droit_daemon_edition = DroitService::getDroitFor(DroitService::DROIT_DAEMON, DroitType::EDITION);
        $droit_system_lecture = DroitService::getDroitFor(DroitService::DROIT_SYSTEM, DroitType::LECTURE);
        $droit_system_edition = DroitService::getDroitFor(DroitService::DROIT_SYSTEM, DroitType::EDITION);

        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleDroit = $this->getObjectInstancier()->getInstance(RoleDroit::class);

        $all_role = $roleSQL->getAllRole();
        foreach ($all_role as $role) {
            $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), $role['role']);
            $droit[$droit_daemon_lecture] = false;
            $droit[$droit_daemon_edition] = false;
            $roleSQL->updateDroit($role['role'], array_keys($droit, true));
        }

        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        $droit[$droit_daemon_lecture] = true;
        $droit[$droit_daemon_edition] = false;
        $roleSQL->updateDroit('admin', array_keys($droit, true));
        static::assertTrue($droit[$droit_system_lecture]);
        static::assertTrue($droit[$droit_system_edition]);

        $this->getObjectInstancier()->getInstance(AddDroitDaemon::class)->update();
        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit[$droit_daemon_lecture]);
        static::assertFalse($droit[$droit_daemon_edition]);
    }
}
