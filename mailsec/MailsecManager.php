<?php

declare(strict_types=1);

namespace Mailsec;

use ActionCreatorSQL;
use ActionExecutorFactory;
use DocumentCreationService;
use DocumentEmail;
use DocumentEmailReponseSQL;
use DocumentEntite;
use DocumentSQL;
use DocumentTypeFactory;
use DonneesFormulaireFactory;
use EntiteSQL;
use Journal;
use Mailsec\Exception\InvalidKeyException;
use Mailsec\Exception\UnavailableMailException;
use Mailsec\Exception\MissingPasswordException;
use Mailsec\Exception\NotEditableResponseException;
use Mailsec\Exception\UnableToExecuteActionException;
use MailSecInfo;
use NotFoundException;
use NotificationMail;
use ObjectInstancier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use UnrecoverableException;

/**
 * FIXME: This class needs to be deleted and reworked into several services
 * Symfony cannot load legacy Pastell classes as they are not PSR-4 compliant
 * It acts as a proxy between legacy Pastell classes
 */
final class MailsecManager
{
    public function __construct(
        private readonly ObjectInstancier $objectInstancier,
    ) {
    }

    /**
     * @throws MissingPasswordException
     * @throws NotFoundException
     * @throws InvalidKeyException
     * @throws UnavailableMailException
     */
    public function getMailsecInfo(string $key, Request $request, bool $checkPassword = true): MailSecInfo
    {
        $mailSecInfo = new MailSecInfo();
        $mailSecInfo->key = $key;

        $info = $this->objectInstancier->getInstance(DocumentEmail::class)->getInfoFromKey($mailSecInfo->key);
        if (!$info) {
            throw new InvalidKeyException('Unable to find key');
        }
        if ($info['non_recu']) {
            throw new UnavailableMailException('Email no longer available');
        }

        $mailSecInfo->id_de = $info['id_de'];
        $mailSecInfo->id_d = $info['id_d'];
        $mailSecInfo->type_destinataire = $info['type_destinataire'];

        $mailSecInfo->reponse = $info['reponse'];
        $mailSecInfo->has_reponse = (bool)$mailSecInfo->reponse;

        $mailSecInfo->email = $info['email'];

        $mailSecInfo->id_e = $this->objectInstancier->getInstance(DocumentEntite::class)->getEntiteWithRole(
            $mailSecInfo->id_d,
            'editeur'
        );
        $mailSecInfo->denomination_entite =
            $this->objectInstancier->getInstance(EntiteSQL::class)->getInfo($mailSecInfo->id_e)['denomination'];
        $mailSecInfo->type_document = $this->objectInstancier
            ->getInstance(DocumentSQL::class)
            ->getInfo($mailSecInfo->id_d)['type'];

        $mailSecInfo->flux_destinataire = $this->getRecipientFlux($mailSecInfo->type_document);

        $mailSecInfo->donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get(
            $mailSecInfo->id_d,
            $mailSecInfo->flux_destinataire
        );

        if ($checkPassword) {
            $this->validatePassword($mailSecInfo->donneesFormulaire, $mailSecInfo->key, $request);
        }

        $this->objectInstancier->getInstance(DocumentEmail::class)->consulter(
            $mailSecInfo->key,
            $this->objectInstancier->getInstance(Journal::class)
        );

        $this->objectInstancier->getInstance(ActionExecutorFactory::class)->executeOnDocument(
            $mailSecInfo->id_e,
            0,
            $mailSecInfo->id_d,
            'compute_read_mail'
        );
        $mailSecInfo->donneesFormulaire->getFormulaire()->setTabNumber(0);
        $mailSecInfo->fieldDataList = $mailSecInfo->donneesFormulaire->getFieldDataList('', 0);

        $mailSecInfo->flux_reponse = $this->getReplyFlux(
            $mailSecInfo->type_document,
            $mailSecInfo->type_destinataire
        );

        $mailSecInfo->has_flux_reponse = (bool)$mailSecInfo->flux_reponse;

        if ($mailSecInfo->has_flux_reponse) {
            $documentEmailReponseSQL = $this->objectInstancier->getInstance(DocumentEmailReponseSQL::class);
            $mailSecInfo->id_d_reponse = $documentEmailReponseSQL->getDocumentReponseId($mailSecInfo->id_de);

            $mailSecInfo->has_reponse = $documentEmailReponseSQL->getInfo($mailSecInfo->id_de)['has_reponse'] ?? false;

            $mailSecInfo->donneesFormulaireReponse = $this->objectInstancier
                ->getInstance(DonneesFormulaireFactory::class)
                ->get($mailSecInfo->id_d_reponse, $mailSecInfo->flux_reponse);

            $mailSecInfo->donneesFormulaireReponse->getFormulaire()->setTabNumber(0);
            $mailSecInfo->fieldDataListReponse =
                $mailSecInfo->donneesFormulaireReponse->getFieldDataList('', 0);
        }

        return $mailSecInfo;
    }

    private function getRecipientFlux(string $flux): string
    {
        $recipientFlux = $flux . '-destinataire';
        if (! $this->objectInstancier->getInstance(DocumentTypeFactory::class)->isTypePresent($recipientFlux)) {
            $recipientFlux = 'mailsec-destinataire';
        }
        return $recipientFlux;
    }

    private function getReplyFlux(string $flux, string $recipientType): string|false
    {
        $replyFlux = $flux . '-reponse';
        if (
            $recipientType !== 'to' ||
            !$this->objectInstancier->getInstance(DocumentTypeFactory::class)->isTypePresent($replyFlux)
        ) {
            $replyFlux = false;
        }
        return $replyFlux;
    }

    /**
     * @throws InvalidKeyException
     * @throws NotFoundException
     */
    public function verifyPassword(string $key, string $password): bool
    {
        $info = $this->objectInstancier->getInstance(DocumentEmail::class)->getInfoFromKey($key);
        if (!$info) {
            throw new InvalidKeyException('Unable to find key');
        }
        $typeDocument = $this->objectInstancier->getInstance(DocumentSQL::class)->getInfo($info['id_d'])['type'];
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get(
            $info['id_d'],
            $this->getRecipientFlux($typeDocument)
        );
        return $donneesFormulaire->get('password') === $password;
    }

    /**
     * @throws MissingPasswordException
     */
    private function validatePassword(\DonneesFormulaire $donneesFormulaire, string $key, Request $request): void
    {
        $ip = $request->getClientIp();
        if ($donneesFormulaire->get('password') && $request->getSession()->get("consult_ok_{$key}_{$ip}") === null) {
            throw new MissingPasswordException('Password is missing');
        }
    }

    /**
     * @throws NotEditableResponseException
     */
    public function checkResponseCanBeEdited(MailSecInfo $mailSecInfo): void
    {
        if (!$mailSecInfo->has_flux_reponse || $mailSecInfo->has_reponse) {
            throw new NotEditableResponseException();
        }
    }

    /**
     * @throws NotEditableResponseException
     * @throws TransportExceptionInterface
     * @throws UnableToExecuteActionException
     */
    public function validateResponse(MailSecInfo $mailSecInfo): void
    {
        $this->checkResponseCanBeEdited($mailSecInfo);
        /** Pour des raisons de compatibilité */
        if (
            $this->objectInstancier
                ->getInstance(DocumentTypeFactory::class)
                ->getFluxDocumentType($mailSecInfo->type_document)
                ->getAction()
                ->getActionClass('modification-reponse')
        ) {
            $result = $this->objectInstancier->getInstance(ActionExecutorFactory::class)->executeOnDocument(
                $mailSecInfo->id_e,
                -1,
                $mailSecInfo->id_d,
                'modification-reponse',
                [],
                false,
                ['mailSecInfo' => $mailSecInfo]
            );
            if (!$result) {
                throw new UnableToExecuteActionException(
                    $this->objectInstancier->getInstance(ActionExecutorFactory::class)->getLastMessage()
                );
            }
        }
        $this->objectInstancier->getInstance(ActionExecutorFactory::class)->executeOnDocument(
            $mailSecInfo->id_e,
            0,
            $mailSecInfo->id_d,
            'compute_answered_mail'
        );

        $this->objectInstancier->getInstance(ActionCreatorSQL::class)->addAction(
            $mailSecInfo->id_e,
            0,
            'validation',
            'Validation du document par ' . $mailSecInfo->email,
            $mailSecInfo->id_d_reponse
        );

        $titre = $mailSecInfo->donneesFormulaireReponse->getTitre();

        $this->objectInstancier->getInstance(Journal::class)->add(
            Journal::MAIL_SECURISE,
            $mailSecInfo->id_e,
            $mailSecInfo->id_d_reponse,
            'Validation',
            \sprintf('%s a validé le document %s (id_de = %s)', $mailSecInfo->email, $titre, $mailSecInfo->id_de)
        );

        $this->objectInstancier->getInstance(Journal::class)->add(
            Journal::MAIL_SECURISE,
            $mailSecInfo->id_e,
            $mailSecInfo->id_d,
            'Validation',
            \sprintf(
                '%s a validé une réponse pour le document %s (id_de = %s)',
                $mailSecInfo->email,
                $titre,
                $mailSecInfo->id_de
            )
        );

        $this->objectInstancier->getInstance(DocumentEmailReponseSQL::class)->validateReponse($mailSecInfo->id_de);

        $notificationMail = $this->objectInstancier->getInstance(NotificationMail::class);
        $notificationMail->notify(
            $mailSecInfo->id_e,
            $mailSecInfo->id_d,
            'reponse',
            $mailSecInfo->type_document,
            'Une réponse a été apportée à ce mail sécurisé.'
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function createDocumentResponse(MailSecInfo $mailSecInfo): MailSecInfo
    {
        $documentEmailReponseSQL = $this->objectInstancier->getInstance(DocumentEmailReponseSQL::class);
        $responseId = $documentEmailReponseSQL->getDocumentReponseId($mailSecInfo->id_de);

        if (!$responseId) {
            $documentCreationService = $this->objectInstancier->getInstance(DocumentCreationService::class);
            $responseId = $documentCreationService->createDocumentWithoutAuthorizationChecking(
                $mailSecInfo->id_e,
                $mailSecInfo->flux_reponse
            );

            $documentEmailReponseSQL = $this->objectInstancier->getInstance(DocumentEmailReponseSQL::class);
            $documentEmailReponseSQL->addDocumentReponseId($mailSecInfo->id_de, $responseId);
        }
        $mailSecInfo->id_d_reponse = $responseId;

        return $mailSecInfo;
    }
}
