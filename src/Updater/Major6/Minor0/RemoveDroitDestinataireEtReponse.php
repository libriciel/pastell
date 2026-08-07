<?php

declare(strict_types=1);

namespace Pastell\Updater\Major6\Minor0;

use Exception;
use Pastell\Updater\Version;
use PastellLogger;
use SQLQuery;

final class RemoveDroitDestinataireEtReponse implements Version
{
    private const string LIKE_DESTINATAIRE = '%-destinataire:%';
    private const string LIKE_REPONSE = '%-reponse:%';

    public function __construct(
        private readonly SQLQuery $sqlQuery,
        private readonly ?PastellLogger $logger = null,
    ) {
    }

    /**
     * @throws Exception
     */
    public function update(): void
    {
        $this->logger?->info('Start');

        $droits = $this->sqlQuery->query(
            'SELECT DISTINCT role, droit FROM role_droit WHERE droit LIKE ? OR droit LIKE ?',
            self::LIKE_DESTINATAIRE,
            self::LIKE_REPONSE
        );

        if (!$droits) {
            $this->logger?->info('Nothing to do. There is no obsolete destinataire/reponse droit left');
            return;
        }

        foreach ($droits as $droit) {
            $this->logger?->info(
                \sprintf('Removing obsolete droit `%s` from role `%s`', $droit['droit'], $droit['role'])
            );
        }

        $this->sqlQuery->query(
            'DELETE FROM role_droit WHERE droit LIKE ? OR droit LIKE ?',
            self::LIKE_DESTINATAIRE,
            self::LIKE_REPONSE
        );
    }
}
