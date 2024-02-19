<?php

use Pastell\File\Chunk\ChunkUploader;
use Pastell\Viewer\ViewerFactory;

class DonneesFormulaireControler extends PastellControler
{
    /**
     * @param $id_e
     * @param $id_d
     * @param $id_ce
     * @throws Exception
     */
    private function verifDroitEditionOnDocumentOrConnecteur($id_e, $id_d, $id_ce)
    {
        if ($id_d) {
            // Si l'id_d est un document_email_reponse alors on vérifie les droits sur le document_email, issue #1703
            $mail_info = $this->getDocumentEmailService()->getDocumentEmailFromIdReponse($id_d);
            $info = (!empty($mail_info)) ? $mail_info : $this->getDocumentSQL()->getInfo($id_d);
            if (
                !$this->getDroitService()->hasDroit(
                    $this->getId_u(),
                    $this->getDroitService()->getDroitEdition($info['type']),
                    $id_e
                )
            ) {
                if (!$this->isDocumentEmailChunkUpload()) {
                    echo "KO";
                    exit_wrapper();
                }
            }
        } elseif ($id_ce) {
            if (!$this->getDroitService()->hasDroitConnecteurEdition($id_e, $this->getId_u())) {
                echo "KO";
                exit_wrapper();
            }
        } else {
            throw new Exception("id_d ou id_ce est obligatoire");
        }
    }

    /**
     * @param $id_e
     * @param $id_d
     * @param $id_ce
     * @throws Exception
     */
    private function verifDroitLectureOnDocumentOrConnecteur($id_e, $id_d, $id_ce)
    {
        if ($id_d) {
            // Si l'id_d est un document_email_reponse alors on vérifie les droits sur le document_email, issue #1703
            $mail_info = $this->getDocumentEmailService()->getDocumentEmailFromIdReponse($id_d);
            $info = (!empty($mail_info)) ? $mail_info : $this->getDocumentSQL()->getInfo($id_d);
            if (
                !$this->getDroitService()->hasDroit(
                    $this->getId_u(),
                    $this->getDroitService()->getDroitLecture($info['type']),
                    $id_e
                )
            ) {
                if (!$this->isDocumentEmailChunkUpload()) {
                    echo "KO";
                    exit_wrapper();
                }
            }
        } elseif ($id_ce) {
            if (!$this->getDroitService()->hasDroitConnecteurLecture($id_e, $this->getId_u())) {
                echo "KO";
                exit_wrapper();
            }
        } else {
            throw new Exception("id_d ou id_ce est obligatoire");
        }
    }

    /**
     * @throws Exception
     */
    public function downloadAllAction()
    {
        $getInfo = $this->getGetInfo();
        $id_e = $getInfo->getInt('id_e');
        $id_d = $getInfo->get('id_d');
        $id_ce = $getInfo->get('id_ce');
        $field = $getInfo->get('field');

        $this->verifDroitEditionOnDocumentOrConnecteur($id_e, $id_d, $id_ce);
        $this->downloadAll($id_e, $id_d, $id_ce, $field);
    }

    /**
     * @throws Exception
     */
    public function downloadAll($id_e, $id_d, $id_ce, $field)
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getFromDocumentOrConnecteur($id_d, $id_ce);

        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();

        $zipArchive = new ZipArchive();
        $zip_filename = $tmp_folder . "/fichier-{$id_e}-" . ($id_d ?: $id_ce) . "-{$field}.zip";
        if (!$zipArchive->open($zip_filename, ZIPARCHIVE::CREATE)) {
            throw new Exception("Impossible de créer le fichier d'archive $zip_filename");
        }

        $file_names = [];
        foreach ($donneesFormulaire->get($field) as $i => $fichier) {
            $file_path = $donneesFormulaire->getFilePath($field, $i);
            $file_name = $donneesFormulaire->getFileName($field, $i);

            $file_dup = 2;
            $path_parts = pathinfo($file_name);
            $base_name = $path_parts['filename'];
            $extension = isset($path_parts['extension']) ? '.' . $path_parts['extension'] : '';

            while (in_array($file_name, $file_names, true)) {
                $file_name = $base_name . '_' . $file_dup . $extension;
                $file_dup++;
            }
            $file_names[] = $file_name;
            if (!$zipArchive->addFile($file_path, $file_name)) {
                throw new Exception(
                    "Impossible d'ajouter le fichier $file_path ($file_name) dans l'archive $zip_filename"
                );
            }
        }
        $zipArchive->close();

        $sendFileToBrowser = $this->getObjectInstancier()->getInstance(SendFileToBrowser::class);
        $sendFileToBrowser->send($zip_filename);

        $tmpFolder->delete($tmp_folder);
    }


    private function isDocumentEmailChunkUpload()
    {
        /* mailsec ? */
        $key = $this->getPostOrGetInfo()->get('key');
        $documentEmail = $this->getObjectInstancier()->getInstance(DocumentEmail::class);
        $mailsec_info = $documentEmail->getInfoFromKey($key);
        if (!$mailsec_info) {
            return false;
        }
        $documentEmailReponseSQL = $this->getObjectInstancier()->getInstance(DocumentEmailReponseSQL::class);
        $id_d_reponse = $documentEmailReponseSQL->getDocumentReponseId($mailsec_info['id_de']);
        if ($this->getPostOrGetInfo()->get('id_d') != $id_d_reponse) {
            return false;
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function chunkUploadAction()
    {
        $id_e = $this->getPostOrGetInfo()->getInt('id_e');
        $id_d = $this->getPostOrGetInfo()->get('id_d');
        $id_ce = $this->getPostOrGetInfo()->get('id_ce');
        $field = $this->getPostOrGetInfo()->get('field');

        $this->verifDroitEditionOnDocumentOrConnecteur($id_e, $id_d, $id_ce);

        if (\preg_match('#[^\w-]#', $field)) {
            throw new UnrecoverableException("Champ `$field` incorrect");
        }

        $chunkUploader = $this->getObjectInstancier()->getInstance(ChunkUploader::class);
        $upload_filepath = \sprintf(
            '%s/%s_%s_%s_%s_%s_%s',
            $chunkUploader->getUploadChunkDirectory(),
            $id_e,
            $id_d,
            $id_ce,
            $field,
            time(),
            random_int(0, mt_getrandmax())
        );

        $this->getLogger()->debug(
            \sprintf(
                'Chargement partiel du fichier : %s dans (id_e=%s,id_d=%s,id_ce=%s,field=%s)',
                $upload_filepath,
                $id_e,
                $id_d,
                $id_ce,
                $field
            )
        );

        if ($chunkUploader->uploadChunk($upload_filepath)) {
            $donneesFormulaire = $this->getDonneesFormulaireFactory()->getFromDocumentOrConnecteur($id_d, $id_ce);

            if ($donneesFormulaire->getFormulaire()->getField($field)->isMultiple()) {
                $nb_file = $donneesFormulaire->get($field) ? count($donneesFormulaire->get($field)) : 0;
                $this->getLogger()->debug("ajout fichier $nb_file");
                $donneesFormulaire->addFileFromCopy($field, $chunkUploader->getRequest()->getFileName(), $upload_filepath, $nb_file);
            } else {
                $donneesFormulaire->addFileFromCopy($field, $chunkUploader->getRequest()->getFileName(), $upload_filepath);
            }

            foreach ($donneesFormulaire->getOnChangeAction() as $action_on_change) {
                if ($id_ce) {
                    $result = $this->getActionExecutorFactory()->executeOnConnecteur(
                        $id_ce,
                        $this->getId_u(),
                        $action_on_change
                    );
                } else {
                    $result = $this->getActionExecutorFactory()->executeOnDocument(
                        $id_e,
                        $this->getId_u(),
                        $id_d,
                        $action_on_change
                    );
                }
                if (!$result) {
                    $this->setLastError($this->getActionExecutorFactory()->getLastMessage());
                } elseif ($this->getActionExecutorFactory()->getLastMessage()) {
                    $this->setLastMessage($this->getActionExecutorFactory()->getLastMessage());
                }
            }
            $this->getLogger()->debug('chargement terminé');
            unlink($upload_filepath);
        }

        $chunkUploader->pruneChunks();
        echo 'OK';
        exit_wrapper();
    }

    /**
     * @throws Exception
     */
    public function visionneuseAction(): void
    {
        $getInfo = $this->getGetInfo();
        $id_e = $getInfo->getInt('id_e');
        $id_d = $getInfo->get('id_d');
        $id_ce = $getInfo->get('id_ce');
        $field = $getInfo->get('field');
        $num = $getInfo->getInt('num');

        $this->verifDroitLectureOnDocumentOrConnecteur($id_e, $id_d, $id_ce);

        try {
            $visionneuseFactory = $this->getObjectInstancier()->getInstance(ViewerFactory::class);
            if ($id_d) {
                $visionneuseFactory->display($id_d, $field, $num);
            } else {
                $visionneuseFactory->displayConnecteur($id_ce, $field, $num);
            }
        } catch (Exception $e) {
            echo "Une erreur est survenue : " . $e->getMessage();
        }
    }
}
