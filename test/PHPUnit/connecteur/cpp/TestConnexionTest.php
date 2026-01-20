<?php

declare(strict_types=1);

class TestConnexionTest extends ExtensionCppTestCase
{
    use CurlUtilitiesTestTrait;

    private const CLIENT_ID = 'client_id';
    private const MEMORY_KEY = 'pastell_token_piste_' . self::CLIENT_ID;
    private const TOKEN = 'Bearer myToken';
    protected function setUp(): void
    {
        parent::setUp();
        $this->getObjectInstancier()->getInstance(MemoryCache::class)->store(self::MEMORY_KEY, self::TOKEN);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->getObjectInstancier()->getInstance(MemoryCache::class)->delete(self::MEMORY_KEY);
    }

    public static function getConnexionProvider(): array
    {
        return [
            'OauthOK' =>
                [
                    'https://token',
                    self::CLIENT_ID,
                    'secret',
                    'https://api',
                    'login',
                    'password',
                    'La connexion est réussie',
                ],
            'OauthKONeedElement' =>
                [
                    'https://token',
                    '',
                    'secret',
                    'https://api',
                    'login',
                    'password',
                    "Il manque des éléments pour l'authentification PISTE, le connecteur global est-il bien associé ?",
                ],
            'OauthKONeedUser' =>
                [
                    'https://token',
                    self::CLIENT_ID,
                    'secret',
                    'https://api',
                    '',
                    'password',
                    'Erreur: Utilisateur sans Login/Mot de passe',
                ],
        ];
    }

    /**
     * @dataProvider getConnexionProvider
     * @throws Exception
     */
    public function testTestConnexion(
        string $url_piste_get_token,
        string $client_id,
        string $client_secret,
        string $url_piste_api,
        string $user_login,
        string $user_password,
        string $last_message_expected,
    ): void {
        $this->mockCurl(
            [
                $url_piste_api . '/cpro/transverses/v1/recuperer/tauxtva' => '{"ok":"ok"}',
                $url_piste_get_token => json_encode([
                    'token_type' => 'foo',
                    'access_token' => 'bar',
                    'expires_in' => 42
                ], JSON_THROW_ON_ERROR),

            ],
        );

        $id_ce_chorus = $this->createCppConnector('facture-cpp');
        $connecteurDonneesFormulaire = $this
            ->getDonneesFormulaireFactory()
            ->getConnecteurEntiteFormulaire($id_ce_chorus);
        $connecteurDonneesFormulaire->setData('url_piste_get_token', $url_piste_get_token);
        $connecteurDonneesFormulaire->setData('client_id', $client_id);
        $connecteurDonneesFormulaire->setData('client_secret', $client_secret);
        $connecteurDonneesFormulaire->setData('url_piste_api', $url_piste_api);
        $connecteurDonneesFormulaire->setData('user_login', $user_login);
        $connecteurDonneesFormulaire->setData('user_password', $user_password);

        $this->triggerActionOnConnector($id_ce_chorus, 'test-cpp');

        $this->assertLastMessage($last_message_expected);
    }
}
