<?php

use Pastell\Service\Entite\EntityCreationService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class PastellControlerTest extends ControlerTestCase
{
    public function testsetNavigationInfo()
    {
        $pastellControler = $this->getControlerInstance(PastellControler::class);
        $pastellControler->setNavigationInfo(0, 'test');
        $this->assertCount(1, $pastellControler->getViewParameterByKey('navigation'));
        $this->assertEquals(
            "Bourg-en-Bresse",
            $pastellControler->getViewParameterByKey('navigation')[0]['children'][0]['denomination']
        );
    }

    public function testsetNavigationInfoSubNav()
    {
        $pastellControler = $this->getControlerInstance(PastellControler::class);
        $pastellControler->setNavigationInfo(1, 'test');
        $this->assertCount(2, $pastellControler->getViewParameterByKey('navigation'));
        $this->assertEquals(
            "Bourg-en-Bresse",
            $pastellControler->getViewParameterByKey('navigation')[1]['name']
        );
    }

    public function testsetNavigationInfoSubSubNav()
    {
        $pastellControler = $this->getControlerInstance(PastellControler::class);
        $pastellControler->setNavigationInfo(2, 'test');
        $this->assertCount(3, $pastellControler->getViewParameterByKey('navigation'));
        $this->assertEquals(
            "CCAS",
            $pastellControler->getViewParameterByKey('navigation')[2]['name']
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testSetNavigationWhenUserHasNoEntiteLectureRight(): void
    {
        $entityCreationService = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $entityCreationService->create('Nouvelle entité', '000000000');

        $this->authenticateNewUserWithPermission([DroitService::getDroitFor('helios-generique', DroitType::EDITION)], 1);

        $pastellControler = $this->getObjectInstancier()->getInstance(PastellControler::class);

        $pastellControler->setNavigationInfo(0, 'test');
        static::assertCount(1, $pastellControler->getViewParameterByKey('navigation')[0]['children']);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testSetNavigationWhenUserHasNoRightAtAll(): void
    {
        $entityCreationService = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $entityCreationService->create('Nouvelle entité', '000000000');

        $this->authenticateNewUserWithPermission([DroitService::getDroitFor('helios-generique', DroitType::EDITION)], 1);

        $pastellControler = $this->getObjectInstancier()->getInstance(PastellControler::class);

        $pastellControler->setNavigationInfo(0, 'test');
        static::assertCount(1, $pastellControler->getViewParameterByKey('navigation')[0]['children']);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testSetNavigationWhenUserHasNoRightOnSecondLevel(): void
    {
        $entityCreationService = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $id_e_fille = $entityCreationService->create('Nouvelle entité', '000000000', EntiteSQL::TYPE_COLLECTIVITE, 2);

        $id_e_fille2 = $entityCreationService->create(
            'Nouvelle entité 2',
            '000000000',
            EntiteSQL::TYPE_COLLECTIVITE,
            2
        );

        $this->authenticateNewUserWithPermission([DroitService::getDroitFor('helios-generique', DroitType::EDITION)], $id_e_fille);

        $pastellControler = $this->getObjectInstancier()->getInstance(PastellControler::class);
        $pastellControler->setNavigationInfo($id_e_fille, 'test');
        static::assertCount(1, $pastellControler->getViewParameterByKey('navigation')[1]['same_level_entities']);
        static::assertEquals(
            'Nouvelle entité',
            $pastellControler->getViewParameterByKey('navigation')[1]['same_level_entities'][0]['denomination']
        );
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function testMagicLinkActiveKeepsSession(): void
    {
        $pastellControler = $this->getControlerInstance(PastellControler::class);
        $_SERVER['REQUEST_URI'] = '/';

        $magicLinkSQL = $this->getObjectInstancier()->getInstance(MagicLinkSQL::class);
        $magicLinkId = $magicLinkSQL->create(
            '11111111-1111-4111-8111-111111111111',
            1,
            'token-actif',
            '123456',
            'Intervention',
            1,
            date('Y-m-d H:i:s', strtotime('+1 day')),
            'Dupont',
            'Jean',
            'jean.dupont@example.org',
        );

        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);
        $authentification->setMagicLinkId($magicLinkId);

        $pastellControler->_beforeAction();

        static::assertTrue($authentification->isConnected());
        static::assertSame($magicLinkId, $authentification->getMagicLinkId());
    }

    public function testMagicLinkInactiveDisconnects(): void
    {
        $pastellControler = $this->getControlerInstance(PastellControler::class);
        $_SERVER['REQUEST_URI'] = '/';

        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);
        $authentification->setMagicLinkId('99999999-9999-4999-8999-999999999999');

        try {
            $pastellControler->_beforeAction();
            static::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            static::assertStringContainsString('accès temporaire a expiré ou a été révoqué', $e->getMessage());
        }

        static::assertFalse($authentification->isConnected());
    }
}
