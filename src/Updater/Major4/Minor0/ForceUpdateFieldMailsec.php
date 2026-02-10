<?php

declare(strict_types=1);

namespace Pastell\Updater\Major4\Minor0;

use Exception;
use Pastell\Service\UpdateFieldService;
use Pastell\Updater\Version;
use PastellLogger;

final class ForceUpdateFieldMailsec implements Version
{
    public function __construct(
        private readonly UpdateFieldService $updateFieldService,
        private readonly ?PastellLogger $logger = null,
    ) {
    }
    /**
     * Suppression de la constante MODE_MUTUALISE.
     * Lors de l'envoi d'un mail sécurisé, mailsec_from prend la valeur de PLATEFORME_MAIL.
     * Il faut lancer la commande:
     * `app:force-update-field connector mailsec mailsec_reply_to
     * "{% if mailsec_reply_to == '' %}{{mailsec_from}}{% else %}{{mailsec_reply_to}}{% endif %}"`
     * pour reporter l'ancien mailsec_from à mailsec_reply_to (s'il n'est pas déjà renseigné) #1465
     * @throws Exception
     */
    public function update(): void
    {
        $this->logger?->info('Start');

        $scope = UpdateFieldService::SCOPE_CONNECTOR;
        $scopeType = 'configuration';
        $type = "mailsec";
        $field = "mailsec_reply_to";
        $twigExpression = "{% if mailsec_reply_to == '' %}{{mailsec_from}}{% else %}{{mailsec_reply_to}}{% endif %}";

        $documents = $this->updateFieldService->getAllDocuments($scope, $type);
        $documentsNumber = count($documents);
        $this->logger?->info(
            sprintf(
                "%d %s %s `%s` to update",
                $documentsNumber,
                $scopeType,
                $scope,
                $type
            )
        );
        if ($documentsNumber === 0) {
            return;
        }

        foreach ($documents as $document) {
            $message = $this->updateFieldService->updateField($document, $scope, $field, $twigExpression);
            $this->logger?->info($message);
        }
    }
}
