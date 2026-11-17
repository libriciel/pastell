<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Entite;

use Pastell\Service\Entite\EntityType;
use PHPUnit\Framework\TestCase;

final class EntityTypeTest extends TestCase
{
    public function testLabel(): void
    {
        self::assertSame('Centre de gestion', EntityType::CENTRE_DE_GESTION->label());
    }

    public function testGetLabel(): void
    {
        self::assertSame('Collectivité', EntityType::getLabel(EntityType::COLLECTIVITE->value));
    }

    public function testGetLabelUnknownType(): void
    {
        self::assertSame('unknown', EntityType::getLabel('unknown'));
    }

    public function testValues(): void
    {
        self::assertSame(['collectivite', 'centre_de_gestion'], EntityType::values());
    }
}
