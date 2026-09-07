<?php

use Pastell\Service\Annuaire\AnnuaireContactService;
use Pastell\Service\Annuaire\AnnuaireExportService;
use Pastell\Service\Annuaire\AnnuaireGroupeService;
use Pastell\Service\Annuaire\AnnuaireImportService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Entite\EntityUtilitiesService;
use Pastell\Service\Menu\MenuGaucheService;

class MailSecControler extends PastellControler
{
    public const NB_MAIL_AFFICHE = 100;

    public function _beforeAction()
    {
        parent::_beforeAction();
        $id_e = $this->getPostOrGetInfo()->getInt('id_e');
        $this->setViewParameter('id_e', $id_e);
        $this->checkDroitFor($id_e, DroitService::DROIT_ENTITE, DroitType::LECTURE);
        $this->setNavigationInfo($id_e, "MailSec/annuaire?");
        $this->setMenuGaucheSelect(MenuGaucheService::MAILSEC_ANNUAIRE);
        $this->setEntiteMenuGauche($id_e);
        $this->setDroitViewParameter($id_e, DroitService::DROIT_CONNECTEUR, DroitType::LECTURE);
        $this->setDroitsDaemon($id_e);
    }


    /**
     * @return AnnuaireSQL
     */
    private function getAnnuaireSQL()
    {
        return $this->getInstance(AnnuaireSQL::class);
    }

    /**
     * @return AnnuaireRoleSQL
     */
    private function getAnnuaireRoleSQL()
    {
        return $this->getInstance(AnnuaireRoleSQL::class);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function annuaireAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $id_e = $recuperateur->getInt('id_e');
        $search = $recuperateur->get('search', '');
        $offset = $recuperateur->getInt('offset', 0);
        $limit = $recuperateur->getInt('limit', self::NB_MAIL_AFFICHE);
        $id_g = $recuperateur->getInt('id_g');

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);

        $this->setDroitViewParameter($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $this->setViewParameter('id_e', $id_e);

        $annuaireGroupe = $this->getInstance(AnnuaireGroupeSQL::class);

        $listUtilisateur = $this->getAnnuaireSQL()->getUtilisateurList($id_e, $offset, $limit, $search, $id_g);
        foreach ($listUtilisateur as $i => $utilisateur) {
            $listUtilisateur[$i]['groupe'] = $annuaireGroupe->getGroupeFromUtilisateur($utilisateur['id_a']);
        }

        $this->setInfoEntite($id_e);
        $this->setViewParameter('listUtilisateur', $listUtilisateur);
        $this->setViewParameter('groupe_list', $annuaireGroupe->getGroupe($id_e));
        $this->setViewParameter('nb_email', $this->getAnnuaireSQL()->getNbUtilisateur($id_e, $search, $id_g));
        $this->setViewParameter('id_g', $id_g);
        $this->setViewParameter('search', $search);
        $this->setViewParameter('offset', $offset);
        $this->setViewParameter('limit', $limit);
        $this->setViewParameter('page', "Carnet d'adresses");
        $this->setViewParameter('page_title', $this->getViewParameterByKey('infoEntite')['denomination'] . " - Carnet d'adresses");
        $this->setViewParameter('template_milieu', "MailSecAnnuaire");
        $this->renderDefault();
    }

    private function setInfoEntite($id_e)
    {
        if ($id_e) {
            $this->setViewParameter('infoEntite', $this->getEntiteSQL()->getInfo($id_e));
        } else {
            $this->setViewParameter('infoEntite', ["denomination" => "Annuaire global"]);
        }
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function groupeListAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $id_e = (int)$recuperateur->getInt('id_e');
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);
        $this->setDroitViewParameter($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);
        $annuaireGroupe = $this->getInstance(AnnuaireGroupeSQL::class);

        $listGroupe = $annuaireGroupe->getGroupe($id_e);
        foreach ($listGroupe as $key => $groupe) {
            $listGroupe[$key]['contactsInfo'] = $this->getContactsInfo($annuaireGroupe, $groupe);
        }
        $this->setViewParameter('listGroupe', $listGroupe);

        $infoEntite = $this->getEntiteSQL()->getInfo($id_e);
        if ($id_e === 0) {
            $infoEntite = ['denomination' => 'Annuaire global'];
        }

        $all_ancetre = $this->getEntiteSQL()->getAncetreId($id_e);
        $groupe_herited = $annuaireGroupe->getGroupeHerite($all_ancetre);
        foreach ($groupe_herited as $key => $groupe) {
            $groupe_herited[$key]['contactsInfo'] = $this->getContactsInfo($annuaireGroupe, $groupe);
        }

        $this->setViewParameter('groupe_herited', $groupe_herited);
        $this->setViewParameter('annuaireGroupe', $annuaireGroupe);
        $this->setViewParameter('infoEntite', $infoEntite);
        $this->setViewParameter('page', "Carnet d'adresses");
        $this->setViewParameter('page_title', $infoEntite['denomination'] . " - Carnet d'adresses");
        $this->setViewParameter('template_milieu', 'MailSecGroupeList');
        $this->setMenuGaucheSelect(MenuGaucheService::MAILSEC_GROUPES);
        $this->setViewParameter('nb_max', AnnuaireGroupeSQL::NB_MAX);
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function groupeEditionAction(): void
    {
        $recuperateur = new Recuperateur($_GET);
        $id_e = $recuperateur->getInt('id_e');
        $id_g = $recuperateur->getInt('id_g');

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $annuaireGroupe = $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
        $info_group = ['nom' => $this->getLastError()->getLastInput('nom')];
        if ($id_g) {
            $info_group = $annuaireGroupe->getInfo($id_e, $id_g);
            if (!is_array($info_group)) {
                $this->setLastError("Ce groupe n'existe pas");
                $this->redirect("MailSec/groupeList?id_e=$id_e");
            }
        }

        $this->setViewParameter('id_g', $id_g);
        $this->setViewParameter('page_title', 'Nouveau groupe');
        $this->setViewParameter('info_group', $info_group);
        $this->setViewParameter('template_milieu', 'MailSecGroupeEdition');
        $this->setMenuGaucheSelect(MenuGaucheService::MAILSEC_GROUPES);
        $this->renderDefault();
    }

    private function getContactsInfo(AnnuaireGroupeSQL $annuaireGroupe, array $groupe): array
    {
        $contactsInfo = [];

        $nbContacts = $annuaireGroupe->getNbUtilisateur($groupe['id_g']);
        $contactsInfo['nb_contacts'] = $nbContacts;

        $contacts = $annuaireGroupe->getUtilisateur($groupe['id_g'], 0, 3);
        $contactsString = $this->convertContactsToString($contacts);
        $contactsInfo['contacts'] = $contactsString;

        if ($nbContacts > 3) {
            $moreContacts = $annuaireGroupe->getUtilisateur($groupe['id_g'], 3);
            $moreContactsString = $this->convertContactsToString($moreContacts);
            $contactsInfo['more_contacts'] = $moreContactsString;
        }

        return $contactsInfo;
    }

    private function convertContactsToString(array $contacts): string
    {
        $r = [];
        foreach ($contacts as $u) {
            $r[] = get_hecho('"' . $u['description'] . '"' . ' <' . $u['email'] . '>');
        }
        return implode(',<br/>', $r);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function groupeDetailAction(): void
    {
        $recuperateur = new Recuperateur($_GET);
        $id_e = $recuperateur->getInt('id_e');
        $id_g = $recuperateur->getInt('id_g');
        $offset = $recuperateur->getInt('offset');
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);
        $this->setDroitViewParameter($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $annuaireGroupe = $this->getInstance(AnnuaireGroupeSQL::class);
        $infoGroupe = $annuaireGroupe->getInfo($id_e, $id_g);
        if (!is_array($infoGroupe)) {
            $this->setLastError("Ce groupe n'existe pas");
            $this->redirect("MailSec/groupeList?id_e=$id_e");
        }
        $this->setViewParameter('infoGroupe', $infoGroupe);
        $this->setViewParameter('listUtilisateur', $annuaireGroupe->getUtilisateur($id_g, $offset));
        $this->setViewParameter('nbUtilisateur', $annuaireGroupe->getNbUtilisateur($id_g));

        $entite_info = $id_e === 0 ? ['denomination' => 'Annuaire global'] : $this->getEntiteSQL()->getInfo($id_e);
        $this->setViewParameter('infoEntite', $entite_info);
        $this->setViewParameter('id_g', $id_g);
        $this->setViewParameter('offset', $offset);
        $this->setViewParameter('nb_max', AnnuaireGroupeSQL::NB_MAX);
        $this->setViewParameter('page', "Carnet d'adresses");
        $this->setViewParameter('page_title', $this->getViewParameterByKey('infoEntite')['denomination'] . " - Carnet d'adresses");
        $this->setMenuGaucheSelect(MenuGaucheService::MAILSEC_GROUPES);
        $this->setViewParameter('template_milieu', 'MailSecGroupeDetail');
        $this->renderDefault();
    }

    /**
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws JsonException
     */
    public function groupeRoleListAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $id_e = $recuperateur->getInt('id_e');
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);
        $this->setDroitViewParameter($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $entityUtilitiesService = $this->getInstance(EntityUtilitiesService::class);
        $entity_tree = $entityUtilitiesService->toTreeselectOptions($entityUtilitiesService->buildEntityTree(
            $this->getRoleUtilisateur()->getArbreFille($this->getId_u(), DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION))
        ));
        $this->setViewParameter('entity_treeselect_data', json_encode($entity_tree, JSON_THROW_ON_ERROR));

        $this->setViewParameter('listGroupe', $this->getAnnuaireRoleSQL()->getAll($id_e));

        if ($id_e) {
            $this->setViewParameter('infoEntite', $this->getEntiteSQL()->getInfo($id_e));
        } else {
            $this->setViewParameter('infoEntite', ["denomination" => "Annuaire global"]);
        }

        $all_ancetre = $this->getEntiteSQL()->getAncetreId($id_e);

        $this->setMenuGaucheSelect(MenuGaucheService::MAILSEC_GROUPES_ROLES);
        $this->setViewParameter('groupe_herited', $this->getAnnuaireRoleSQL()->getGroupeHerite($all_ancetre));
        $this->setViewParameter('annuaireRole', $this->getAnnuaireRoleSQL());
        $this->setViewParameter('page', "Carnet d'adresses");
        $this->setViewParameter('page_title', $this->getViewParameterByKey('infoEntite')['denomination'] . " - Carnet d'adresses");
        $this->setViewParameter('template_milieu', "MailSecGroupeRoleList");
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function contactImportAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $id_e = $recuperateur->getInt('id_e');
        $this->setViewParameter('id_e', $id_e);
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $this->setInfoEntite($id_e);

        $this->setViewParameter('page_title', "Importer un carnet d'adresse");
        $this->setViewParameter('template_milieu', 'MailSecContactImport');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doContactImportAction(): void
    {
        $recuperateur = $this->getPostInfo();

        $id_e = $recuperateur->getInt('id_e', 0);
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $fileUploader = new FileUploader();
        $file_path = $fileUploader->getFilePath('csv');
        if (! $file_path) {
            $this->getLastError()->setLastError('Impossible de lire le fichier');
            $this->redirect('MailSec/contactImport?id_e=' . $id_e);
        }

        $finfo = new finfo();

        if (! in_array($finfo->file($file_path, FILEINFO_MIME_TYPE), [ 'text/plain','text/csv'])) {
            $this->setLastError('Le fichier doit être en CSV');
            $this->redirect("MailSec/contactImport?id_e=$id_e");
        }

        $result = $this->getInstance(AnnuaireImportService::class)->import($id_e, $file_path);

        $message = $this->appendFailureCount(
            "{$result['imported']} emails ont été importés",
            $result['ignored'],
            'lignes ignorées'
        );
        $this->reportBulkResult($message, $result['imported'], $result['ignored']);
        $this->redirect('MailSec/annuaire?id_e=' . $id_e);
    }

    private function appendFailureCount(string $message, int $nb_failed, string $suffix): string
    {
        if ($nb_failed > 0) {
            $message .= " ($nb_failed $suffix)";
        }
        return $message;
    }

    private function reportBulkResult(string $message, int $nb_done, int $nb_failed): void
    {
        if ($nb_done === 0 && $nb_failed > 0) {
            $this->setLastError($message);
        } else {
            $this->getLastMessage()->setLastMessage($message);
        }
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function contactExportAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $id_e = $recuperateur->getInt('id_e');
        $search = $recuperateur->get('search', '');
        $id_g = $recuperateur->getInt('id_g');

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);

        $csvContent = $this->getInstance(AnnuaireExportService::class)->export($id_e, $search, $id_g);
        $csvOutput = new CSVoutput();
        $csvOutput->displayHTTPHeader("pastell-annuaire-$id_e.csv");
        echo $csvContent;
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function contactDetailAction(): void
    {
        $recuperateur = new Recuperateur($_GET);
        $id_a = $recuperateur->getInt('id_a');
        $contact_info = $this->getAnnuaireSQL()->getInfo($id_a);

        if (!is_array($contact_info)) {
            $this->setLastError("Ce contact n'existe pas");
            $this->redirect('MailSec/annuaire?id_e=' . $recuperateur->getInt('id_e'));
        }

        $id_e = (int)$contact_info['id_e'];
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);
        $this->setInfoEntite($id_e);
        $this->setViewParameter('contact_info', $contact_info);

        $annuaireGroupe = $this->getInstance(AnnuaireGroupeSQL::class);
        $this->setViewParameter('groupe_list', $annuaireGroupe->getGroupeFromUtilisateur($id_a));

        $this->setDroitViewParameter($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);


        $this->setViewParameter('page_title', $this->getViewParameterByKey('infoEntite')['denomination'] .
            " - Détail de l'adresse « {$contact_info['email']} »");
        $this->setViewParameter('template_milieu', 'MailSecContactDetail');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    public function contactEditionAction(): void
    {
        $recuperateur = new Recuperateur($_GET);
        $id_a = $recuperateur->getInt('id_a');
        $id_e = $recuperateur->getInt('id_e');
        $annuaireGroupeSQL = $this->getInstance(AnnuaireGroupeSQL::class);

        if ($id_a) {
            $info_contact = $this->getAnnuaireSQL()->getInfo($id_a);
            if (!is_array($info_contact)) {
                $this->setLastError("Ce contact n'existe pas");
                $this->redirect("MailSec/annuaire?id_e=$id_e");
            }

            $id_e = (int)$info_contact['id_e'];
            $info_contact['id_g_list'] = array_map(
                '\intval',
                array_column($annuaireGroupeSQL->getGroupeFromUtilisateur($id_a), 'id_g')
            );
        } else {
            $info_contact = [
                'description' => $this->getLastError()->getLastInput('description'),
                'email' => $this->getLastError()->getLastInput('email'),
                'id_g_list' => array_map('\intval', $this->getLastError()->getLastInput('id_g_list') ?: []),
            ];
        }

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);
        $this->setInfoEntite($id_e);
        $this->setViewParameter('info_contact', $info_contact);
        $this->setViewParameter('id_a', $id_a);
        $this->setViewParameter('all_groups', $annuaireGroupeSQL->getGroupe($id_e));
        $this->setViewParameter(
            'page_title',
            $id_a ?
                "Modification du contact {$info_contact['email']}" :
                'Nouveau contact'
        );
        $this->setViewParameter('template_milieu', 'MailSecContactEdition');
        $this->renderDefault();
    }

    /**
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doContactEditionAction(): void
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $id_a = $recuperateur->getInt('id_a');
        $description = $recuperateur->get('description');
        $email = $recuperateur->get('email');
        $id_g_list = $recuperateur->get('id_g_list', []);

        if ($id_a) {
            $contact_info = $this->getAnnuaireSQL()->getInfo($id_a);
            if (!is_array($contact_info)) {
                $this->setLastError("Ce contact n'existe pas");
                $this->redirect("MailSec/annuaire?id_e=$id_e");
            }
            $id_e = (int)$contact_info['id_e'];
        }

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $annuaireContactService = $this->getInstance(AnnuaireContactService::class);
        $mail = htmlentities("\"$description\"<$email>", ENT_QUOTES);

        try {
            if ($id_a) {
                $annuaireContactService->edit($id_a, $description, $email);
                $message = "$mail a été modifié";
                $redirect = "MailSec/contactDetail?id_e=$id_e&id_a=$id_a";
            } else {
                $id_a = $annuaireContactService->create($id_e, $description, $email);
                $message = "$mail a été ajouté à la liste de contacts";
                $redirect = "MailSec/annuaire?id_e=$id_e";
            }

            $annuaireGroupeSQL = $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
            $annuaireGroupeSQL->deleteAllGroupFromContact($id_a);
            foreach ((array)$id_g_list as $id_g) {
                $annuaireGroupeSQL->addToGroupe((int)$id_g, $id_a);
            }
            $this->setLastMessage($message);
            $this->redirect($redirect);
        } catch (NotFoundException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("MailSec/annuaire?id_e=$id_e");
        } catch (BadRequestException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("MailSec/contactEdition?id_e=$id_e&id_a=$id_a");
        } catch (ConflictException $e) {
            $this->setLastError($e->getMessage());
            $conflicting_id_a = $this->getAnnuaireSQL()->getFromEmail($id_e, $email);
            $this->redirect("MailSec/contactDetail?id_e=$id_e&id_a=$conflicting_id_a");
        }
    }

    /**
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function contactSuppressionAction(): void
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $id_a_list = $recuperateur->getInt('id_a');
        $id_a_list = is_array($id_a_list) ? $id_a_list : array_filter([$id_a_list]);

        if (! $id_a_list) {
            $this->getLastError()->setLastError('Vous devez sélectionner au moins un contact à supprimer');
            $this->redirect("MailSec/annuaire?id_e=$id_e");
        }
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $contacts_to_delete = [];
        foreach ($id_a_list as $id_a) {
            $id_a = (int)$id_a;
            $info = $this->getAnnuaireSQL()->getInfo($id_a);
            if (!is_array($info) || (int)$info['id_e'] !== $id_e) {
                continue;
            }
            $contacts_to_delete[] = ['id_a' => $id_a, 'info' => $info];
        }

        if (! $contacts_to_delete) {
            $this->getLastError()->setLastError('Vous devez sélectionner au moins un contact à supprimer');
            $this->redirect("MailSec/annuaire?id_e=$id_e");
        }

        $this->setViewParameter('contacts_to_delete', $contacts_to_delete);
        $this->setViewParameter('page_title', 'Suppression de contact(s)');
        $this->setViewParameter('template_milieu', 'MailSecContactSuppression');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doContactSuppressionAction(): void
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $id_a_list = $recuperateur->getInt('id_a');
        $id_a_list = is_array($id_a_list) ? $id_a_list : array_filter([$id_a_list]);

        if (! $id_a_list) {
            $this->getLastError()->setLastError('Vous devez sélectionner au moins un contact à supprimer');
            $this->redirect("MailSec/annuaire?id_e=$id_e");
        }
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $service = $this->getInstance(AnnuaireContactService::class);
        $nb_deleted = 0;
        $nb_failed = 0;
        foreach ($id_a_list as $id_a) {
            $id_a = (int)$id_a;
            $info = $this->getAnnuaireSQL()->getInfo($id_a);
            if (!is_array($info) || (int)$info['id_e'] !== $id_e) {
                continue;
            }
            try {
                $service->delete($id_e, $id_a);
                $nb_deleted++;
            } catch (Throwable) {
                $nb_failed++;
            }
        }

        $message = $this->appendFailureCount(
            $nb_deleted === 1 ? 'Le contact a été supprimé' : "$nb_deleted contacts ont été supprimés",
            $nb_failed,
            'non supprimés suite à une erreur'
        );
        $this->reportBulkResult($message, $nb_deleted, $nb_failed);
        $this->redirect("MailSec/annuaire?id_e=$id_e");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function addContactToGroupeAction(): void
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $name = $recuperateur->get('name');
        $id_g = $recuperateur->getInt('id_g');

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION, "MailSec/annuaire?id_e=$id_e");

        $id_a = false;
        $email = "";
        if (preg_match("/<([^>]*)>/u", $name, $matches)) {
            $email = $matches[1];
            $id_a = $this->getAnnuaireSQL()->getFromEmail($id_e, $email);
        }

        if (! $id_a) {
            $this->setLastError("L'email $email est inconnu");
            $this->redirect("MailSec/groupeDetail?id_e=$id_e&id_g=$id_g");
        }

        try {
            $this->getInstance(AnnuaireGroupeService::class)->addContactToGroupe($id_g, (int)$id_a);
        } catch (NotFoundException | ConflictException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("MailSec/groupeDetail?id_e=$id_e&id_g=$id_g");
        }

        $mail = htmlentities($name, ENT_QUOTES);
        $this->setLastMessage("$mail a été ajouté à ce groupe");
        $this->redirect("MailSec/groupeDetail?id_e=$id_e&id_g=$id_g");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doGroupeEditionAction(): void
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $nom = $recuperateur->get('nom');

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION, "MailSec/annuaire?id_e=$id_e");

        try {
            $this->getInstance(AnnuaireGroupeService::class)->createGroupe($id_e, $nom);
        } catch (BadRequestException | ConflictException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("MailSec/groupeList?id_e=$id_e");
        }

        $this->setLastMessage("Le groupe « $nom » a été créé");
        $this->redirect("MailSec/groupeList?id_e=$id_e");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function addGroupeRoleAction()
    {

        $recuperateur = new Recuperateur($_POST);
        $id_e = (int)$recuperateur->getInt('id_e');
        $id_e_owner = $recuperateur->getInt('id_e_owner');
        $role = $recuperateur->get('role');

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION, "MailSec/annuaire?id_e=$id_e");
        $this->checkDroitFor($id_e_owner, DroitService::DROIT_ANNUAIRE, DroitType::EDITION, "MailSec/annuaire?id_e=$id_e");


        $infoEntite = $this->getEntiteSQL()->getInfo($id_e);

        if ($id_e !== 0) {
            $nom = "$role - {$infoEntite['denomination']}";
        } else {
            $nom = "$role - toutes les collectivités";
        }

        $this->getAnnuaireRoleSQL()->add($nom, $id_e_owner, $id_e, $role);

        $this->setLastMessage("Le groupe « $nom » a été créé");
        $this->redirect("MailSec/groupeRoleList?id_e=$id_e_owner");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function groupeRetraitAction()
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $id_g = $recuperateur->getInt('id_g');
        $id_a_list = $recuperateur->getInt('id_a');
        $id_a_list = is_array($id_a_list) ? $id_a_list : array_filter([$id_a_list]);

        if (! $id_a_list) {
            $this->getLastError()->setLastError('Vous devez sélectionner au moins un contact à retirer');
            $this->redirect("MailSec/groupeDetail?id_e=$id_e&id_g=$id_g");
        }
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION, "MailSec/annuaire?id_e=$id_e");

        $annuaireGroupeSQL = $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
        $infoGroupe = $annuaireGroupeSQL->getInfo($id_e, $id_g);
        if (!is_array($infoGroupe)) {
            $this->setLastError("Ce groupe n'existe pas");
            $this->redirect("MailSec/groupeList?id_e=$id_e");
        }

        $contacts_to_delete = [];
        foreach ($id_a_list as $id_a) {
            $id_a = (int)$id_a;
            if (! $annuaireGroupeSQL->isInGroupe($id_g, $id_a)) {
                continue;
            }
            $info = $this->getAnnuaireSQL()->getInfo($id_a);
            if (!is_array($info)) {
                continue;
            }
            $contacts_to_delete[] = ['id_a' => $id_a, 'info' => $info];
        }

        if (! $contacts_to_delete) {
            $this->getLastError()->setLastError('Vous devez sélectionner au moins un contact à retirer');
            $this->redirect("MailSec/groupeDetail?id_e=$id_e&id_g=$id_g");
        }

        $this->setViewParameter('contacts_to_delete', $contacts_to_delete);
        $this->setViewParameter('infoGroupe', $infoGroupe);
        $this->setViewParameter('id_g', $id_g);
        $this->setViewParameter('page_title', 'Retrait de contact(s) du groupe');
        $this->setViewParameter('template_milieu', 'MailSecGroupeRetrait');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function doGroupeRetraitAction(): void
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $id_g = $recuperateur->getInt('id_g');
        $id_a_list = $recuperateur->getInt('id_a');
        $id_a_list = is_array($id_a_list) ? $id_a_list : array_filter([$id_a_list]);

        if (!$id_a_list) {
            $this->getLastError()->setLastError('Vous devez sélectionner au moins un contact à retirer');
            $this->redirect("MailSec/groupeDetail?id_e=$id_e&id_g=$id_g");
        }
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $annuaireGroupeSQL = $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
        if (!is_array($annuaireGroupeSQL->getInfo($id_e, $id_g))) {
            $this->setLastError("Ce groupe n'existe pas");
            $this->redirect("MailSec/groupeList?id_e=$id_e");
        }

        $annuaireGroupeService = $this->getObjectInstancier()->getInstance(AnnuaireGroupeService::class);
        foreach ($id_a_list as $id_a) {
            try {
                $annuaireGroupeService->removeContactFromGroupe($id_g, (int)$id_a);
            } catch (NotFoundException $e) {
                $this->setLastError($e->getMessage());
                $this->redirect("MailSec/groupeDetail?id_e=$id_e&id_g=$id_g");
            }
        }

        $this->getLastMessage()->setLastMessage(
            count($id_a_list) === 1
                ? 'Le contact a été retiré du groupe'
                : count($id_a_list) . ' contacts ont été retirés du groupe'
        );
        $this->redirect("MailSec/groupeDetail?id_e=$id_e&id_g=$id_g");
    }

    /**
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function groupeSuppressionAction(): void
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $id_g_list = $recuperateur->getInt('id_g');
        $id_g_list = is_array($id_g_list) ? $id_g_list : array_filter([$id_g_list]);

        if (!$id_g_list) {
            $this->getLastError()->setLastError('Vous devez sélectionner au moins un groupe à supprimer');
            $this->redirect("MailSec/groupeList?id_e=$id_e");
        }
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $annuaireGroupeSQL = $this->getInstance(AnnuaireGroupeSQL::class);
        $groupes_to_delete = [];
        foreach ($id_g_list as $id_g) {
            $id_g = (int)$id_g;
            $info = $annuaireGroupeSQL->getInfo($id_e, $id_g);
            if (!is_array($info)) {
                continue;
            }
            $groupes_to_delete[] = [
                'id_g' => $id_g,
                'info' => $info,
                'nb_contacts' => $annuaireGroupeSQL->getNbUtilisateur($id_g),
            ];
        }

        if (! $groupes_to_delete) {
            $this->getLastError()->setLastError('Vous devez sélectionner au moins un groupe à supprimer');
            $this->redirect("MailSec/groupeList?id_e=$id_e");
        }

        $this->setViewParameter('groupes_to_delete', $groupes_to_delete);
        $this->setViewParameter('page_title', 'Suppression de groupe(s)');
        $this->setViewParameter('template_milieu', 'MailSecGroupesSuppression');
        $this->setMenuGaucheSelect(MenuGaucheService::MAILSEC_GROUPES);
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doGroupeSuppressionAction(): void
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $id_g_list = $recuperateur->getInt('id_g');
        $id_g_list = is_array($id_g_list) ? $id_g_list : array_filter([$id_g_list]);

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION, "MailSec/annuaire?id_e=$id_e");
        if (!$id_g_list) {
            $this->getLastError()->setLastError('Vous devez sélectionner au moins un groupe à supprimer');
            $this->redirect("MailSec/groupeList?id_e=$id_e");
        }

        $annuaireGroupeSQL = $this->getInstance(AnnuaireGroupeSQL::class);
        $annuaireGroupeService = $this->getObjectInstancier()->getInstance(AnnuaireGroupeService::class);
        $nb_deleted = 0;
        $nb_failed = 0;
        foreach ($id_g_list as $id_g) {
            $id_g = (int)$id_g;
            if (!is_array($annuaireGroupeSQL->getInfo($id_e, $id_g))) {
                continue;
            }
            try {
                $annuaireGroupeService->deleteGroupe($id_e, $id_g);
                $nb_deleted++;
            } catch (Throwable) {
                $nb_failed++;
            }
        }

        $message = $this->appendFailureCount(
            $nb_deleted === 1 ? 'Le groupe a été supprimé' : "$nb_deleted groupes ont été supprimés",
            $nb_failed,
            'non supprimés suite à une erreur'
        );
        $this->reportBulkResult($message, $nb_deleted, $nb_failed);
        $this->redirect("MailSec/groupeList?id_e=$id_e");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function getContactAjaxAction()
    {
        $recuperateur = new Recuperateur($_REQUEST);
        $id_e = $recuperateur->getInt('id_e');
        $q = $recuperateur->get('term');
        $mailOnly = $recuperateur->get('mail-only');

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);

        $annuaireGroupe = $this->getInstance(AnnuaireGroupeSQL::class);

        $result = [];

        $all_ancetre = $this->getEntiteSQL()->getAncetreId($id_e);

        $groupe_herited = $annuaireGroupe->getGroupeHerite($all_ancetre, $q);
        $role_herited = $this->getAnnuaireRoleSQL()->getGroupeHerite($all_ancetre, $q);

        if ($mailOnly == "false") {
            foreach ($annuaireGroupe->getListGroupe($id_e, $q) as $item) {
                $result[] = "groupe: \"" . $item['nom'] . "\"\n";
            }
            foreach ($this->getAnnuaireRoleSQL()->getList($id_e, $q) as $item) {
                $result[] = "role: \"" . $item['nom'] . "\"\n";
            }
            foreach ($groupe_herited as $item) {
                $result[] = $annuaireGroupe->getChaineHerited($item) . "\n";
            }
            foreach ($role_herited as $item) {
                $result[] = $this->getAnnuaireRoleSQL()->getChaineHerited($item) . "\n";
            }
        }


        foreach ($this->getAnnuaireSQL()->getListeMail($id_e, $q) as $item) {
            $result[] = '"' . $item['description'] . '"' . " <" . $item['email'] . ">";
        }

        foreach ($result as $i => $line) {
            $result[$i] = $line;
        }

        echo json_encode($result);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function operationGroupeRoleAction()
    {
        $recuperateur = new Recuperateur($_POST);
        $all_id_r = $recuperateur->get('id_r', []);
        $id_e = $recuperateur->getInt('id_e');
        $submit = $recuperateur->get('submit');

        foreach ($all_id_r as $id_r) {
            $info = $this->getAnnuaireRoleSQL()->getInfo($id_r);

            if ($this->hasDroitFor($info['id_e_owner'], DroitService::DROIT_ANNUAIRE, DroitType::EDITION)) {
                if ($submit == "Supprimer") {
                    $this->getAnnuaireRoleSQL()->delete($id_r);
                    $this->setLastMessage("Les groupes sélectionnés ont été supprimés");
                } elseif ($submit == "Partager") {
                    $this->getAnnuaireRoleSQL()->partage($id_r);
                    $this->setLastMessage("Les groupes sélectionnés sont accessibles aux entités filles");
                } else {
                    $this->getAnnuaireRoleSQL()->unpartage($id_r);
                    $this->setLastMessage("Les groupes sélectionnés ne sont plus accessibles aux entités filles");
                }
            }
        }
        $this->redirect("MailSec/groupeRoleList?id_e=$id_e");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function partageGroupeAction()
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $id_g = $recuperateur->getInt('id_g');

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION, "MailSec/annuaire?id_e=$id_e");

        $annuaireGroupe = $this->getInstance(AnnuaireGroupeSQL::class);
        if (!is_array($annuaireGroupe->getInfo($id_e, $id_g))) {
            $this->setLastError("Ce groupe n'existe pas");
            $this->redirect("MailSec/groupeList?id_e=$id_e");
        }
        $annuaireGroupe->tooglePartage($id_g);
        /** @var array $info */
        $info = $annuaireGroupe->getInfo($id_e, $id_g);
        if ($info['partage']) {
            $this->setLastMessage("Le groupe est maintenant partagé");
        } else {
            $this->setLastMessage("Le partage du groupe a été supprimé");
        }
        $this->redirect("MailSec/groupeDetail?id_e=$id_e&id_g=$id_g");
    }
}
