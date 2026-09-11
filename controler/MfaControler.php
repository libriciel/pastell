<?php

declare(strict_types=1);

use Pastell\Service\LoginAttemptLimit;
use Pastell\Service\Utilisateur\MfaManagementAction;
use Pastell\Service\Utilisateur\MfaObligationService;
use Pastell\Service\Utilisateur\UserAuthenticationService;
use Random\RandomException;

class MfaControler extends PastellControler
{
    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function _beforeAction(): void
    {
        parent::_beforeAction();
        if ($this->getUserAuthenticationService()->isExternal($this->getId_u())) {
            $this->redirect('/Utilisateur/moi');
        }
    }

    private function getUserAuthenticationService(): UserAuthenticationService
    {
        return $this->getObjectInstancier()->getInstance(UserAuthenticationService::class);
    }

    private function getMfaObligationService(): MfaObligationService
    {
        return $this->getObjectInstancier()->getInstance(MfaObligationService::class);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function authRequiredAction(): void
    {
        $action = MfaManagementAction::tryFromParam($this->getGetInfo()->get('action'));
        if ($action === null) {
            $this->redirect('/Utilisateur/moi');
        }
        if ($this->getMfaService()->isEnabled($this->getId_u()) !== $action->requiresEnabledMfa()) {
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
        $this->checkPostRequest('/Utilisateur/moi');
        $action = MfaManagementAction::tryFromParam($this->getPostInfo()->get('action'));
        if ($action === null) {
            $this->redirect('/Utilisateur/moi');
        }

        $id_u = $this->getId_u();
        if ($this->getMfaService()->isEnabled($id_u) !== $action->requiresEnabledMfa()) {
            $this->redirect('/Utilisateur/moi');
        }
        $login = $this->getAuthentification()->getLogin();
        $loginAttemptLimit = $this->getObjectInstancier()->getInstance(LoginAttemptLimit::class);
        if ($loginAttemptLimit->getRateLimit($login)->getRemainingTokens() <= 0) {
            $this->setLastError('Trop de tentatives, veuillez réessayer plus tard.');
            $this->redirect('/Mfa/authRequired?action=' . $action->value);
        }

        try {
            $passwordValid = $this->getUserAuthenticationService()
                ->verifyPassword($id_u, (string) $this->getPostInfo()->get('password'));
        } catch (UnrecoverableException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect('/Mfa/authRequired?action=' . $action->value);
        }
        if (!$passwordValid) {
            $loginAttemptLimit->consumeLoginAttempt($login);
            $this->setLastError('Votre mot de passe est incorrect.');
            $this->redirect('/Mfa/authRequired?action=' . $action->value);
        }
        $loginAttemptLimit->resetLoginAttempt($login);

        try {
            match ($action) {
                MfaManagementAction::ENABLE => $this->startEnrolement($id_u),
                MfaManagementAction::REGENERATE => $this->renderRecoveryCodes(
                    $this->getMfaService()->regenerateRecoveryCodes($id_u)
                ),
                MfaManagementAction::DISABLE => $this->disable($id_u),
                MfaManagementAction::RESET => $this->reset($id_u),
            };
        } catch (UnrecoverableException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect('/Utilisateur/moi');
        }
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function startEnrolement(int $id_u): void
    {
        $this->startMfaEnrolment($id_u);
        $this->redirect('/Mfa/enrolement');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function redirectIfEnrolmentNotAuthorised(): void
    {
        if (!$this->getAuthentification()->isMfaEnrolmentAuthorised()) {
            $this->redirect('/Mfa/authRequired?action=' . MfaManagementAction::ENABLE->value);
        }
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
        $this->redirectIfEnabled($id_u);
        $this->redirectIfEnrolmentNotAuthorised();

        $enrolment = $mfaService->getEnrolment($id_u, $this->getUtilisateur()->getInfo($id_u)['login']);
        if ($enrolment === null) {
            $this->redirect('/Utilisateur/moi');
        }

        $this->setViewParameter('secret', $enrolment->secret);
        $this->setViewParameter('qr_code_svg', $enrolment->qrCodeSvg);
        $this->setViewParameter('enrolment_required', $this->getMfaObligationService()->mustEnroll($id_u));
        $this->setViewParameter('page_title', 'Activer la double authentification');
        $this->setViewParameter('template_milieu', 'MfaEnrolement');
        $this->setViewParameter('pages_without_left_menu', true);
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     * @throws RandomException
     */
    public function doEnrolementAction(): void
    {
        $this->checkPostRequest('/Utilisateur/moi');
        $id_u = $this->getId_u();
        $code = (string)$this->getPostInfo()->get('code');
        $this->redirectIfEnrolmentNotAuthorised();

        try {
            $recovery_codes = $this->getMfaService()->activate($id_u, $code);
        } catch (UnrecoverableException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect('/Mfa/enrolement');
        }
        $this->getAuthentification()->clearMfaEnrolmentAuthorisation();
        $this->renderRecoveryCodes($recovery_codes);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function cancelEnrolementAction(): void
    {
        $this->checkPostRequest('/Utilisateur/moi');
        $id_u = $this->getId_u();
        try {
            $this->getMfaObligationService()->cancelEnrolment($id_u);
        } catch (UnrecoverableException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect('/Mfa/enrolement');
        }
        $this->getAuthentification()->clearMfaEnrolmentAuthorisation();
        $this->redirect('/Utilisateur/moi');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function redirectIfEnabled(int $id_u): void
    {
        if (!$this->getMfaService()->canEnrol($id_u)) {
            $this->setLastMessage('La double authentification est déjà activée.');
            $this->redirect('/Utilisateur/moi');
        }
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    private function disable(int $id_u): void
    {
        $this->getMfaObligationService()->disableMfa($id_u);
        $this->setLastMessage('La double authentification est désactivée.');
        $this->redirect('/Utilisateur/moi');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    private function reset(int $id_u): void
    {
        $this->getMfaObligationService()->resetMfa($id_u);
        $this->setLastMessage('Configurez votre nouvelle double authentification.');
        $this->startEnrolement($id_u);
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
