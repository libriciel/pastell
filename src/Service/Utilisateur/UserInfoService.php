<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use Exception;
use NotFoundException;
use UtilisateurSQL;

final class UserInfoService
{
    public function __construct(
        private readonly UtilisateurSQL $utilisateurSQL,
    ) {
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function getUserFromData(array $data): array
    {
        if (!empty($data['id_u'])) {
            $id_u = $data['id_u'];
            $user_info = $this->utilisateurSQL->getInfo($id_u);
            if (!$user_info) {
                throw new NotFoundException("L'identifiant de l'utilisateur n'existe pas : {id_u=$id_u}");
            }
            return $user_info;
        }

        if (isset($data['login']) && is_scalar($data['login'])) {
            $login = (string)$data['login'];
            $user_info = $this->utilisateurSQL->getInfoByLogin($login);
            if (!$user_info) {
                throw new NotFoundException("Le login de l'utilisateur n'existe pas : {login=$login}");
            }
            return $user_info;
        }

        throw new Exception("Aucun paramètre permettant la recherche de l'utilisateur n'a été renseigné");
    }
}
