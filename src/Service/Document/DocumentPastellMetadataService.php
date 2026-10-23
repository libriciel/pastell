<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

use DocumentActionSQL;
use DocumentSQL;

class DocumentPastellMetadataService
{
    public const PA_ENTITY_ID_E = 'pa_entity_id_e';
    public const PA_ENTITY_NAME = 'pa_entity_name';
    public const PA_ENTITY_SIREN = 'pa_entity_siren';
    public const PA_CREATOR_LASTNAME = 'pa_creator_lastname';
    public const PA_CREATOR_FIRSTNAME = 'pa_creator_firstname';
    public const PA_CREATOR_EMAIL = 'pa_creator_email';
    public const PA_CREATOR_LOGIN = 'pa_creator_login';
    public const PA_CREATOR_ID_U = 'pa_creator_id_u';
    public const PA_DOCUMENT_CREATION_DATE = 'pa_document_creation_date';
    public const PA_DOCUMENT_TITRE = 'pa_document_titre';
    public const PA_DOCUMENT_ID_D = 'pa_document_id_d';

    public function __construct(
        private readonly DocumentSQL $documentSQL,
        private readonly DocumentEntiteService $documentEntiteService,
        private readonly DocumentActionSQL $documentActionSQL,
    ) {
    }

    public static function getPastellMetadataDescription(): array
    {
        return [
            self::PA_DOCUMENT_ID_D => 'Identifiant du document',
            self::PA_DOCUMENT_TITRE => 'Titre du document',
            self::PA_DOCUMENT_CREATION_DATE => 'Date de création du document (de la forme "2024-04-25 15:21:14")',
            self::PA_CREATOR_ID_U => "Identifiant de l'utilisateur ayant créé le document",
            self::PA_CREATOR_LASTNAME => "Nom de l'utilisateur ayant créé le document",
            self::PA_CREATOR_FIRSTNAME => "Prénom de l'utilisateur ayant créé le document",
            self::PA_CREATOR_EMAIL => "Email de l'utilisateur ayant créé le document",
            self::PA_CREATOR_LOGIN => "Login de l'utilisateur ayant créé le document",
            self::PA_ENTITY_ID_E => "Identifiant numérique de l'entité Pastell dans laquelle le document a été créé",
            self::PA_ENTITY_NAME => "Nom de l'entité Pastell dans laquelle le document a été créé",
            self::PA_ENTITY_SIREN => "SIREN de l'entité Pastell dans laquelle le document a été créé",
        ];
    }

    public function getMetadataPastellByDocument(?string $id_d): array
    {
        $metadata = [];

        if ($id_d !== null) {
            $documentInfo = $this->documentSQL->getInfo($id_d);
            $userInfo = $this->documentActionSQL->getCreator($id_d);
            $entiteInfo = $this->documentEntiteService->getEntites($id_d)[0] ?? [];
        }
        $metadata[self::PA_DOCUMENT_ID_D] = $id_d ?? '';
        $metadata[self::PA_DOCUMENT_TITRE] = $documentInfo['titre'] ?? '';
        $metadata[self::PA_DOCUMENT_CREATION_DATE] = $documentInfo['creation'] ?? '';
        $metadata[self::PA_CREATOR_ID_U] = $userInfo['id_u'] ?? '';
        $metadata[self::PA_CREATOR_LASTNAME] = $userInfo['nom'] ?? '';
        $metadata[self::PA_CREATOR_FIRSTNAME] = $userInfo['prenom'] ?? '';
        $metadata[self::PA_CREATOR_EMAIL] = $userInfo['email'] ?? '';
        $metadata[self::PA_CREATOR_LOGIN] = $userInfo['login'] ?? '';
        $metadata[self::PA_ENTITY_ID_E] = $entiteInfo['id_e'] ?? '';
        $metadata[self::PA_ENTITY_NAME] = $entiteInfo['denomination'] ?? '';
        $metadata[self::PA_ENTITY_SIREN] = $entiteInfo['siren'] ?? '';

        return $metadata;
    }
}
