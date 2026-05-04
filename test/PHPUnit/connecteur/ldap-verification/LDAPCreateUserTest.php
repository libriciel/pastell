<?php

class LDAPCreateUserTest extends PastellTestCase
{
    public function testSynchronizeUpdatesNotificationDigestEmail(): void
    {
        $notificationDigestSQL = $this->getObjectInstancier()->getInstance(NotificationDigestSQL::class);
        $notificationDigestSQL->add('eric@sigmalis.com', 1, 'id-d-fake', 'action-fake', 'type-fake', 'message-fake');

        $id_ce = $this->createConnector('ldap-verification', 'LDAP', 0)['id_ce'];

        $ldapVerification = $this->createMock(LDAPVerification::class);
        $ldapVerification->method('getUserToCreate')->willReturn([
            [
                'create' => false,
                'synchronize' => true,
                'id_u' => 1,
                'login' => 'admin',
                'email' => 'new@example.com',
                'nom' => 'Pommateau',
                'prenom' => 'Eric',
            ],
        ]);

        $connecteurFactory = $this->createMock(ConnecteurFactory::class);
        $connecteurFactory->method('getConnecteurById')->willReturn($ldapVerification);
        $this->getObjectInstancier()->setInstance(ConnecteurFactory::class, $connecteurFactory);

        $ldapCreateUser = new LDAPCreateUser($this->getObjectInstancier());
        $ldapCreateUser->setConnecteurId('ldap-verification', $id_ce);
        $ldapCreateUser->go();

        $all = $notificationDigestSQL->getAll();
        self::assertArrayHasKey('new@example.com', $all);
        self::assertArrayNotHasKey('eric@sigmalis.com', $all);
    }
}
