<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Module;

use ConflictException;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Module\ModuleListService;
use Pastell\Service\Utilisateur\UserCreationService;
use PastellTestCase;
use RoleSQL;
use RoleUtilisateur;
use UnrecoverableException;

final class ModuleListServiceTest extends PastellTestCase
{
    private function getModuleListService(): ModuleListService
    {
        return $this->getObjectInstancier()->getInstance(ModuleListService::class);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    private function getUserWithRole(): int
    {
        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('user', 'aa@aa.fr', 'user', 'user');

        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleSQL->edit('utilisateurRole', 'Droit utilisateur');
        $roleSQL->addDroit('utilisateurRole', DroitService::getDroitFor('gfc-dossier', DroitType::LECTURE));
        $roleSQL->addDroit('utilisateurRole', DroitService::getDroitFor('pes-marche', DroitType::LECTURE));
        $roleSQL->addDroit('utilisateurRole', DroitService::getDroitFor('ls-actes', DroitType::LECTURE));
        $roleSQL->addDroit('utilisateurRole', DroitService::getDroitFor('actes-preversement-seda', DroitType::LECTURE));
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)
            ->addRole($id_u, 'utilisateurRole', 1);

        return $id_u;
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testGetModuleListOrderByNom(): void
    {
        self::assertSame(
            [
                'ls-actes' => [
                    'type' => 'Actes administratifs',
                    'nom' => 'Actes',
                ],
                'gfc-dossier' => [
                    'type' => 'Relation citoyens',
                    'nom' => 'Dossier GFC',
                ],
                'pes-marche' =>  [
                    'type' => 'Commande publique',
                    'nom' => 'PES marché',
                ],
                'actes-preversement-seda' => [
                    'type' => 'Actes administratifs',
                    'nom' => 'Reprise arriéré Actes (TdT versant)',
                ],
            ],
            $this->getModuleListService()->getModuleListOrderByNom($this->getUserWithRole())
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testGetModuleListOrderByType(): void
    {
        self::assertSame(
            [
                'Actes administratifs' => [
                    'ls-actes' => 'Actes',
                    'actes-preversement-seda' => 'Reprise arriéré Actes (TdT versant)',
                ],
                'Commande publique' => [
                    'pes-marche' => 'PES marché',
                ],
                'Relation citoyens' =>  [
                    'gfc-dossier' => 'Dossier GFC',
                ],
            ],
            $this->getModuleListService()->getModuleListOrderByType($this->getUserWithRole())
        );
    }
}
