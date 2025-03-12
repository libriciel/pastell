<?php

declare(strict_types=1);

namespace Pastell\Service\Entite;

use EntiteSQL;
use Journal;
use Pastell\Validator\EntityValidator;
use UnrecoverableException;

final class EntityUpdateService
{
    public function __construct(
        private readonly EntiteSQL $entiteSQL,
        private readonly Journal $journal,
        private readonly EntityValidator $validator,
    ) {
    }

    /**
     * @throws UnrecoverableException
     */
    public function update(
        int $entityId,
        string $name,
        string $siren,
        string $type = EntiteSQL::TYPE_COLLECTIVITE,
        int $parent = 0,
        int $cdg = 0
    ): void {
        $this->validator->validate($name, $siren, $type, $parent, $cdg);

        $oldEntity = $this->entiteSQL->getInfo($entityId);
        $this->validator->checkEntiteMere((int)$oldEntity['entite_mere'], $parent);
        $this->entiteSQL->update($entityId, $name, $siren, $type, $parent, $cdg);

        $newEntity = $this->entiteSQL->getInfo($entityId);
        $infoToRetrieve = ['siren','denomination','type','entite_mere','centre_de_gestion'];

        $infoChanged = [];
        foreach ($infoToRetrieve as $key) {
            if ($oldEntity[$key] !== $newEntity[$key]) {
                $infoChanged[] = sprintf('%s : %s -> %s', $key, $oldEntity[$key], $newEntity[$key]);
            }
        }
        $infoChanged  = implode('; ', $infoChanged);

        $this->journal->add(
            Journal::MODIFICATION_ENTITE,
            $entityId,
            0,
            Journal::ACTION_MODIFFIE,
            "Modification de l'entité $name ($entityId) : $infoChanged"
        );
        $this->entiteSQL->updateAncestor($entityId, $parent);
    }
}
