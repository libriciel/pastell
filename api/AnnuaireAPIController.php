<?php

declare(strict_types=1);

use Pastell\Service\Annuaire\AnnuaireContactService;
use Pastell\Service\Annuaire\AnnuaireExportService;
use Pastell\Service\Annuaire\AnnuaireImportService;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;

final class AnnuaireAPIController extends BaseAPIController
{
    public function __construct(
        private readonly AnnuaireSQL $annuaireSQL,
        private readonly AnnuaireContactService $annuaireContactService,
        private readonly AnnuaireImportService $annuaireImportService,
        private readonly AnnuaireExportService $annuaireExportService,
    ) {
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function get(): array
    {
        $id_a = $this->getFromQueryArgs(0);
        if ($id_a === 'export') {
            return $this->export();
        }
        if ($id_a !== false) {
            return $this->getContactInfo((int)$id_a);
        }

        $id_e = (int)$this->getFromRequest('id_e', 0);
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);

        $search = $this->getFromRequest('search', '');
        $offset = (int)$this->getFromRequest('offset', 0);
        $limit = (int)$this->getFromRequest('limit', 100);
        $id_g = (int)$this->getFromRequest('id_g', 0);

        $list = $this->annuaireSQL->getUtilisateurList($id_e, $offset, $limit, $search, $id_g ?: null);

        $result = [];
        foreach ($list as $contact) {
            $result[] = $this->formatContact($contact);
        }
        return $result;
    }

    /**
     * @throws ForbiddenException
     * @throws Exception
     */
    private function export(): array
    {
        $id_e = (int)$this->getFromRequest('id_e', 0);
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);

        $content = $this->annuaireExportService->export($id_e);

        header_wrapper('Content-type: text/csv; charset=utf-8');
        header_wrapper("Content-disposition: attachment; filename=\"pastell-annuaire-$id_e.csv\"");
        header_wrapper('Expires: 0');
        header_wrapper('Cache-Control: must-revalidate, post-check=0,pre-check=0');
        header_wrapper('Pragma: public');

        echo $content;

        exit_wrapper();
    }

    /**
     * @throws ForbiddenException
     * @throws ConflictException
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function post(): array
    {
        if ($this->getFromQueryArgs(0) === 'import') {
            return $this->import();
        }

        $id_e = (int)$this->getFromRequest('id_e', 0);
        $description = (string)$this->getFromRequest('description', '');
        $email = (string)$this->getFromRequest('email', '');

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $id_a = $this->annuaireContactService->create($id_e, $description, $email);
        return $this->getContactInfo($id_a);
    }

    /**
     * @throws ForbiddenException
     * @throws BadRequestException
     */
    private function import(): array
    {
        $id_e = (int)$this->getFromRequest('id_e', 0);
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $file_content = $this->getFileUploader()->getFileContent('csv');
        if ($file_content === false) {
            throw new BadRequestException('Fichier CSV manquant');
        }

        $file_path = tempnam(sys_get_temp_dir(), 'annuaire_import_');
        file_put_contents($file_path, $file_content);

        try {
            $nb_import = $this->annuaireImportService->import($id_e, $file_path);
        } finally {
            unlink($file_path);
        }

        return ['nb_import' => $nb_import];
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function patch(): array
    {
        $id_a = (int)$this->getFromQueryArgs(0);
        $info = $this->verifExists($id_a);

        $this->checkDroitFor((int)$info['id_e'], DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $description = (string)$this->getFromRequest('description', $info['description']);
        $email = (string)$this->getFromRequest('email', $info['email']);

        $this->annuaireContactService->edit($id_a, $description, $email);

        $result = $this->getContactInfo($id_a);
        $result['result'] = self::RESULT_OK;
        return $result;
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function delete(): array
    {
        $id_a = (int)$this->getFromQueryArgs(0);
        $info = $this->verifExists($id_a);

        $id_e = (int)$info['id_e'];
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $this->annuaireContactService->delete($id_e, $id_a);

        return ['result' => self::RESULT_OK];
    }

    /**
     * @throws NotFoundException
     */
    private function verifExists(int $id_a): array
    {
        $info = $this->annuaireSQL->getInfo($id_a);
        if (!$info) {
            throw new NotFoundException("Le contact id_a=$id_a n'existe pas");
        }
        return $info;
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    private function getContactInfo(int $id_a): array
    {
        $info = $this->verifExists($id_a);
        $this->checkDroitFor((int)$info['id_e'], DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);
        return $this->formatContact($info);
    }

    private function formatContact(array $contact): array
    {
        return [
            'id_a' => (string)$contact['id_a'],
            'id_e' => (string)$contact['id_e'],
            'description' => $contact['description'],
            'email' => $contact['email'],
        ];
    }
}
