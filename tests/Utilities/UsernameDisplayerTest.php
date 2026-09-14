<?php

declare(strict_types=1);

namespace Pastell\Tests\Utilities;

use Pastell\Helpers\UsernameDisplayer;
use PHPUnit\Framework\TestCase;

class UsernameDisplayerTest extends TestCase
{
    public static function getData(): array
    {
        return [
            'demon' => [['id_u' => 0], 'Action automatique'],
            'nothing' => [[], 'Utilisateur supprimé'],
            'only id_u' => [['id_u' => 42], 'Utilisateur supprimé #ID 42'],
            'only login' => [['login' => 'foo'], 'foo'],
            'nom prenom' => [['nom' => 'foo', 'prenom' => 'bar'], 'bar foo'],
            'login with url' => [['id_u' => 42, 'login' => 'foo'], "<a href='/Utilisateur/detail?id_u=42'>foo</a>"],
            'nom prenom with url' => [
                ['id_u' => 42, 'nom' => 'foo', 'prenom' => 'bar'],
                "<a href='/Utilisateur/detail?id_u=42'>bar foo</a>"
            ],
        ];
    }

    /**
     * @dataProvider getData
     * @param array $userInfo
     * @param string $expected
     * @return void
     */
    public function testDisplayer(array $userInfo, string $expected): void
    {
        $usernameDisplayer = new UsernameDisplayer();
        self::assertEquals(
            $expected,
            $usernameDisplayer->getUsername($userInfo),
        );
    }
}
