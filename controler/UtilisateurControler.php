<?php

use Pastell\Mailer\Mailer;
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

    public function _beforeAction()
    {
        parent::_beforeAction();
        $id_u = $this->getGetInfo()->getInt('id_u');
        $id_e = 0;
        if ($id_u) {
            $info = $this->getUtilisateur()->getInfo($id_u);
            if (! $info) {
                $this->setLastError("L'utilisateur n'existe pas");
                $this->redirect("/");
            }
            $id_e = $info['id_e'];
            $this->setViewParameter('id_e_menu', $id_e);
            $this->setViewParameter('type_e_menu', "");
            $this->hasUtilisateurDroitLecture($id_e);
            $this->setNavigationInfo($id_e, "Entite/utilisateur?");
        } elseif ($this->getGetInfo()->get('id_e')) {
            $this->setViewParameter('type_e_menu', "");
            $id_e = $this->getGetInfo()->get('id_e');
            $this->setViewParameter('id_e_menu', $id_e);
            $this->setNavigationInfo($id_e, 'Entite/utilisateur?');
        } else {
            $this->setNavigationInfo($id_e, "Entite/utilisateur?");
        }
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter(
            'droitLectureAnnuaire',
            $this->getRoleUtilisateur()->hasDroit($this->getId_u(), 'annuaire:lecture', $id_e)
        );
        $this->setViewParameter('menu_gauche_template', "EntiteMenuGauche");
        $this->setViewParameter('menu_gauche_select', "Entite/utilisateur");
        $this->setDroitLectureOnConnecteur($this->getViewParameterOrObject('id_e'));
        $this->setDroitLectureOnUtilisateur($this->getViewParameterByKey('id_e'));
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
        }

        $this->getUtilisateurNewEmailSQL()->delete($info['id_u']);
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
            $this->getRoleUtilisateur()->getArbreFille($this->getId_u(), 'entite:edition')
        );

        if ($id_u) {
            $this->verifDroit($infoUtilisateur['id_e'], 'utilisateur:edition');
            $this->setViewParameter(
                'page_title',
                'Modification de ' . $infoUtilisateur['prenom'] . ' ' . $infoUtilisateur['nom']
            );
            $this->setViewParameter('new_user', false);
            $this->setViewParameter('is_api', $infoUtilisateur['is_api']);
        } else {
            $this->verifDroit($infoUtilisateur['id_e'], 'utilisateur:creation');
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
            $this->getRoleUtilisateur()->getEntite($this->getId_u(), 'entite:edition')
        );

        if ((int) $id_u === $this->getId_u()) {
            $this->setViewParameter('notification_list', $this->getNotificationList($id_u));
        }

        if ($this->hasDroit($info['id_e'], 'role:lecture')) {
            $this->setViewParameter('role_authorized', $this->apiGet('role'));
        } else {
            $this->setViewParameter('role_authorized', []);
        }

        if (!$this->getRoleUtilisateur()->hasDroit($this->getId_u(), 'utilisateur:lecture', $info['id_e'])) {
            $this->setLastError(
                \sprintf(
                    "Vous n'avez pas les droits nécessaires (%s:utilisateur:lecture) pour accéder à cette page",
                    $info['id_e']
                )
            );
            $this->redirect();
        }
        $this->setViewParameter(
            'utilisateur_edition',
            $this->getRoleUtilisateur()->hasDroit($this->getId_u(), 'utilisateur:edition', $info['id_e'])
        );

        if (
            (int) $id_u === $this->getId_u()
            || ($this->getRoleUtilisateur()->hasDroit($this->getId_u(), 'utilisateur:edition', $info['id_e'])
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
        $this->setViewParameter(
            'arbre',
            $this->getRoleUtilisateur()->getArbreFille($this->getId_u(), 'entite:edition')
        );

        $tree = $this->getRoleUtilisateur()->getEntityTree($this->getId_u(), 'entite:edition');

        $this->replaceArrayKeyRecursive($tree, 'denomination', 'name');
        $this->replaceArrayKeyRecursive($tree, 'id_e', 'value');
        array_unshift($tree, [
            'name' => 'Entité Racine',
            'value' => '0',
        ]);
        $this->setViewParameter(
            'tree',
            \json_encode($tree, \JSON_THROW_ON_ERROR)
        );

        $this->setViewParameter('template_milieu', 'UtilisateurDetail');
        $this->renderDefault();
    }

    private function replaceArrayKeyRecursive(array &$array, string $oldName, string $newName): void
    {
        foreach ($array as &$element) {
            if (\is_array($element)) {
                $this->replaceArrayKeyRecursive($element, $oldName, $newName);
            }
            if (isset($element[$oldName])) {
                $element[$newName] = $element[$oldName];
                unset($element[$oldName]);
            }
            if (isset($element['children']) && \is_array($element['children'])) {
                $this->replaceArrayKeyRecursive($element['children'], $oldName, $newName);
            }
        }
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
            $this->getRoleUtilisateur()->getEntite($this->getId_u(), 'entite:edition')
        );

        $this->setViewParameter('notification_list', $this->getNotificationList($id_u));

        $this->setViewParameter('roleInfo', $this->getRoleUtilisateur()->getRole($id_u));
        $this->setViewParameter(
            'droit_entite_racine',
            $this->getRoleUtilisateur()->hasDroit($this->getId_u(), 'entite:lecture', 0)
        );

        if ($info['id_e']) {
            $infoEntiteDeBase = $this->getEntiteSQL()->getInfo($info['id_e']);
            $this->setViewParameter('denominationEntiteDeBase', $infoEntiteDeBase['denomination']);
        }
        $this->setViewParameter('info', $info);
        $this->setViewParameter('id_u', $id_u);
        $this->setViewParameter(
            'arbre',
            $this->getRoleUtilisateur()->getArbreFille($this->getId_u(), 'entite:lecture')
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
    public function ajoutRoleAction()
    {
        $recuperateur = new Recuperateur($_POST);
        $id_u = $recuperateur->get('id_u');
        $role = $recuperateur->get('role');
        $id_e = $recuperateur->get('id_e', 0);

        $this->verifDroit($id_e, 'entite:edition');
        if ($this->getRoleUtilisateur()->hasRole($id_u, $role, $id_e)) {
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
        $this->verifDroit($id_e, 'entite:edition');
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
     * @param $id_u
     * @param $id_e
     * @param $type
     * @return bool
     * @throws LastErrorException
     * @throws LastMessageException
     */
    private function verifEditNotification($id_u, $id_e, $type): bool
    {
        $utilisateur_info = $this->getUtilisateur()->getInfo($id_u);

        if ($type === null) {
            $this->setLastError("Vous n'avez sélectionné aucun type de dossier");
            $this->redirectToPageUtilisateur($id_u);
            return false;
        }

        if (
            $this->getRoleUtilisateur()->hasDroit($this->getId_u(), 'entite:edition', $id_e)
            &&
            $this->getRoleUtilisateur()->hasDroit($this->getId_u(), 'utilisateur:edition', $utilisateur_info['id_e'])
        ) {
            return true;
        }

        $this->setLastError("Vous n'avez pas les droits nécessaires pour accéder à cette page");
        $this->redirectToPageUtilisateur($id_u);
        return false;
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function verifEditMesNotifications(int $id_e, ?string $type): bool
    {
        $id_u = $this->getId_u();

        if ($type === null) {
            $this->setLastError("Vous n'avez sélectionné aucun type de dossier");
            $this->redirectToPageUtilisateur($id_u, true);
            return false;
        }

        if ($this->getRoleUtilisateur()->hasDroit($id_u, 'entite:edition', $id_e)) {
            return true;
        }

        if (
            $this->getRoleUtilisateur()->hasDroit($id_u, 'entite:lecture', $id_e)
            &&
            $this->getDroitService()->hasDroit(
                $id_u,
                $this->getDroitService()->getDroitLecture($type),
                $id_e
            )
        ) {
            return true;
        }

        $this->setLastError("Vous n'avez pas les droits nécessaires pour accéder à cette page");
        $this->redirectToPageUtilisateur($id_u, true);
        return false;
    }

    /**
     * @param $id_u
     * @param $page_moi
     * @throws LastErrorException
     * @throws LastMessageException
     */
    private function redirectToPageUtilisateur($id_u, $page_moi = false)
    {
        if ($page_moi) {
            $this->redirect('/Utilisateur/moi');
        } else {
            $this->redirect("/Utilisateur/detail?id_u=$id_u");
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
        $id_e = $recuperateur->getInt('id_e', 0);
        $type = $recuperateur->get('type', null);
        $daily_digest = $recuperateur->getInt('daily_digest', 0);
        $url = '/Utilisateur/notification?';

        if ($id_u === 0) {
            $this->verifEditMesNotifications($id_e, $type);
            $id_u = $this->getId_u();
        } else {
            $this->verifEditNotification($id_u, $id_e, $type);
            $url .= "id_u=$id_u&";
        }

        $this->getNotification()->add($id_u, $id_e, $type, 0, $daily_digest);

        $this->redirect($url . "id_e=$id_e&type=$type");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function notificationAction(): void
    {
        $recuperateur = $this->getGetInfo();

        $id_u = $recuperateur->getInt('id_u');
        $id_e = $recuperateur->getInt('id_e');
        $type = $recuperateur->get('type');

        if ($id_u === 0) {
            $this->verifEditMesNotifications($id_e, $type);

            $this->setViewParameter('pages_without_left_menu', true);
            $this->setViewParameter('page_moi', true);
            $this->setViewParameter('cancel_url', '/Utilisateur/moi');

            $id_u = $this->getId_u();
        } else {
            $this->verifEditNotification($id_u, $id_e, $type);

            $this->setViewParameter('page_moi', false);
            $this->setViewParameter('id_u', $id_u);
            $this->setViewParameter('cancel_url', "/Utilisateur/detail?id_u=$id_u&id_e=$id_e");
        }

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

        $id_n = $recuperateur->get('id_n');
        $page_moi = false;

        $infoNotification = $this->getNotification()->getInfo($id_n);
        $id_u = $infoNotification['id_u'];
        $id_e = $infoNotification['id_e'];
        $type = $infoNotification['type'];

        if ($id_u === $this->getId_u()) {
            $page_moi = true;
        } else {
            $this->verifEditNotification($id_u, $id_e, $type);
        }

        $this->getNotification()->removeAll($id_u, $id_e, $type);
        $this->setLastMessage('La notification a été supprimée');
        $this->redirectToPageUtilisateur($id_u, $page_moi);
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doNotificationEditAction()
    {
        $recuperateur = $this->getPostInfo();
        $id_u = $recuperateur->getInt('id_u');
        $id_e = $recuperateur->getInt('id_e');
        $type = $recuperateur->get('type');
        $daily_digest = $recuperateur->get('has_daily_digest');
        $page_moi = false;

        $this->getUtilisateur()->getInfo($id_u);
        if ($id_u === 0) {
            $this->verifEditMesNotifications($id_e, $type);

            $id_u = $this->getId_u();
            $page_moi = true;
        } else {
            $this->verifEditNotification($id_u, $id_e, $type);
        }

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
            $this->redirectToPageUtilisateur($id_u, $page_moi);
        }
        if ($all_checked) {
            $this->getNotification()->add($id_u, $id_e, $type, Notification::ALL_TYPE, $daily_digest);
            $this->redirectToPageUtilisateur($id_u, $page_moi);
        }
        foreach ($action_list as $action) {
            if (!$action_checked[$action['id']]) {
                continue;
            }
            $this->getNotification()->add($id_u, $id_e, $type, $action['id'], $daily_digest);
        }
        $this->redirectToPageUtilisateur($id_u, $page_moi);
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
        $this->verifDroit($userInfo['id_e'], 'utilisateur:edition');
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
        $this->verifDroit($userInfo['id_e'], 'utilisateur:edition');
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
        $this->verifDroit($userInfo['id_e'], 'utilisateur:edition');
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
        $this->verifDroit($userInfo['id_e'], 'utilisateur:edition');
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
     */
    private function verifDroitApi(int $id_u): void
    {
        $info = $this->getUtilisateur()->getInfo($id_u);
        $id_e = $info['id_e'];
        $is_api = $info['is_api'];
        if (
            $id_u !== $this->getId_u() &&
            !($is_api &&
                $this->getRoleUtilisateur()->hasDroit($this->getId_u(), 'utilisateur:edition', $id_e))
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
