<?php

declare(strict_types=1);

use Pastell\Service\LoginAttemptLimit;
use Pastell\Service\Utilisateur\MfaAuthAction;
use Random\RandomException;

class MfaControler extends PastellControler
{
    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function authRequiredAction(): void
    {
        $action = MfaAuthAction::tryFromParam($this->getGetInfo()->get('action'));
        if ($action === null) {
            $this->redirect('/Utilisateur/moi');
        }
        if (!$this->getMfaService()->isEnabled($this->getId_u())) {
            $this->redirect('/Utilisateur/moi');
        }

        $this->setViewParameter('action', $action->value);
        $this->setViewParameter('description', $action->description());
        $this->setViewParameter('submit_label', $action->submitLabel());
        $this->setViewParameter('page_title', 'Authentification nécessaire');
        $this->setViewParameter('template_milieu', 'MfaAuthRequired');
        $this->setViewParameter('pages_without_left_menu', true);
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     * @throws RandomException
     */
    public function doAuthRequiredAction(): void
    {
        $action = MfaAuthAction::tryFromParam($this->getPostInfo()->get('action'));
        if ($action === null) {
            $this->redirect('/Utilisateur/moi');
        }

        $id_u = $this->getId_u();
        if (!$this->getMfaService()->isEnabled($id_u)) {
            $this->redirect('/Utilisateur/moi');
        }

        $login = $this->getAuthentification()->getLogin();
        $loginAttemptLimit = $this->getObjectInstancier()->getInstance(LoginAttemptLimit::class);
        if ($loginAttemptLimit->getRateLimit($login)->getRemainingTokens() <= 0) {
            $this->setLastError('Trop de tentatives, veuillez réessayer plus tard.');
            $this->redirect('/Mfa/authRequired?action=' . $action->value);
        }

        if (!$this->getUtilisateur()->verifPassword($id_u, $this->getPostInfo()->get('password'))) {
            $loginAttemptLimit->consumeLoginAttempt($login);
            $this->setLastError('Votre mot de passe est incorrect.');
            $this->redirect('/Mfa/authRequired?action=' . $action->value);
        }
        $loginAttemptLimit->resetLoginAttempt($login);

        match ($action) {
            MfaAuthAction::REGENERATE => $this->doRegenerateRecoveryCodes($id_u),
            MfaAuthAction::DESACTIVATION => $this->doDesactivation($id_u),
        };
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function enrolementAction(): void
    {
        $id_u = $this->getId_u();
        $mfaService = $this->getMfaService();

        if ($mfaService->isEnabled($id_u)) {
            $this->setLastMessage('La double authentification est déjà activée.');
            $this->redirect('/Utilisateur/moi');
        }

        $info = $this->getUtilisateur()->getInfo($id_u);

        $pendingSecret = $mfaService->getInfo($id_u)['secret'] ?? '';
        if ($pendingSecret !== '') {
            $secret = $pendingSecret;
        } else {
            $secret = $mfaService->generateSecret();
            $mfaService->enroll($id_u, $secret);
        }

        $uri = $mfaService->getProvisioningUri($secret, $info['login']);

        $this->setViewParameter('secret', $secret);
        $this->setViewParameter('qr_code_svg', $mfaService->getQrCodeSvg($uri));
        $this->setViewParameter('page_title', 'Activer la double authentification');
        $this->setViewParameter('template_milieu', 'MfaEnrolement');
        $this->setViewParameter('pages_without_left_menu', true);
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function cancelEnrolementAction(): void
    {
        $id_u = $this->getId_u();
        $info = $this->getMfaService()->getInfo($id_u);
        if (!empty($info) && empty($info['is_enabled'])) {
            $this->getMfaService()->delete($id_u);
        }
        $this->redirect('/Utilisateur/moi');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     * @throws RandomException
     */
    public function doEnrolementAction(): void
    {
        $id_u = $this->getId_u();
        $mfaService = $this->getMfaService();
        $code = $this->getPostInfo()->get('code');

        $info = $mfaService->getInfo($id_u);
        if (empty($info['secret']) || !$mfaService->verify($info['secret'], $code)) {
            $this->setLastError('Le code saisi est invalide.');
            $this->redirect('/Mfa/enrolement');
        }

        $mfaService->confirm($id_u);
        $userInfo = $this->getUtilisateur()->getInfo($id_u);
        $this->getJournal()->add(
            Journal::MODIFICATION_UTILISATEUR,
            $userInfo['id_e'],
            Journal::NO_ID_D,
            'double authentification activée',
            "{$userInfo['login']} ($id_u) a activé la double authentification"
        );
        $this->renderRecoveryCodes($mfaService->generateRecoveryCodes($id_u));
    }

    /**
     * @throws NotFoundException
     * @throws RandomException
     */
    private function doRegenerateRecoveryCodes(int $id_u): void
    {
        $recoveryCodes = $this->getMfaService()->generateRecoveryCodes($id_u);
        $userInfo = $this->getUtilisateur()->getInfo($id_u);
        $this->getJournal()->add(
            Journal::MODIFICATION_UTILISATEUR,
            $userInfo['id_e'],
            Journal::NO_ID_D,
            'codes de récupération régénérés',
            "{$userInfo['login']} ($id_u) a régénéré ses codes de récupération"
        );

        $this->renderRecoveryCodes($recoveryCodes);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function doDesactivation(int $id_u): void
    {
        $this->getMfaService()->delete($id_u);
        $userInfo = $this->getUtilisateur()->getInfo($id_u);
        $this->getJournal()->add(
            Journal::MODIFICATION_UTILISATEUR,
            $userInfo['id_e'],
            Journal::NO_ID_D,
            'double authentification désactivée',
            "{$userInfo['login']} ($id_u) a désactivé la double authentification"
        );
        $this->setLastMessage('La double authentification est désactivée.');
        $this->redirect('/Utilisateur/moi');
    }

    /**
     * @param string[] $recoveryCodes
     * @throws NotFoundException
     */
    private function renderRecoveryCodes(array $recoveryCodes): void
    {
        $this->setViewParameter('recovery_codes', $recoveryCodes);
        $this->setViewParameter('page_title', 'Codes de récupération');
        $this->setViewParameter('template_milieu', 'MfaRecoveryCodes');
        $this->setViewParameter('pages_without_left_menu', true);
        $this->renderDefault();
    }
}
