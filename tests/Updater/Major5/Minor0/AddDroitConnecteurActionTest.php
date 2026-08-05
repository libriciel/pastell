<?php

declare(strict_types=1);

namespace Pastell\Tests\Updater\Major5\Minor0;

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Pastell\Updater\Major5\Minor0\AddDroitConnecteurAction;
use PastellTestCase;
use RoleDroit;
use RoleSQL;

class AddDroitConnecteurActionTest extends PastellTestCase
{
    public function testAddDroitConnecteurActionWithoutExistingRole(): void
    {
        $droit_connecteur_edition = DroitService::getDroitFor(DroitService::DROIT_CONNECTEUR, DroitType::EDITION);
        $droit_connecteur_action = DroitService::getDroitFor(DroitService::DROIT_CONNECTEUR, DroitType::ACTION);

        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleDroit = $this->getObjectInstancier()->getInstance(RoleDroit::class);

        $all_role = $roleSQL->getAllRole();
        foreach ($all_role as $role) {
            $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), $role['role']);
            $droit[$droit_connecteur_action] = false;
            $roleSQL->updateDroit($role['role'], array_keys($droit, true));
        }

        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit[$droit_connecteur_edition]);

        $this->getObjectInstancier()->getInstance(AddDroitConnecteurAction::class)->update();
        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit[$droit_connecteur_action]);
    }

    public function testAddDroitConnecteurActionWithExistingRole(): void
    {
        $droit_connecteur_edition = DroitService::getDroitFor(DroitService::DROIT_CONNECTEUR, DroitType::EDITION);
        $droit_connecteur_action = DroitService::getDroitFor(DroitService::DROIT_CONNECTEUR, DroitType::ACTION);

        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleDroit = $this->getObjectInstancier()->getInstance(RoleDroit::class);

        $all_role = $roleSQL->getAllRole();
        foreach ($all_role as $role) {
            $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), $role['role']);
            $droit[$droit_connecteur_action] = false;
            $roleSQL->updateDroit($role['role'], array_keys($droit, true));
        }

        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        $droit[$droit_connecteur_action] = true;
        $roleSQL->updateDroit('admin', array_keys($droit, true));
        static::assertTrue($droit[$droit_connecteur_edition]);

        $this->getObjectInstancier()->getInstance(AddDroitConnecteurAction::class)->update();
        $droit = $roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        static::assertTrue($droit[$droit_connecteur_action]);
    }
}
