<?php

declare(strict_types=1);

namespace Pastell\Tests\Updater\Major6\Minor0;

use Pastell\Updater\Major6\Minor0\RemoveDroitDestinataireEtReponse;
use PastellTestCase;
use RoleSQL;

class RemoveDroitDestinataireEtReponseTest extends PastellTestCase
{
    public function testUpdateRemovesObsoleteDroits(): void
    {
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);

        $roleSQL->addDroit('admin', 'mailsec-bidir-destinataire:lecture');
        $roleSQL->addDroit('admin', 'mailsec-bidir-reponse:lecture');
        $roleSQL->addDroit('admin', 'entite:lecture');

        $this->getObjectInstancier()->getInstance(RemoveDroitDestinataireEtReponse::class)->update();

        static::assertSame(
            0,
            (int) $roleSQL->queryOne(
                "SELECT count(*) FROM role_droit WHERE droit LIKE ? OR droit LIKE ?",
                '%-destinataire:%',
                '%-reponse:%'
            )
        );
        static::assertSame(
            1,
            (int) $roleSQL->queryOne(
                "SELECT count(*) FROM role_droit WHERE role=? AND droit=?",
                'admin',
                'entite:lecture'
            )
        );
    }

    public function testUpdateIsIdempotent(): void
    {
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);

        $this->getObjectInstancier()->getInstance(RemoveDroitDestinataireEtReponse::class)->update();
        $this->getObjectInstancier()->getInstance(RemoveDroitDestinataireEtReponse::class)->update();

        static::assertSame(
            0,
            (int) $roleSQL->queryOne(
                "SELECT count(*) FROM role_droit WHERE droit LIKE ? OR droit LIKE ?",
                '%-destinataire:%',
                '%-reponse:%'
            )
        );
    }
}
