<?php

use Pastell\Service\Annuaire\AnnuaireContactService;
use Pastell\Service\Annuaire\AnnuaireExportService;
use Pastell\Service\Annuaire\AnnuaireGroupeService;
use Pastell\Service\Annuaire\AnnuaireImportService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;
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
        $this->setViewParameter('id_g', $recuperateur->getInt('id_g'));
        $search = $recuperateur->get('search', '');
        $this->setViewParameter('search', get_hecho($search));
        $this->setViewParameter('offset', $recuperateur->getInt('offset'));
        $this->setViewParameter('limit', self::NB_MAIL_AFFICHE);

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);

        $this->setDroitViewParameter($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);


        $listUtilisateur = $this->getAnnuaireSQL()->getUtilisateurList(
            $id_e,
            $this->getViewParameterByKey('offset'),
            $this->getViewParameterByKey('limit'),
            $search,
            $this->getViewParameterByKey('id_g')
        );

        $this->setViewParameter('nb_email', $this->getAnnuaireSQL()->getNbUtilisateur($id_e, $search, $this->getViewParameterByKey('id_g')));

        $annuaireGroupe = $this->getInstance(AnnuaireGroupeSQL::class);

        foreach ($listUtilisateur as $i => $utilisateur) {
            $listUtilisateur[$i]['groupe'] = $annuaireGroupe->getGroupeFromUtilisateur($utilisateur['id_a']);
        }

        $this->setViewParameter('listUtilisateur', $listUtilisateur);

        $this->setViewParameter('groupe_list', $annuaireGroupe->getGroupe($id_e));


        $this->setInfoEntite($id_e);
        $this->setViewParameter('id_e', $id_e);
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
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('page', "Carnet d'adresses");
        $this->setViewParameter('page_title', $infoEntite['denomination'] . " - Carnet d'adresses");
        $this->setViewParameter('template_milieu', 'MailSecGroupeList');
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
    public function groupeAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $id_e = $recuperateur->getInt('id_e');
        $id_g = $recuperateur->getInt('id_g');
        $offset = $recuperateur->getInt('offset');
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);
        $this->setDroitViewParameter($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $annuaireGroupe = $this->getInstance(AnnuaireGroupeSQL::class);
        $this->setViewParameter('infoGroupe', $annuaireGroupe->getInfo($id_e, $id_g));
        $this->setViewParameter('listUtilisateur', $annuaireGroupe->getUtilisateur($id_g, $offset));
        $this->setViewParameter('nbUtilisateur', $annuaireGroupe->getNbUtilisateur($id_g));

        if ($id_e) {
            $this->setViewParameter('infoEntite', $this->getEntiteSQL()->getInfo($id_e));
        } else {
            $this->setViewParameter('infoEntite', ["denomination" => "Annuaire global"]);
        }

        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('id_g', $id_g);
        $this->setViewParameter('offset', $offset);

        $this->setViewParameter('page', "Carnet d'adresses");
        $this->setViewParameter('page_title', $this->getViewParameterByKey('infoEntite')['denomination'] . " - Carnet d'adresses");

        $this->setViewParameter('template_milieu', "MailSecGroupe");
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function groupeRoleListAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $id_e = $recuperateur->getInt('id_e');
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);
        $this->setDroitViewParameter($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $this->setViewParameter('arbre', $this->getRoleUtilisateur()->getArbreFille(
            $this->getId_u(),
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION)
        ));

        $this->setViewParameter('listGroupe', $this->getAnnuaireRoleSQL()->getAll($id_e));

        if ($id_e) {
            $this->setViewParameter('infoEntite', $this->getEntiteSQL()->getInfo($id_e));
        } else {
            $this->setViewParameter('infoEntite', ["denomination" => "Annuaire global"]);
        }

        $all_ancetre = $this->getEntiteSQL()->getAncetreId($id_e);
        $this->setViewParameter('groupe_herited', $this->getAnnuaireRoleSQL()->getGroupeHerite($all_ancetre));
        $this->setViewParameter('id_e', $id_e);
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
    public function importAction()
    {
        $recuperateur = $this->getGetInfo();
        $id_e = $recuperateur->getInt('id_e');
        $this->setViewParameter('id_e', $id_e);
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $this->setInfoEntite($this->getViewParameterByKey('id_e'));

        $this->setViewParameter('page_title', "Importer un carnet d'adresse");
        $this->setViewParameter('template_milieu', "MailSecImporter");
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doImportAction(): void
    {
        $recuperateur = $this->getPostInfo();

        $id_e = $recuperateur->getInt('id_e', 0);
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        $fileUploader = new FileUploader();
        $file_path = $fileUploader->getFilePath('csv');
        if (! $file_path) {
            $this->getLastError()->setLastError('Impossible de lire le fichier');
            $this->redirect('/MailSec/import?id_e=' . $id_e);
        }

        $finfo = new finfo();

        if (! in_array($finfo->file($file_path, FILEINFO_MIME_TYPE), [ 'text/plain','text/csv'])) {
            $this->setLastError('Le fichier doit être en CSV');
            $this->redirect("/MailSec/import?id_e=$id_e");
        }

        $nb_import = $this->getInstance(AnnuaireImportService::class)->import($id_e, $file_path);

        $this->getLastMessage()->setLastMessage("$nb_import emails ont été importés");
        $this->redirect('/MailSec/annuaire?id_e=' . $id_e);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function exportAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $id_e = $recuperateur->getInt('id_e');

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);

        $csvContent = $this->getInstance(AnnuaireExportService::class)->export($id_e);
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
    public function detailAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $id_a = $recuperateur->getInt('id_a');
        $this->setViewParameter('info', $this->getAnnuaireSQL()->getInfo($id_a));

        $annuaireGroupe = $this->getInstance(AnnuaireGroupeSQL::class);

        $this->setViewParameter('groupe_list', $annuaireGroupe->getGroupeFromUtilisateur($id_a));

        $id_e = $this->getViewParameterByKey('info')['id_e'];
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);
        $this->setInfoEntite($this->getViewParameterByKey('info')['id_e']);
        $this->setDroitViewParameter($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);


        $this->setViewParameter('page_title', $this->getViewParameterByKey('infoEntite')['denomination'] .
            " - Détail de l'adresse « {$this->getViewParameterByKey('info')['email']} »");
        $this->setViewParameter('template_milieu', "MailSecDetail");
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    public function editAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $id_a = $recuperateur->getInt('id_a');
        $this->setViewParameter('info', $this->getAnnuaireSQL()->getInfo($id_a));
        $id_e = $this->getViewParameterByKey('info')['id_e'];
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);
        $this->setInfoEntite($this->getViewParameterByKey('info')['id_e']);

        $id_e = (int)$this->getViewParameterByKey('info')['id_e'];
        $annuaireGroupe = $this->getInstance(AnnuaireGroupeSQL::class);

        $this->setViewParameter('groupe_list', $annuaireGroupe->getGroupeWithHasUtilisateur($id_e, $id_a));

        $this->setViewParameter('page_title', $this->getViewParameterByKey('infoEntite')['denomination'] .
            " - Édition de l'adresse « {$this->getViewParameterByKey('info')['email']} »");
        $this->setViewParameter('template_milieu', "MailSecEdit");
        $this->renderDefault();
    }

    /**
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doEditAction(): void
    {
        $recuperateur = new Recuperateur($_POST);
        $id_a = $recuperateur->getInt('id_a');
        $description = $recuperateur->get('description', '');
        $email = $recuperateur->get('email');
        $id_g_list = $recuperateur->get('id_g');

        $info = $this->getAnnuaireSQL()->getInfo($id_a);
        $this->checkDroitFor($info['id_e'], DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        try {
            $this->getInstance(AnnuaireContactService::class)->edit($id_a, $description, $email);
        } catch (BadRequestException | ConflictException $e) {
            $this->getLastError()->setLastError($e->getMessage());
            $this->redirect("MailSec/edit?id_a=$id_a");
        }

        $annuaireGroupe = $this->getInstance(AnnuaireGroupeSQL::class);
        $annuaireGroupe->deleteAllGroupFromContact($id_a);

        if ($id_g_list) {
            foreach ($id_g_list as $id_g) {
                $annuaireGroupe->addToGroupe($id_g, $id_a);
            }
        }

        $this->getLastMessage()->setLastMessage('Le contact a été modifié');
        $this->redirect("MailSec/detail?id_a=$id_a&id_e=" . $info['id_e']);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function deleteAction()
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $id_a_list = $recuperateur->getInt('id_a');

        if (! $id_a_list) {
            $this->getLastError()->setLastError("Vous devez sélectionner au moins un email à supprimer");
            $this->redirect("MailSec/annuaire?id_e=$id_e");
        }
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION);

        if (! is_array($id_a_list)) {
            $id_a_list = [$id_a_list];
        }

        $annuaireContactService = $this->getInstance(AnnuaireContactService::class);
        foreach ($id_a_list as $id_a) {
            $annuaireContactService->delete($id_e, (int)$id_a);
        }
        $this->getLastMessage()->setLastMessage('Email(s) supprimé(s) de la liste de contacts');
        $this->redirect("MailSec/annuaire?id_e=$id_e");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function addContactAction(): void
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $description = $recuperateur->get('description');
        $email = $recuperateur->get('email');

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION, "MailSec/annuaire?id_e=$id_e");

        try {
            $this->getInstance(AnnuaireContactService::class)->create($id_e, $description, $email);
        } catch (BadRequestException | ConflictException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("MailSec/annuaire?id_e=$id_e");
        }

        $mail = htmlentities("\"$description\"<$email>", ENT_QUOTES);
        $this->setLastMessage("$mail a été ajouté à la liste de contacts");
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
            $this->redirect("MailSec/groupe?id_e=$id_e&id_g=$id_g");
        }

        try {
            $this->getInstance(AnnuaireGroupeService::class)->addContactToGroupe($id_g, (int)$id_a);
        } catch (NotFoundException | ConflictException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("MailSec/groupe?id_e=$id_e&id_g=$id_g");
        }

        $mail = htmlentities($name, ENT_QUOTES);
        $this->setLastMessage("$mail a été ajouté à ce groupe");
        $this->redirect("MailSec/groupe?id_e=$id_e&id_g=$id_g");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function addGroupeAction(): void
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
    public function delContactFromGroupeAction()
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $id_g = $recuperateur->getInt('id_g');
        $id_a_list = $recuperateur->get('id_a', []);
        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION, "MailSec/annuaire?id_e=$id_e");

        if (! is_array($id_a_list)) {
            $id_a_list = [$id_a_list];
        }

        $annuaireGroupeService = $this->getInstance(AnnuaireGroupeService::class);
        foreach ($id_a_list as $id_a) {
            try {
                $annuaireGroupeService->removeContactFromGroupe($id_g, (int)$id_a);
            } catch (NotFoundException $e) {
                $this->setLastError($e->getMessage());
                $this->redirect("MailSec/groupe?id_e=$id_e&id_g=$id_g");
            }
        }

        $this->setLastMessage("Email retiré du groupe");
        $this->redirect("MailSec/groupe?id_e=$id_e&id_g=$id_g");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function delGroupeAction()
    {
        $recuperateur = new Recuperateur($_POST);
        $id_e = $recuperateur->getInt('id_e');
        $id_g_list = $recuperateur->get('id_g', []);

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION, "MailSec/annuaire?id_e=$id_e");

        $annuaireGroupeService = $this->getInstance(AnnuaireGroupeService::class);
        foreach ($id_g_list as $id_g) {
            $annuaireGroupeService->deleteGroupe($id_e, (int)$id_g);
        }

        if ($id_g_list) {
            $this->setLastMessage('Les groupes sélectionnés ont été supprimés');
        }

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
        $id_g = $recuperateur->get('id_g');

        $this->checkDroitFor($id_e, DroitService::DROIT_ANNUAIRE, DroitType::EDITION, "MailSec/annuaire?id_e=$id_e");


        $annuaireGroupe = $this->getInstance(AnnuaireGroupeSQL::class);
        $annuaireGroupe->tooglePartage($id_g);
        $info = $annuaireGroupe->getInfo($id_e, $id_g);
        if ($info['partage']) {
            $this->setLastMessage("Le groupe est maintenant partagé");
        } else {
            $this->setLastMessage("Le partage du groupe a été supprimé");
        }
        $this->redirect("MailSec/groupe?id_e=$id_e&id_g=$id_g");
    }
}
