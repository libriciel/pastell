<?php

declare(strict_types=1);

class UserTokenSQLTest extends PastellTestCase
{
    private const TOKEN_HASH = 'my-token-hash';

    private UserTokenSQL $userTokenSQL;
    private int $id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userTokenSQL = new UserTokenSQL(self::getSQLQuery());
        $this->userTokenSQL->create(self::ID_U_ADMIN, 'token-test', self::TOKEN_HASH, '2030-01-01 00:00:00');
        $this->id = (int) $this->userTokenSQL->getInfoByToken(self::TOKEN_HASH)['id'];
    }

    public function testCreate(): void
    {
        $token_info = $this->userTokenSQL->getInfo($this->id);
        self::assertSame(self::TOKEN_HASH, $token_info['token']);
        self::assertSame('token-test', $token_info['name']);
        self::assertSame('2030-01-01 00:00:00', $token_info['expired_at']);
    }

    public function testUpdateToken(): void
    {
        $this->userTokenSQL->updateToken($this->id, 'new-token-hash');
        self::assertNull($this->userTokenSQL->getInfoByToken(self::TOKEN_HASH));
        self::assertSame($this->id, (int) $this->userTokenSQL->getInfoByToken('new-token-hash')['id']);
    }

    public function testGetTokensOfUser(): void
    {
        $tokens = $this->userTokenSQL->getTokensOfUser(self::ID_U_ADMIN);
        self::assertCount(1, $tokens);
        self::assertArrayNotHasKey('token', $tokens[0]);
    }

    public function testGetUser(): void
    {
        self::assertSame(self::ID_U_ADMIN, (int) $this->userTokenSQL->getUser($this->id)['id_u']);
        self::assertNull($this->userTokenSQL->getUser(0));
    }

    public function testGetUserFromToken(): void
    {
        $user_info = $this->userTokenSQL->getUserFromToken(self::TOKEN_HASH);
        self::assertSame(self::ID_U_ADMIN, (int) $user_info['id_u']);
        self::assertSame('2030-01-01 00:00:00', $user_info['expired_at']);
        self::assertNull($this->userTokenSQL->getUserFromToken('unknown'));
    }

    public function testDelete(): void
    {
        $this->userTokenSQL->delete($this->id);
        self::assertNull($this->userTokenSQL->getInfo($this->id));
    }

    public function testDeleteAllForUser(): void
    {
        $this->userTokenSQL->create(self::ID_U_ADMIN, 'other-token', 'other');
        $this->userTokenSQL->create(2, 'other-user-token', 'other-user');
        $this->userTokenSQL->deleteAllForUser(self::ID_U_ADMIN);
        self::assertSame([], $this->userTokenSQL->getTokensOfUser(self::ID_U_ADMIN));
        self::assertCount(1, $this->userTokenSQL->getTokensOfUser(2));
    }
}
