<?php

namespace Pastell\Tests\Service\Droit;

use NotFoundException;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;
use PastellTestCase;

class DroitServiceTest extends PastellTestCase
{
    protected function tearDown(): void
    {
        $this->setListPack(['suppl_test' => true]);
    }

    /**
     * @throws NotFoundException
     */
    public function testHasDroitConnecteur(): void
    {
        $droitService = $this->getObjectInstancier()->getInstance(DroitService::class);
        static::assertTrue($droitService->hasDroitFor(1, 1, DroitService::DROIT_CONNECTEUR, DroitType::EDITION));
        static::assertTrue($droitService->hasDroitFor(1, 1, DroitService::DROIT_CONNECTEUR, DroitType::LECTURE));
    }

    /**
     * @throws NotFoundException
     */
    public function testHasDroitUtilisateur(): void
    {
        $droitService = $this->getObjectInstancier()->getInstance(DroitService::class);
        static::assertTrue($droitService->hasDroitFor(1, 1, DroitService::DROIT_UTILISATEUR, DroitType::LECTURE));
    }

    /**
     * @throws NotFoundException
     */
    public function testHasDroitNoUser(): void
    {
        $droitService = $this->getObjectInstancier()->getInstance(DroitService::class);
        static::assertTrue($droitService->hasDroitFor(0, 1, DroitService::DROIT_CONNECTEUR, DroitType::EDITION));
        static::assertTrue($droitService->hasDroitFor(0, 1, DroitService::DROIT_UTILISATEUR, DroitType::LECTURE));
    }

    /**
     * @throws NotFoundException
     */
    public function testPackEnableDroit(): void
    {
        $droitService = $this->getObjectInstancier()->getInstance(DroitService::class);
        $droit_id = 'test';
        $droit_test_lecture = DroitService::getDroitFor($droit_id, DroitType::LECTURE);

        $this->setListPack(['suppl_test' => false]);
        static::assertTrue($droitService->isRestrictedDroit($droit_test_lecture));
        static::assertTrue($droitService->isRestrictedConnecteur('test', true));
        static::assertTrue($droitService->isRestrictedConnecteur('test'));
        static::assertFalse($droitService->hasDroitFor(1, 1, $droit_id, DroitType::LECTURE));
        static::assertFalse($droitService->hasOneDroit(1, $droit_test_lecture));
        static::assertNotContains('test', $droitService->getAllDocumentLecture(1, 1));
        static::assertNotContains($droit_test_lecture, $droitService->getAllDroitEntite(1, 1));
        static::assertNotContains($droit_test_lecture, $droitService->getAllDroit(1));

        $this->setListPack(['suppl_test' => true]);
        static::assertFalse($droitService->isRestrictedDroit($droit_test_lecture));
        static::assertFalse($droitService->isRestrictedConnecteur('test', true));
        static::assertFalse($droitService->isRestrictedConnecteur('test'));
        static::assertTrue($droitService->hasDroitFor(1, 1, $droit_id, DroitType::LECTURE));
        static::assertTrue($droitService->hasOneDroit(1, $droit_test_lecture));
        static::assertContains('test', $droitService->getAllDocumentLecture(1, 1));
        static::assertContains($droit_test_lecture, $droitService->getAllDroitEntite(1, 1));
        static::assertContains($droit_test_lecture, $droitService->getAllDroit(1));
    }
}
