<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use EntiteMfaObligationSQL;
use JournalEventType;
use NotFoundException;
use Pastell\Service\Entite\EntityInfoService;
use Pastell\Service\Journal\JournalEntryService;
use UnrecoverableException;
use UtilisateurSQL;

final class MfaObligationService
{
    public function __construct(
        private readonly EntiteMfaObligationSQL $entiteMfaObligationSQL,
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly MfaService $mfaService,
        private readonly UserAuthenticationService $userAuthenticationService,
        private readonly EntityInfoService $entityInfoService,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    public function isObligatory(int $id_u): bool
    {
        $user_info = $this->utilisateurSQL->getInfo($id_u);
        if (!$user_info || !empty($user_info['is_api'])) {
            return false;
        }
        if ($this->userAuthenticationService->isExternal($id_u)) {
            return false;
        }
        return $this->appliesTo((int)$user_info['id_e']);
    }

    public function mustEnroll(int $id_u): bool
    {
        return !$this->mfaService->isEnabled($id_u) && $this->isObligatory($id_u);
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function disableMfa(int $id_u, ?int $admin_id_u = null): void
    {
        if ($this->isObligatory($id_u)) {
            throw new UnrecoverableException(
                'La double authentification est obligatoire sur cette entité et ne peut pas être désactivée.'
            );
        }
        $this->mfaService->disable($id_u, $admin_id_u);
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function resetMfa(int $id_u, ?int $admin_id_u = null): void
    {
        if (!$this->utilisateurSQL->exists($id_u)) {
            throw new NotFoundException("Utilisateur $id_u inconnu");
        }
        if (!$this->isObligatory($id_u)) {
            throw new UnrecoverableException('La double authentification peut seulement être désactivée.');
        }
        $this->mfaService->reset($id_u, $admin_id_u);
    }

    /**
     * @throws UnrecoverableException
     */
    public function cancelEnrolment(int $id_u): void
    {
        if ($this->mustEnroll($id_u)) {
            throw new UnrecoverableException(
                'La double authentification est obligatoire sur cette entité et doit être configurée.'
            );
        }
        $this->mfaService->cancelEnrolment($id_u);
    }

    public function appliesTo(int $id_e): bool
    {
        return $this->entiteMfaObligationSQL->getEnforcingAncestor($id_e) !== null;
    }

    public function getEnforcingParent(int $id_e): ?int
    {
        return $this->entiteMfaObligationSQL->getEnforcingProperAncestor($id_e);
    }

    /**
     * @throws UnrecoverableException
     */
    public function enableForEntity(int $id_e, int $id_u): void
    {
        $this->assertNotInherited($id_e);
        if ($this->entiteMfaObligationSQL->isDirectlySet($id_e)) {
            return;
        }
        $this->entiteMfaObligationSQL->enable($id_e);
        $this->addJournalEntry($id_e, $id_u, 'activée');
    }

    /**
     * @throws UnrecoverableException
     */
    public function disableForEntity(int $id_e, int $id_u): void
    {
        $this->assertNotInherited($id_e);
        if (!$this->entiteMfaObligationSQL->isDirectlySet($id_e)) {
            return;
        }
        $this->entiteMfaObligationSQL->disable($id_e);
        $this->addJournalEntry($id_e, $id_u, 'désactivée');
    }

    /**
     * @throws UnrecoverableException
     */
    private function assertNotInherited(int $id_e): void
    {
        if ($this->getEnforcingParent($id_e) !== null) {
            throw new UnrecoverableException(
                "L'obligation de double authentification est imposée par une entité mère "
                . 'et ne peut pas être modifiée ici.'
            );
        }
    }

    private function addJournalEntry(int $id_e, int $id_u, string $state): void
    {
        $denomination = $this->entityInfoService->getDenomination($id_e);
        $this->journalEntryService->addSQL(
            JournalEventType::ENTITY_MODIFICATION,
            $id_e,
            $id_u,
            JournalEntryService::NO_ID_D,
            "obligation de double authentification $state",
            "L'obligation de double authentification a été $state sur l'entité $denomination ($id_e)"
        );
    }
}
