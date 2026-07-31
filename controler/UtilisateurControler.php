<?php

use Pastell\Mailer\Mailer;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Menu\MenuGaucheService;
use Pastell\Service\PasswordEntropy;
use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Service\Utilisateur\UserTokenService;
use Pastell\Service\Utilisateur\UserUpdateService;
use Pastell\Service\Utilisateur\UtilisateurDeletionService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class UtilisateurControler extends PastellControler
{
    /**
     * @return UtilisateurNewEmailSQL
     */
    public function getUtilisateurNewEmailSQL()
    {
        return $this->getInstance(UtilisateurNewEmailSQL::class);
    }

    /**
     * @return NotificationMail
     */
    public function getNotificationMail()
    {
        return $this->getInstance(NotificationMail::class);
    }

    /**
     * @return Notification
     */
    public function getNotification()
    {
        return $this->getInstance(Notification::class);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function _beforeAction()
    {
        parent::_beforeAction();
        $id_u = $this->getGetInfo()->getInt('id_u');
        $id_e = 0;
        if ($id_u) {
            $info = $this->getUtilisateur()->getInfo($id_u);
            if (! $info) {
                $this->setLastError("L'utilisateur n'existe pas");
                $this->redirect('/');
            }
            $id_e = $info['id_e'];
            if ($this->getGetInfo()->get('source') !== 'moi') {
                $this->checkDroitFor($id_e, DroitService::DROIT_UTILISATEUR, DroitType::LECTURE);
            }
        } elseif ($this->getGetInfo()->get('id_e')) {
            $id_e = $this->getGetInfo()->get('id_e');
        }
        $this->setViewParameter('type_e_menu', '');
        $this->setViewParameter('id_e_menu', $id_e);
        $this->setViewParameter('id_e', $id_e);
        $this->setEntiteMenuGauche((int) $id_e);
        $this->setNavigationInfo($id_e, 'Entite/utilisateur');
        $this->setMenuGaucheSelect(MenuGaucheService::ENTITE_UTILISATEUR);
        $this->setDroitViewParameter((int) $this->getViewParameterOrObject('id_e'), DroitService::DROIT_CONNECTEUR, DroitType::LECTURE);
        $this->setDroitsDaemon($id_e);
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function modifPasswordAction()
    {
        $authentificationConnecteur = $this->getConnecteurFactory()->getGlobalConnecteur('authentification');
        if ($authentificationConnecteur) {
            $this->getViewParameterOrObject('LastError')->setLastError(
                'Vous ne pouvez pas modifier votre mot de passe en dehors du CAS'
            );
            $this->redirect('/Utilisateur/moi');
        }
        $this->setViewParameter('pages_without_left_menu', true);

        $this->setViewParameter('page_title', 'Modification de votre mot de passe');
        $this->setViewParameter('template_milieu', 'UtilisateurModifPassword');
        $passwordEntropy = $this->getObjectInstancier()->getInstance(PasswordEntropy::class);
        $this->setViewParameter('password_min_entropy', $passwordEntropy->getEntropyForDisplay());
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function modifEmailAction()
    {
        $this->setViewParameter('utilisateur_info', $this->getUtilisateur()->getInfo($this->getId_u()));
        if ($this->getViewParameterOrObject('utilisateur_info')['id_e'] == 0) {
            $this->getViewParameterOrObject('LastError')->setLastError(
                "Les utilisateurs de l'entité racine ne peuvent pas utiliser cette procédure"
            );
            $this->redirect('/Utilisateur/moi');
        }
        $this->setViewParameter('page_title', 'Modification de votre email');
        $this->setViewParameter('template_milieu', 'UtilisateurModifEmail');
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws Exception
     */
    public function modifEmailControlerAction()
    {
        $recuperateur = new Recuperateur($_POST);
        $password = $recuperateur->get('password');
        if (!$this->getUtilisateur()->verifPassword($this->getId_u(), $password)) {
            $this->getViewParameterOrObject('LastError')->setLastError('Le mot de passe est incorrect.');
            $this->redirect('/Utilisateur/modifEmail');
        }
        $email = $recuperateur->get('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->getViewParameterOrObject('LastError')->setLastError(
                "L'email que vous avez saisi ne semble pas être valide"
            );
            $this->redirect('/Utilisateur/modifEmail');
        }

        $utilisateur_info = $this->getUtilisateur()->getInfo($this->getId_u());

        $password = $this->getUtilisateurNewEmailSQL()->add($this->getId_u(), $email);

        $link = sprintf('%s/Utilisateur/modifEmailConfirm?password=%s', $this->getSiteBase(), $password);
        $plateforme_mail = $this->getInstance('plateforme_mail');
        $libelle_plateforme_mail = $this->getConfigurationSQL()->getLibellePlateformeMail();
        $templatedEmail = new TemplatedEmail()
            ->from(new Address($plateforme_mail, $libelle_plateforme_mail))
            ->to($email)
            ->subject('[Pastell] Changement de mail sur Pastell')
            ->htmlTemplate('changement-email.html.twig')
            ->context(['link' => $link]);
        $this->getObjectInstancier()
            ->getInstance(Mailer::class)
            ->send($templatedEmail);

        $this->getJournal()->add(
            Journal::MODIFICATION_UTILISATEUR,
            $utilisateur_info['id_e'],
            0,
            'change-email',
            "Demande de changement d'email initiée {$utilisateur_info['email']} -> $email"
        );

        $this->setLastMessage(
            'Un email a été envoyé à votre nouvelle adresse. Merci de le consulter pour la suite de la procédure.'
        );
        $this->redirect('/Utilisateur/moi');
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function modifEmailConfirmAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $password = $recuperateur->get('password');
        $info = $this->getUtilisateurNewEmailSQL()->confirm($password);
        if ($info) {
            $this->createChangementEmail($info['id_u'], $info['email']);
            $this->getUtilisateurNewEmailSQL()->delete($info['id_u']);
        }
        $this->setViewParameter('result', $info);
        $this->setViewParameter('page_title', "Procédure de changement d'email");
        $this->setViewParameter('template_milieu', 'UtilisateurModifEmailConfirm');
        $this->renderDefault();
    }

    /**
     * @param $id_u
     * @param $email
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    private function createChangementEmail($id_u, $email)
    {
        $utilisateur_info = $this->getUtilisateur()->getInfo($id_u);

        $documentCreationService = $this->getObjectInstancier()->getInstance(DocumentCreationService::class);
        $id_d = $documentCreationService->createDocument($utilisateur_info['id_e'], $id_u, 'changement-email');
        $this->getDocumentSQL()->setTitre($id_d, $utilisateur_info['login']);

        /** @var DonneesFormulaire $donneesFormulaire */
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        foreach (['id_u', 'login', 'nom', 'prenom'] as $key) {
            $data[$key] = $utilisateur_info[$key];
        }
        $data['email_actuel'] = $utilisateur_info['email'];
        $data['email_demande'] = $email;
        $donneesFormulaire->setTabData($data);

        $this->getNotificationMail()->notify(
            $utilisateur_info['id_e'],
            $id_d,
            'creation',
            'changement-email',
            $utilisateur_info['login'] . " a fait une demande de changement d'email"
        );
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function editionAction()
    {
        $recuperateur = $this->getGetInfo();
        $id_u = $recuperateur->getInt('id_u');
        $id_e = $recuperateur->getInt('id_e');
        $is_api = $recuperateur->getInt('is_api') ?: false;
        $infoUtilisateur = [
            'login' => $this->getLastError()->getLastInput('login'),
            'nom' => $this->getLastError()->getLastInput('nom'),
            'prenom' => $this->getLastError()->getLastInput('prenom'),
            'email' => $this->getLastError()->getLastInput('email'),
            'certificat' => '',
            'id_e' => $id_e,
            'is_api' => $is_api,
        ];

        if ($id_u) {
            $infoUtilisateur = $this->getUtilisateur()->getInfo($id_u);
            if (!$infoUtilisateur) {
                $this->redirect();
            }
        }

        $this->setViewParameter('infoEntite', $this->getEntiteSQL()->getInfo($infoUtilisateur['id_e']));
        $this->setViewParameter(
            'arbre',
            $this->getRoleUtilisateur()
                ->getArbreFilleWithRacine($this->getId_u(), DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION))
        );

        if ($id_u) {
            $this->checkDroitFor($infoUtilisateur['id_e'], DroitService::DROIT_UTILISATEUR, DroitType::EDITION);
            $this->setViewParameter(
                'page_title',
                'Modification de ' . $infoUtilisateur['prenom'] . ' ' . $infoUtilisateur['nom']
            );
            $this->setViewParameter('new_user', false);
            $this->setViewParameter('is_api', $infoUtilisateur['is_api']);
        } else {
            $this->checkDroitFor($infoUtilisateur['id_e'], DroitService::DROIT_UTILISATEUR, DroitType::CREATION);
            $this->setViewParameter('page_title', 'Nouvel utilisateur ');
            $this->setViewParameter('new_user', true);
            $this->setViewParameter('is_api', false);
        }
        $this->setViewParameter('id_u', $id_u);
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('infoUtilisateur', $infoUtilisateur);
        $this->setViewParameter('template_milieu', 'UtilisateurEdition');
        $this->renderDefault();
    }

    /**
     * @throws JsonException
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function detailAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $id_u = $recuperateur->getInt('id_u');

        $info = $this->getUtilisateur()->getInfo($id_u);
        if (!$info) {
            $this->setLastError("Utilisateur $id_u inconnu");
            $this->redirect('index.php');
        }

        $this->setViewParameter('id_current_u', $this->getId_u());
        $this->setViewParameter('page_title', 'Utilisateur ' . $info['prenom'] . ' ' . $info['nom']);
        $this->setViewParameter('entiteListe', $this->getEntiteListe());
        $this->setViewParameter(
            'tabEntite',
            $this->getRoleUtilisateur()
                ->getEntite($this->getId_u(), DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION))
        );

        if ((int) $id_u === $this->getId_u()) {
            $this->setViewParameter('notification_list', $this->getNotificationList($id_u));
        }

        if ($this->hasDroitFor($info['id_e'], DroitService::DROIT_ROLE, DroitType::LECTURE)) {
            $this->setViewParameter('role_authorized', $this->apiGet('role'));
        } else {
            $this->setViewParameter('role_authorized', []);
        }

        if (!$this->hasDroitFor($info['id_e'], DroitService::DROIT_UTILISATEUR, DroitType::LECTURE)) {
            $this->setLastError(
                \sprintf(
                    "Vous n'avez pas les droits nécessaires (%s:%s) pour accéder à cette page",
                    $info['id_e'],
                    DroitService::getDroitFor(DroitService::DROIT_UTILISATEUR, DroitType::LECTURE)
                )
            );
            $this->redirect();
        }
        $this->setDroitViewParameter((int) $info['id_e'], DroitService::DROIT_UTILISATEUR, DroitType::EDITION);

        if (
            (int) $id_u === $this->getId_u()
            || ($this->hasDroitFor($info['id_e'], DroitService::DROIT_UTILISATEUR, DroitType::EDITION)
                && $info['is_api'])
        ) {
            $tokens = $this->getObjectInstancier()
                ->getInstance(UserTokenService::class)
                ->getTokens($id_u);
            $this->setViewParameter('tokens', $tokens);
        }

        if ($info['id_e']) {
            $this->setViewParameter('infoEntiteDeBase', $this->getEntiteSQL()->getInfo($info['id_e']));
            $this->setViewParameter(
                'denominationEntiteDeBase',
                $this->getViewParameterOrObject('infoEntiteDeBase')['denomination']
            );
        }
        $this->setViewParameter('info', $info);
        $this->setViewParameter('id_u', $id_u);
        $arbre = $this->getRoleUtilisateur()
            ->getArbreFilleWithRacine($this->getId_u(), DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION));
        $this->setViewParameter('arbre', $arbre);

        $this->setViewParameter(
            'tree',
            \json_encode(\Pastell\Helpers\ArrayHelper::buildTreeselectOptions($arbre), \JSON_THROW_ON_ERROR)
        );

        $this->setDroitViewParameter((int) $info['id_e'], DroitService::DROIT_JOURNAL, DroitType::LECTURE);
        $this->setViewParameter('template_milieu', 'UtilisateurDetail');
        $this->renderDefault();
    }

    private function getNotificationList($id_u)
    {
        $result = $this->getNotification()->getAll($id_u);
        foreach ($result as $i => $line) {
            $action = $this->getDocumentTypeFactory()->getFluxDocumentType($line['type'])->getAction();
            foreach ($line['action'] as $j => $action_id) {
                $result[$i]['action'][$j] = $action->getActionName($action_id);
            }
        }
        return $result;
    }

    /**
     * @throws NotFoundException
     */
    public function moiAction()
    {
        $id_u = $this->getId_u();
        $info = $this->getUtilisateur()->getInfo($id_u);

        $this->setViewParameter('page_title', 'Espace utilisateur : ' . $info['prenom'] . ' ' . $info['nom']);

        $this->setViewParameter('entiteListe', $this->getEntiteListe());

        $this->setViewParameter(
            'tabEntite',
            $this->getRoleUtilisateur()->getEntite($this->getId_u(), DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION))
        );

        $this->setViewParameter('notification_list', $this->getNotificationList($id_u));

        $this->setViewParameter('roleInfo', $this->getRoleUtilisateur()->getRole($id_u));

        if ($info['id_e']) {
            $infoEntiteDeBase = $this->getEntiteSQL()->getInfo($info['id_e']);
            $this->setViewParameter('denominationEntiteDeBase', $infoEntiteDeBase['denomination']);
        }
        $this->setViewParameter('info', $info);
        $this->setViewParameter('id_u', $id_u);
        $this->setViewParameter(
            'arbre',
            $this->getRoleUtilisateur()
                ->getArbreFilleWithRacine($this->getId_u(), DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE))
        );

        $tokens = $this->getObjectInstancier()
            ->getInstance(UserTokenService::class)
            ->getTokens($this->getId_u());
        $this->setViewParameter('tokens', $tokens);
        $this->setViewParameter('template_milieu', 'UtilisateurMoi');
        $this->setViewParameter('pages_without_left_menu', true);
        $this->renderDefault();
    }


    /**
     * Prise en compte du paramètre $message dans l'affectation de l'erreur
     * Correction "lastError"
     * @param $id_e
     * @param $id_u
     * @param $message
     * @throws LastErrorException
     * @throws LastMessageException
     */
    private function redirectEdition($id_e, $id_u, $message)
    {
        $this->setLastError($message);
        $this->redirect("/Utilisateur/edition?id_e=$id_e&id_u=$id_u");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doEditionAction(): void
    {
        $recuperateur = $this->getPostInfo();
        $id_e = $recuperateur->getInt('id_e');
        $id_u = $recuperateur->getInt('id_u');
        $login = $recuperateur->get('login');
        $email = $recuperateur->get('email');
        $firstname = $recuperateur->get('prenom');
        $lastname = $recuperateur->get('nom');
        $is_api = $recuperateur->get('api_user');

        if ($id_u) {
            $info = $this->getInstance(UtilisateurSQL::class)->getInfo($id_u);
            $this->checkDroitFor($info['id_e'], DroitService::DROIT_UTILISATEUR, DroitType::EDITION);
        }
        $this->checkDroitFor($id_e, DroitService::DROIT_UTILISATEUR, DroitType::EDITION);
        try {
            if ($id_u) {
                $is_api = $this->getInstance(UtilisateurSQL::class)->getInfo($id_u)['is_api'];
                if ($is_api) {
                    $this->getInstance(UserUpdateService::class)->updateAPI(
                        $id_u,
                        $login,
                        $firstname,
                        $lastname,
                        $id_e,
                    );
                } else {
                    $this->getInstance(UserUpdateService::class)->update(
                        $id_u,
                        $login,
                        $email,
                        $firstname,
                        $lastname,
                        $id_e,
                    );
                }
            } elseif ($is_api) {
                $id_u = $this->getInstance(UserCreationService::class)->createAPI(
                    $login,
                    $id_e,
                    $firstname,
                    $lastname,
                );
            } else {
                $id_u = $this->getInstance(UserCreationService::class)->create(
                    $login,
                    $email,
                    $firstname,
                    $lastname,
                    $id_e,
                    null,
                    true
                );
            }
        } catch (Exception $e) {
            $this->redirectEdition($id_e, $id_u, $e->getMessage());
        }

        $this->redirect("/Utilisateur/detail?id_u=$id_u");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function ajoutRoleAction(): void
    {
        $recuperateur = $this->getPostInfo();
        $id_u = $recuperateur->get('id_u');
        $role = $recuperateur->get('role');
        $id_e = $recuperateur->get('id_e', 0);

        $this->checkDroitFor($id_e, DroitService::DROIT_ENTITE, DroitType::EDITION);

        if (!$this->getRoleUtilisateur()->canDelegateRole($this->getId_u(), $role, $id_e)) {
            $this->setLastError(
                'Vous ne pouvez pas attribuer un rôle contenant des droits que vous ne possédez pas sur cette entité.'
            );
        } elseif ($this->getRoleUtilisateur()->hasRole($id_u, $role, $id_e)) {
            $this->setLastError("Ce droit a déjà été attribué à l'utilisateur");
        } elseif ($role) {
            $this->getRoleUtilisateur()->addRole($id_u, $role, $id_e);
        }
        $this->redirect("/Utilisateur/detail?id_u=$id_u");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function supprimeRoleAction(): never
    {
        $recuperateur = $this->getPostInfo();
        $id_u = $recuperateur->get('id_u');
        $role = $recuperateur->get('role');
        $id_e = $recuperateur->getInt('id_e', 0);
        $this->checkDroitFor($id_e, DroitService::DROIT_ENTITE, DroitType::EDITION);
        $this->getRoleUtilisateur()->removeRole($id_u, $role, $id_e);
        $role_info = $this->getRoleSQL()->getInfo($role);
        $utilisateur_info = $this->getUtilisateur()->getInfo($id_u);

        $this->setLastMessage(
            sprintf(
                "Le rôle <i>%s</i> a été retiré de l'utilisateur <i>%s %s</i>",
                $role_info['libelle'] ?? $role,
                $utilisateur_info['prenom'],
                $utilisateur_info['nom']
            )
        );
        $this->redirect("/Utilisateur/detail?id_u=$id_u");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    private function verifEditMesNotifications(int $id_u, int $id_e, ?string $type, string $source): void
    {
        if ($type === null) {
            $this->setLastError("Vous n'avez sélectionné aucun type de dossier");
            $this->redirectToPageUtilisateur($source, $id_u, $type);
        }

        $fluxDefinitionFiles = $this->getObjectInstancier()->getInstance(FluxDefinitionFiles::class);
        if (!$fluxDefinitionFiles->getInfo($type)) {
            $this->setLastError("Le type de dossier n'existe pas");
            $this->redirectToPageUtilisateur($source, $id_u, $type);
        }

        if ($this->hasDroitFor($id_e, DroitService::DROIT_ENTITE, DroitType::EDITION)) {
            return;
        }

        if (
            $this->getDroitService()->hasDroitFor($id_u, $id_e, DroitService::DROIT_ENTITE, DroitType::LECTURE)
            &&
            $this->getDroitService()->hasDroitFor(
                $id_u,
                $id_e,
                $type,
                DroitType::LECTURE
            )
        ) {
            return;
        }

        $this->setLastError("Vous n'avez pas les droits nécessaires pour accéder à cette page");
        $this->redirectToPageUtilisateur($source, $id_u, $type);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function redirectToPageUtilisateur($source, $id_u, $type = null): void
    {
        if ($source === 'moi') {
            $this->redirect('/Utilisateur/moi');
        } else {
            $this->redirect("/Utilisateur/$source?id_u=$id_u&type=$type");
        }
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function notificationAjoutAction(): void
    {
        $recuperateur = $this->getPostInfo();
        $id_u = $recuperateur->getInt('id_u');
        $source = $recuperateur->get('source', 'moi');
        $id_e = $recuperateur->getInt('id_e');
        $type = $recuperateur->get('type', null);
        $daily_digest = $recuperateur->getInt('daily_digest');

        if ($id_u !== $this->getId_u()) {
            $this->setLastError("Vous ne pouvez pas ajouter de notifications à un autre utilisateur");
            $this->redirectToPageUtilisateur($source, $id_u, $type);
        }
        $this->verifEditMesNotifications($id_u, $id_e, $type, $source);
        $this->getNotification()->add($id_u, $id_e, $type, 0, $daily_digest);
        $this->redirect("/Utilisateur/notification?id_u=$id_u&id_e=$id_e&type=$type&source=$source");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function notificationAction(): void
    {
        $recuperateur = $this->getPostInfo();

        $id_u = $recuperateur->getInt('id_u');
        $id_e = $recuperateur->getInt('id_e');
        $type = $recuperateur->get('type');
        $source = $recuperateur->get('source', 'moi');

        if ($id_u !== $this->getId_u()) {
            $this->setLastError("Vous ne pouvez pas modifer les notifications d'un autre utilisateur");
            $this->redirectToPageUtilisateur($source, $id_u, $type);
        }
        $this->verifEditMesNotifications($id_u, $id_e, $type, $source);

        $this->setViewParameter('pages_without_left_menu', $source === 'moi');
        $this->setViewParameter('id_u', $id_u);
        $cancel_url = $source === 'moi' ? '/Utilisateur/moi' : "/Utilisateur/detail?id_u=$id_u&id_e=$id_e";
        $this->setViewParameter('cancel_url', $cancel_url);

        $utilisateur_info = $this->getUtilisateur()->getInfo($id_u);

        $this->setViewParameter('has_daily_digest', $this->getNotification()->hasDailyDigest($id_u, $id_e, $type));

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);
        $titreSelectAction = $type ? 'Paramètre des notifications des documents de type ' . $type :
            "La sélection des actions n'est pas possible car aucun type de dossier n'est spécifié";

        $action_list = $documentType->getAction()->getActionWithNotificationPossible();

        $this->setViewParameter('titreSelectAction', $titreSelectAction);
        $this->setViewParameter(
            'action_list',
            $this->getNotification()->getNotificationActionList($id_u, $id_e, $type, $action_list)
        );
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('type', $type);
        $this->setViewParameter('source', $source);
        $this->setViewParameter(
            'page_title',
            get_hecho($utilisateur_info['login']) . ' - abonnement aux actions des documents '
        );
        $this->setViewParameter('template_milieu', 'UtilisateurNotification');
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function notificationSuppressionAction()
    {
        $recuperateur = $this->getPostInfo();
        $source = $recuperateur->get('source', 'moi');
        $id_n = $recuperateur->get('id_n');

        $infoNotification = $this->getNotification()->getInfo($id_n);
        if (!$infoNotification) {
            $this->setLastError("La notification n'existe pas");
            $this->redirectToPageUtilisateur($source, $this->getId_u());
        }
        $id_u = $infoNotification['id_u'];
        $id_e = $infoNotification['id_e'];
        $type = $infoNotification['type'];

        if ($id_u !== $this->getId_u()) {
            $this->setLastError("Vous ne pouvez pas supprimer les notifications d'un autre utilisateur");
            $this->redirectToPageUtilisateur($source, $this->getId_u());
        }
        $this->verifEditMesNotifications($id_u, $id_e, $type, $source);

        $this->getNotification()->removeAll($id_u, $id_e, $type);
        $this->setLastMessage('La notification a été supprimée');
        $this->redirectToPageUtilisateur($source, $id_u, $type);
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doNotificationEditAction(): void
    {
        $recuperateur = $this->getPostInfo();
        $id_u = $recuperateur->getInt('id_u');
        $id_e = $recuperateur->getInt('id_e');
        $type = $recuperateur->get('type');
        $daily_digest = $recuperateur->get('has_daily_digest');
        $source = $recuperateur->get('source', 'moi');

        if ($id_u !== $this->getId_u()) {
            $this->setLastError("Vous ne pouvez pas modifer les notifications d'un autre utilisateur");
            $this->redirectToPageUtilisateur($source, $id_u, $type);
        }
        $this->verifEditMesNotifications($id_u, $id_e, $type, $source);

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);
        $action_list = $documentType->getAction()->getActionWithNotificationPossible();
        $all_checked = true;
        $no_checked = false;
        $action_checked = [];
        foreach ($action_list as $action) {
            $checked = !!$recuperateur->get($action['id']);
            $action_checked[$action['id']] = $checked;
            $all_checked = $all_checked && $checked;
            $no_checked = $no_checked || $checked;
        }

        $this->getNotification()->removeAll($id_u, $id_e, $type);

        $this->setLastMessage('Les notifications ont été modifiées');
        if (!$no_checked) {
            $this->redirectToPageUtilisateur($source, $id_u, $type);
        }
        if ($all_checked) {
            $this->getNotification()->add($id_u, $id_e, $type, Notification::ALL_TYPE, $daily_digest);
            $this->redirectToPageUtilisateur($source, $id_u, $type);
        }
        foreach ($action_list as $action) {
            if (!$action_checked[$action['id']]) {
                continue;
            }
            $this->getNotification()->add($id_u, $id_e, $type, $action['id'], $daily_digest);
        }
        $this->redirectToPageUtilisateur($source, $id_u, $type);
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doModifPasswordAction()
    {
        $recuperateur = new Recuperateur($_POST);

        $oldpassword = $recuperateur->get('old_password');
        $password = $recuperateur->get('password');
        $password2 = $recuperateur->get('password2');
        if ($password != $password2) {
            $this->setLastError('Les mots de passe ne correspondent pas');
            $this->redirect('Utilisateur/modifPassword');
        }


        if (!$this->getUtilisateur()->verifPassword($this->getId_u(), $oldpassword)) {
            $this->setLastError('Votre ancien mot de passe est incorrecte');
            $this->redirect('Utilisateur/modifPassword');
        }

        $passwordEntropy = $this->getObjectInstancier()->getInstance(PasswordEntropy::class);
        if (!$passwordEntropy->isPasswordStrongEnough($password)) {
            $this->setLastError(
                "Le mot de passe n'a pas été changé car le nouveau mot de passe n'est pas assez fort.<br/>" .
                "Essayez de l'allonger ou de mettre des caractères de différents types. La barre de vérification doit être entièrement remplie"
            );
            $this->redirect('Utilisateur/modifPassword');
        }

        $this->getUtilisateur()->setPassword($this->getId_u(), $password);

        $this->setLastMessage('Votre mot de passe a été modifié');
        $this->redirect('/Utilisateur/moi');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function suppressionAction(): void
    {
        $id_u = $this->getPostOrGetInfo()->getInt('id_u');
        $this->checkSelfSuppression($id_u);
        $userInfo = $this->getUtilisateur()->getInfo($id_u);
        $this->checkDroitFor($userInfo['id_e'], DroitService::DROIT_UTILISATEUR, DroitType::EDITION);
        $this->setViewParameter('id_u', $id_u);
        $this->setViewParameter('info', $userInfo);
        $this->setViewParameter(
            'page_title',
            sprintf("Utilisateur %s %s - Suppression de l'utilisateur ", $userInfo['prenom'], $userInfo['nom'])
        );
        $this->setViewParameter('template_milieu', 'UtilisateurSuppression');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doSuppressionAction(): void
    {
        $id_u = $this->getPostInfo()->getInt('id_u');
        $this->checkSelfSuppression($id_u);
        $userInfo = $this->getUtilisateur()->getInfo($id_u);
        $this->checkDroitFor($userInfo['id_e'], DroitService::DROIT_UTILISATEUR, DroitType::EDITION);
        $this->getObjectInstancier()->getInstance(UtilisateurDeletionService::class)->delete($id_u);
        $this->setLastMessage("L'utilisateur $id_u a été supprimé");
        $this->redirect("/Entite/utilisateur?id_e={$userInfo['id_e']}");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function checkSelfSuppression(int $id_u): void
    {
        if ($id_u !== (int)$this->getId_u()) {
            return;
        }
        $this->setLastError('Impossible de vous supprimer vous-même');
        $this->redirect("/Utilisateur/detail?id_u=$id_u");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function enableAction(): void
    {
        $id_u = $this->getPostInfo()->get('id_u');
        $userInfo = $this->getUtilisateur()->getInfo($id_u);
        $this->checkDroitFor($userInfo['id_e'], DroitService::DROIT_UTILISATEUR, DroitType::EDITION);
        $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->enable($id_u);
        $message = "L'utilisateur {$userInfo['login']} a été activé";
        $this->getJournal()->add(
            Journal::MODIFICATION_UTILISATEUR,
            $userInfo['id_e'],
            Journal::NO_ID_D,
            'activation',
            $message
        );
        $this->setLastMessage($message);
        $this->redirect("/Utilisateur/detail?id_u=$id_u");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function disableAction(): void
    {
        $id_u = $this->getPostInfo()->get('id_u');
        $userInfo = $this->getUtilisateur()->getInfo($id_u);
        $this->checkDroitFor($userInfo['id_e'], DroitService::DROIT_UTILISATEUR, DroitType::EDITION);
        $this->checkSelfDisable($id_u);
        $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->disable($id_u);
        $message = "L'utilisateur {$userInfo['login']} a été désactivé";
        $this->getJournal()->add(
            Journal::MODIFICATION_UTILISATEUR,
            $userInfo['id_e'],
            Journal::NO_ID_D,
            'désactivation',
            $message
        );
        $this->setLastMessage($message);
        $this->redirect("/Utilisateur/detail?id_u=$id_u");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function checkSelfDisable(int $id_u): void
    {
        if ($id_u !== (int)$this->getId_u()) {
            return;
        }
        $this->setLastError('Impossible de vous désactiver vous-même');
        $this->redirect("/Utilisateur/detail?id_u=$id_u");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function addTokenAction(): void
    {
        $recupGet = $this->getGetInfo();
        $id_u = $recupGet->get('id_u');
        $source = $recupGet->get('source') ?: 'moi';
        $this->verifDroitApi($id_u ?: $this->getId_u());
        $this->setViewParameter('pages_without_left_menu', true);
        $this->setViewParameter('id_u', $id_u);
        $this->setViewParameter('source', $source);
        $this->setViewParameter('page_title', 'Ajouter un jeton d\'authentification');
        $this->setViewParameter('template_milieu', 'UtilisateurToken');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doAddTokenAction(): void
    {

        $recuperateur = $this->getPostInfo();
        $recupGet = $this->getGetInfo();
        $id_u = $recupGet->get('id_u') ?: $this->getId_u();
        $source = $recupGet->get('source') ?: 'moi';
        $this->verifDroitApi($id_u);
        $token = $this->getObjectInstancier()
            ->getInstance(UserTokenService::class)
            ->createToken($id_u, $recuperateur->get('name'), $recuperateur->get('expiration') ?: null);

        $message = <<<EOT
La valeur de votre jeton est <strong>$token</strong><br />
Assurez-vous de la sauvegarder, elle ne sera plus affichée.
EOT;

        $this->setLastMessage($message);
        $this->redirectAPIToken($source, $id_u);
    }


    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function deleteTokenAction(): void
    {
        $userTokenService = $this->getObjectInstancier()->getInstance(UserTokenService::class);
        $recuperateur = $this->getPostInfo();
        $id = $recuperateur->get('id');
        $recupGet = $this->getGetInfo();
        $id_u = $userTokenService->getUser($id);
        $source = $recupGet->get('source') ?: 'moi';
        $this->verifDroitApi($id_u);

        $userTokenService->deleteToken($id);
        $this->setLastMessage('Le jeton a été supprimé');
        $this->redirectAPIToken($source, $id_u);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function renewTokenAction(): void
    {
        $userTokenService = $this->getObjectInstancier()->getInstance(UserTokenService::class);
        $recuperateur = $this->getPostInfo();
        $id = $recuperateur->get('id');
        $recupGet = $this->getGetInfo();
        $id_u = $userTokenService->getUser($id);
        $source = $recupGet->get('source') ?: 'moi';
        $this->verifDroitApi($id_u);

        $token = $userTokenService->renewToken($id);
        $message = <<<EOT
Le jeton a été renouvelé. Sa valeur est <strong>$token</strong><br />
Assurez-vous de la sauvegarder, elle ne sera plus affichée.
EOT;
        $this->setLastMessage($message);
        $this->redirectAPIToken($source, $id_u);
    }


    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    private function verifDroitApi(int $id_u): void
    {
        $info = $this->getUtilisateur()->getInfo($id_u);
        $id_e = $info['id_e'];
        $is_api = $info['is_api'];
        if (
            $id_u !== $this->getId_u() &&
            !($is_api &&
                $this->hasDroitFor($id_e, DroitService::DROIT_UTILISATEUR, DroitType::EDITION))
        ) {
            if (!$is_api && $id_u !== $this->getId_u()) {
                $message = 'Action impossible';
            } else {
                $message = "Vous n'avez pas les droits nécessaires pour éxecuter cette action";
            }
            $this->setLastError($message);
            $this->redirect();
        }
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function redirectAPIToken(string $source, int $id_u)
    {
        if ($source === 'detail') {
            $this->redirect('/Utilisateur/detail?id_u=' . $id_u);
        } else {
            $this->redirect('/Utilisateur/moi');
        }
    }
}
