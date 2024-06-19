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
use Libriciel\OfficeClients\Conversion\Client\Configuration\CloudoooServiceConfiguration;
use Libriciel\OfficeClients\Conversion\Client\Strategy\CloudoooStrategy;
use Libriciel\OfficeClients\Exception\ConnectionException;
use Libriciel\OfficeClients\Fusion\Client\Configuration\RestServiceConfiguration;
use Libriciel\OfficeClients\Fusion\Client\Strategy\RestStrategy;
use Libriciel\OfficeClients\Fusion\Exception\InvalidTemplateException;
use Libriciel\OfficeClients\Fusion\Type\ContentType;
use Libriciel\OfficeClients\Fusion\Type\FieldType;
use Libriciel\OfficeClients\Fusion\Type\IterationType;
use Libriciel\OfficeClients\Fusion\Type\PartType;
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
     * @throws \Exception
     * @throws \Throwable
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

        try {
            $odtFile = $this->updateReceipt($mailSecInfo);
            $config = new CloudoooServiceConfiguration();
            $pdfFile = (new CloudoooStrategy($config))->conversion($odtFile);
            $mailSecInfo->donneesFormulaire->addFileFromData('accuse_notification', 'accuse_notification.pdf', $pdfFile);
            $mailSecInfo->donneesFormulaire->setData('lecture_mail', true);
        } catch (ConnectionException) {
        }
        return $mailSecInfo;
    }

    private function getRecipientFlux(string $flux): string
    {
        $recipientFlux = $flux . '-destinataire';
        if (!$this->objectInstancier->getInstance(DocumentTypeFactory::class)->isTypePresent($recipientFlux)) {
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
     * @throws \JsonException
     */
    public function validateResponse(MailSecInfo $mailSecInfo): void
    {
        $this->checkResponseCanBeEdited($mailSecInfo);
        /** TODO: PA 5, delete this `modification-reponse` */
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
        } else {
            $data = [];
            $indexedFields = $mailSecInfo->donneesFormulaireReponse->getFormulaire()->getIndexedFields();
            foreach ($indexedFields as $fieldId => $fieldLabel) {
                $field = $mailSecInfo->donneesFormulaireReponse->getFieldData($fieldId)->getField();
                if (!$field->isFile()) {
                    $data[$fieldLabel] = $mailSecInfo->donneesFormulaireReponse->get($fieldId);
                }
            }
            $documentEmail = $this->objectInstancier->getInstance(DocumentEmail::class);
            $documentEmail->addReponse($mailSecInfo->id_de, \json_encode($data, JSON_THROW_ON_ERROR));
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

    /**
     * @throws ConnectionException
     * @throws InvalidTemplateException
     * @throws UnrecoverableException
     */
    public function updateReceipt(MailSecInfo $info): string
    {
        $id_d = $info->id_d;
        $documentEmail = $this->objectInstancier->getInstance(DocumentEmail::class);
        $documentEmailReponseSQL = $this->objectInstancier->getInstance(DocumentEmailReponseSQL::class);
        $documentReponse = $documentEmailReponseSQL->getAllReponse($id_d);
        $recipient_list = $documentEmail->getAllRecipientIds($id_d);
        $use_template_reponse = false;
        foreach ($recipient_list as $id_de) {
            if ($documentEmailReponseSQL->getInfo($id_de)) {
                $use_template_reponse = true;
            }
        }
        if ($use_template_reponse) {
            $template_path = $this->objectInstancier->getInstance('data_dir') . '/connector/mailsec/accuse_notification_reponse_template.odt';
        } else {
            $template_path = $this->objectInstancier->getInstance('data_dir') . '/connector/mailsec/accuse_notification_simple_template.odt';
        }

        $fieldDataList = $info->donneesFormulaire->getFormulaire()->getAllFields();
        $documents_list = [];
        foreach ($fieldDataList as $fieldData) {
            if ($fieldData->getProperties('type') === 'file') {
                $value = $info->donneesFormulaire->getFieldData($fieldData->getName())->getValue();
                if ($value[0]) {
                    $documents_list[] = [
                        'champ_document' => $fieldData->getName(),
                        'libelle' => $fieldData->getProperties('name'),
                        'value' => $value
                    ];
                }
            }
        }
        $document_number = count($documents_list);

        $main = new PartType();
        $main->addElement(
            new FieldType(
                'titre',
                $info->donneesFormulaire->getFieldData('objet')->getValue()[0] ?: 'sans titre',
                'text'
            )
        );
        $main->addElement(new FieldType('type_document', $info->type_document, 'text'));
        $main->addElement(new FieldType('entite', $info->denomination_entite, 'text'));

        $main->addElement(new FieldType('nombre_documents', (string)$document_number, 'text'));
        if ($document_number > 0) {
            $table_documents = new IterationType('table_documents');
            foreach ($documents_list as $document_data) {
                foreach ($document_data['value'] as $i => $nom_document) {
                    $valuePart = new PartType();
                    $valuePart->addElement(
                        new FieldType('champ_document', $document_data['libelle'], 'text')
                    );
                    $valuePart->addElement(new FieldType('nom_document', $nom_document, 'text'));
                    $valuePart->addElement(
                        new FieldType(
                            'empreinte_document',
                            hash_file(
                                'sha256',
                                $info->donneesFormulaire->getFilePath($document_data['champ_document'], $i)
                            ),
                            'text'
                        )
                    );
                    $table_documents->addPart($valuePart);
                }
            }
            $main->addElement($table_documents);
        } else {
            $main->addElement(new IterationType('documents'));
        }

        $section_destinaires = new IterationType('table_destinataires');
        foreach ($recipient_list as $id_de) {
            $infoRecipient = $documentEmail->getInfoFromPK($id_de);
            $part = new PartType();
            $part->addElement(new FieldType('email_destinataire', $infoRecipient['email'], 'text'));
            $part->addElement(new FieldType('type_destinataire', $infoRecipient['type_destinataire'], 'text'));
            $part->addElement(new FieldType('date_envoi', $infoRecipient['date_envoie'], 'date'));
            $part->addElement(new FieldType('dernier_envoi', $infoRecipient['date_renvoi'], 'date'));
            $part->addElement(new FieldType('nombre_envois', (string)$infoRecipient['nb_renvoi'], 'text'));
            $part->addElement(
                new FieldType('lecture', ($infoRecipient['lu'] === 1) ? $infoRecipient['date_lecture'] : 'non', 'text')
            );
            if ($use_template_reponse) {
                $part->addElement(
                    new FieldType(
                        'date_reponse',
                        (isset($documentReponse[$id_de]) && $documentReponse[$id_de]['has_date_reponse'] === 1) ? $documentReponse[$id_de]['date_reponse'] : 'non',
                        'text'
                    )
                );
            }
            $section_destinaires->addPart($part);
        }
        $main->addElement($section_destinaires);

        $main->addElement(new FieldType('date', date('Y-m-d H:i:s'), 'date'));
        $main->addElement(
            new ContentType(
                'odt_content',
                'accuse_notification.odt',
                'application/vnd.oasis.opendocument.text',
                'binary',
                file_get_contents($template_path)
            )
        );
        $config = new RestServiceConfiguration('http://flow:8080');
        return (new RestStrategy($config))->fusion($template_path, $main);
    }
}
