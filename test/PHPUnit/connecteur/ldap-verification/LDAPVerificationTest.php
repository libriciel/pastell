<?php

class LDAPVerificationTest extends PastellTestCase
{
    public static function getLDAPFilter()
    {
        yield 'test with parenthesis' => ['(memberOf=pastell)'];
        yield 'test without parenthesis' => ['memberOf=pastell'];
    }

    /**
     * @throws UnrecoverableException
     * @dataProvider getLDAPFilter
     * @param string $ldap_filter
     */
    public function testGetEntry(string $ldap_filter)
    {
        $this->setLDAPWrapper();
        $id_ce = $this->createConnector('ldap-verification', 'LDAP', 0)['id_ce'];

        $this->configureConnector(
            $id_ce,
            [
                'ldap_host' => 'test.pastell',
                'ldap_port' => 689,
                'ldap_user' => "foo",
                'ldap_password' => 'bar',
                'ldap_root' => 'dc=exemple,dc=com',
                'ldap_login_attribute' => 'sAMAccountName',
                'ldap_filter' => $ldap_filter
            ],
            0
        );

        /** @var LDAPVerification $ldapVerification */
        $ldapVerification = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $this->assertEquals(
            'foo',
            $ldapVerification->getEntry("my_user")[0]
        );
    }

    /**
     * @throws Exception
     * @dataProvider getLDAPFilter
     * @throws UnrecoverableException
     */
    public function testGetEntryAllAttribute(string $ldap_filter): void
    {
        $this->setLDAPWrapper($ldap_filter);
        $id_ce = $this->createConnector('ldap-verification', 'LDAP', 0)['id_ce'];

        $this->configureConnector(
            $id_ce,
            [
                'ldap_host' => 'test.pastell',
                'ldap_port' => 689,
                'ldap_user' => "foo",
                'ldap_password' => 'bar',
                'ldap_root' => 'dc=exemple,dc=com',
                'ldap_login_attribute' => 'uid',
                'ldap_lastname_attribute' => 'uid',
                'ldap_firstname_attribute' => 'givenname',
                'ldap_email_attribute' => 'mail',
                'ldap_filter' => $ldap_filter
            ],
            0
        );

        /** @var LDAPVerification $ldapVerification */
        $ldapVerification = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        static::assertSame(
            [
                0 => [
                    'login' => 'login',
                    'prenom' => 'fighter',
                    'nom' => 'login',
                    'email' => 'foo@gmail.com',
                    'create' => true,
                    'synchronize' => true,
                ]
            ],
            $ldapVerification->getUserToCreate($this->getObjectInstancier()->getInstance(UtilisateurSQL::class))
        );
    }

    private function setLDAPWrapper(string $expectedFilter = '(&(memberOf=pastell)(sAMAccountName=my_user))'): void
    {
        $ldapWrapper = $this->getMockBuilder(LDAPWrapper::class)->getMock();
        $ldapWrapper
            ->method('ldap_search')
            ->willReturnCallback(function ($link, $base_dn, $filter) use ($expectedFilter) {
                static::assertTrue($link);
                static::assertSame('dc=exemple,dc=com', $base_dn);
                static::assertSame($expectedFilter, $filter);
                return ['test'];
            });

        $ldapWrapper
            ->method('ldap_count_entries')
            ->willReturn(1);

        $ldapWrapper
            ->method('ldap_get_entries')
            ->willReturn([
                [
                    'dn' => ['0' => 'foo'],
                    'uid' => ['0' => 'login'],
                    'mail' => ['0' => 'foo@gmail.com'],
                    'givenname' => ['0' => 'fighter']
                ]
            ]);

        $ldapWrapper
            ->method('ldap_connect')
            ->willReturn(true);

        $ldapWrapper
            ->method('ldap_bind')
            ->willReturn(true);

        $this->getObjectInstancier()->setInstance(LDAPWrapper::class, $ldapWrapper);
    }

    public function testWithoutPort()
    {
        $id_ce = $this->createConnector('ldap-verification', 'LDAP', 0)['id_ce'];
        /** @var LDAPVerification $ldapVerification */
        $ldapVerification = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Impossible de s'authentifier sur le serveur LDAP : Can't contact LDAP server");
        $ldapVerification->getConnexion();
    }

    /**
     * @throws UnrecoverableException
     * @dataProvider getLDAPFilter
     * @param string $ldap_filter
     */
    public function testGetEntryMissingUser(string $ldap_filter): void
    {
        $this->setLDAPWrapperMissingUser();
        $id_ce = $this->createConnector('ldap-verification', 'LDAP', 0)['id_ce'];
        $this->configureConnector(
            $id_ce,
            [
                'ldap_host' => 'test.pastell',
                'ldap_port' => 689,
                'ldap_user' => "foo",
                'ldap_password' => 'bar',
                'ldap_root' => 'dc=exemple,dc=com',
                'ldap_login_attribute' => 'sAMAccountName',
                'ldap_filter' => $ldap_filter
            ],
            0
        );
        /** @var LDAPVerification $ldapVerification */
        $ldapVerification = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $this->assertFalse(
            $ldapVerification->getEntry("my_user")
        );
    }

    private function setLDAPWrapperMissingUser(): void
    {
        $ldapWrapper = $this->getMockBuilder(LDAPWrapper::class)->getMock();
        $ldapWrapper
            ->method('ldap_search')
            ->willReturnCallback(function ($link, $base_dn, $filter) {
                static::assertTrue($link);
                static::assertSame('dc=exemple,dc=com', $base_dn);
                static::assertSame('(&(memberOf=pastell)(sAMAccountName=my_user))', $filter);
                return ['test'];
            });

        $ldapWrapper
            ->method('ldap_count_entries')
            ->willReturn(0);

        $ldapWrapper
            ->method('ldap_connect')
            ->willReturn(true);

        $ldapWrapper
            ->method('ldap_bind')
            ->willReturn(true);

        $this->getObjectInstancier()->setInstance(LDAPWrapper::class, $ldapWrapper);
    }
}
