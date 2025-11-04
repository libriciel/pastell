<?php

use Pastell\Service\TokenGenerator;
use Pastell\Service\Utilisateur\UserCreationService;

class AdminControler extends Controler
{
    /** @return RoleDroit */
    private function getRoleDroit()
    {
        return $this->getInstance(RoleDroit::class);
    }

    /** @return RoleSQL */
    private function getRoleSQL()
    {
        return $this->getInstance(RoleSQL::class);
    }

    private function getUtilisateur(): UtilisateurSQL
    {
        return $this->getInstance(UtilisateurSQL::class);
    }

    /** @return RoleUtilisateur */
    private function getRoleUtilisateur()
    {
        return $this->getInstance(RoleUtilisateur::class);
    }

    private function getEntiteSQL(): EntiteSQL
    {
        return $this->getInstance(EntiteSQL::class);
    }

    public function createAdmin(string $login, string $password, string $email): bool
    {
        $this->fixDroit();
        try {
            $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)->create(
                $login,
                $email,
                'admin',
                'admin',
                0,
                $password,
                false
            );
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            return false;
        }
        $this->getRoleUtilisateur()->addRole($id_u, 'admin', 0);
        return true;
    }

    public function fixDroit()
    {
        $this->getRoleSQL()->edit('admin', 'Administrateur');

        foreach ($this->getRoleDroit()->getAllDroit() as $droit) {
            if (
                !str_contains($droit, '-destinataire:')
                && !str_contains($droit, '-reponse:')
                && !str_contains($droit, 'changement-email:')
            ) {
                $this->getRoleSQL()->addDroit('admin', $droit);
            }
        }
        $this->getEntiteSQL()->updateAllAncestors();
    }

    public function createOrUpdateAdmin(string $login, string $email): void
    {
        $pastellLogger = $this->getObjectInstancier()->getInstance(PastellLogger::class);
        $utilisateur = $this->getUtilisateur();
        $utilisateurInfo = $utilisateur->getInfoByLogin($login);
        if (! $utilisateurInfo) {
            $tokenGenerator = $this->getObjectInstancier()->getInstance(TokenGenerator::class);
            $pastellLogger->info("L'utilisateur {$login} n'existe pas.");
            $password = $tokenGenerator->generate();
            $createAdminResult = $this->createAdmin(
                $login,
                $password,
                $email
            );
            $pastellLogger->info("Mot de passe de l'administrateur : " . $password);
            if (! $createAdminResult) {
                $pastellLogger->error(
                    "Erreur lors de la création de l'utilisateur :  " . $this->getLastError()->getLastError()
                );
                return;
            }
            $pastellLogger->info("Création de l'utilisateur {$login} OK");
            return;
        }
        $pastellLogger->info("L'utilisateur {$login} existe déjà");
        $this->getUtilisateur()->setEmail($utilisateurInfo['id_u'], $email);
        $pastellLogger->info("Mise à jour de l'utilisateur $login.");
    }
}
