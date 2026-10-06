<?php

declare(strict_types=1);

namespace Pastell\Service\Entite;

use EntiteSQL;
use Pastell\Validator\EntityValidator;
use UnrecoverableException;
use JournalAction;
use JournalEventType;
use Pastell\Service\Journal\JournalEntryService;

final class EntityCreationService
{
    public function __construct(
        private readonly EntiteSQL $entiteSQL,
        private readonly JournalEntryService $journal,
        private readonly EntityValidator $validator,
    ) {
    }

    /**
     * @throws UnrecoverableException
     */
    public function create(
        string $name,
        string $siren,
        string $type = EntiteSQL::TYPE_COLLECTIVITE,
        int $parent = 0,
        int $cdg = 0,
    ): int {
        $this->validator->validate($name, $siren, $type, $parent, $cdg);

        $entityId = $this->entiteSQL->create($name, $siren, $type, $parent, $cdg);

        $this->journal->add(
            JournalEventType::MODIFICATION_ENTITE,
            $entityId,
            0,
            JournalAction::CREATED->value,
            "Création de l'entité $name - $siren"
        );
        $this->entiteSQL->updateAncestor($entityId, $parent);

        return $entityId;
    }
}
