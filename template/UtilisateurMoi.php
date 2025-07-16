<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var array $info
 * @var string $denominationEntiteDeBase
 * @var int $id_u
 * @var array $notification_list
 * @var array $arbre
 * @var array $all_module
 * @var array $tokens
 * @var bool $droit_entite_racine
 */

?>
<div class="box">
    <h2 id="desc-user-table">Vos informations</h2>
    <table class='table table-striped' aria-labelledby="desc-user-table">
        <tr>
            <th class="w140">Login</th>
            <td><?php hecho($info['login']); ?></td>
        </tr>

        <tr>
            <th>Prénom</th>
            <td><?php hecho($info['prenom']); ?></td>
        </tr>

        <tr>
            <th>Nom</th>
            <td><?php hecho($info['nom']); ?></td>
        </tr>

        <tr>
            <th>Email</th>
            <td><?php hecho($info['email']); ?></td>
        </tr>

        <tr>
            <th>Date d'inscription</th>
            <td><?php echo time_iso_to_fr($info['date_inscription']); ?></td>
        </tr>

        <tr>
            <th>Entité de base</th>
            <td>
                <a href='Entite/detail?id_e=<?php echo $info['id_e'] ?>'>
                    <?php if ($info['id_e']) : ?>
                        <?php hecho($denominationEntiteDeBase); ?>
                    <?php else : ?>
                        Utilisateur global
                    <?php endif; ?>
                </a>
            </td>
        </tr>
    </table>


    <a href='Utilisateur/modifPassword' class='btn btn-primary margin-right'>
        <i class="fa fa-pencil"></i>&nbsp;Modifier mon mot de passe
    </a>
    <a href='Utilisateur/modifEmail' class='btn btn-primary'><i class="fa fa-pencil"></i>&nbsp;Modifier mon email</a>
</div>

<div class="box">
    <h2 id="desc-roles-table">Vos rôles sur Pastell</h2>

    <table class='table table-striped' aria-labelledby="desc-roles-table">
        <tr>
            <th class="w140">Rôle</th>
            <th>Entité</th>
            <th>&nbsp;</th>
        </tr>

        <?php
        foreach ($this->getRoleUtilisateur()->getRole($id_u) as $infoRole) : ?>
            <tr>
                <td><?php hecho($infoRole['role']); ?></td>
                <td>
                    <?php if ($infoRole['id_e']) : ?>
                        <a href='Entite/detail?id_e=<?php echo $infoRole['id_e'] ?>'>
                            <?php hecho($infoRole['denomination']); ?>
                        </a>
                    <?php else : ?>
                        Toutes les collectivités
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="box">
    <h2 id="desc-notifications-table">Vos notifications</h2>
    <table class='table table-striped' aria-labelledby="desc-notifications-table">
        <tr>
            <th class="w140">Entité</th>
            <th>Type de dossier</th>
            <th>Action</th>
            <th>Type d'envoi</th>
            <th>&nbsp;</th>
        </tr>

        <?php
        foreach ($notification_list as $infoNotification) : ?>
            <tr>
                <td>
                    <?php if ($infoNotification['id_e']) : ?>
                        <a href='Entite/detail?id_e=<?php echo $infoNotification['id_e']; ?>'>
                            <?php hecho($infoNotification['denomination']); ?>
                        </a>
                    <?php else : ?>
                        Toutes les collectivités
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($infoNotification['type']) : ?>
                        <?php
                        hecho(
                            $this->getDocumentTypeFactory()->getFluxDocumentType($infoNotification['type'])->getName()
                        );
                        ?>
                    <?php else : ?>
                        Tous
                    <?php endif; ?>
                </td>
                <td>
                    <ul id='ulNotification'>
                        <?php foreach ($infoNotification['action'] as $action) : ?>
                            <li><?php echo $action ?: 'Toutes' ?></li>
                        <?php endforeach; ?>
                    </ul>
                </td>
                <td>
                    <p>
                        <?php
                        echo $infoNotification['daily_digest'] ? "Résumé journalier" : "Envoi à chaque événement" ?>
                    </p>
                </td>

                <td>
                    <a
                        class="btn btn-primary"
                        href='Utilisateur/notification?id_e=<?php
                        echo $infoNotification['id_e']; ?>&type=<?php
                        echo $infoNotification['type']; ?>'
                    >
                        <i class="fa fa-pencil"></i>&nbsp;Modifier
                    </a>

                    <a class='btn btn-danger'
                       href='Utilisateur/notificationSuppression?id_n=<?php echo $infoNotification['id_n']; ?>'
                    >
                        <i class="fa fa-trash"></i>&nbsp;Supprimer
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <h3>Ajouter une notification</h3>
    <div class="row">

        <form action='Utilisateur/notificationAjout' method='post' class='input-group align-items-center'>
            <?php $this->displayCSRFInput(); ?>

            <select name='id_e' class='select2_entite form-select col-md-1'>
                <?php if ($droit_entite_racine) : ?>
                    <option value='0'>Entité racine</option>
                <?php endif; ?>
                <?php foreach ($arbre as $entiteInfo) : ?>
                    <option value='<?php echo $entiteInfo['id_e']; ?>'>
                        <?php echo str_repeat("-", $entiteInfo['profondeur']); ?>
                        <?php hecho($entiteInfo['denomination']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php $this->getDocumentTypeHtml()->displaySelectWithCollectivite($all_module); ?>
            <select name='daily_digest' class="form-select col-md-2 me-2">
                <option value=''>Envoi à chaque événement</option>
                <option value='1'>Résumé journalier</option>
            </select>

            <button type='submit' class='btn btn-primary'><i class="fa fa-plus-circle"></i>&nbsp;Ajouter</button>
        </form>
    </div>
</div>


<div class="box">
    <h2 id="desc-token-table">Vos jetons d'authentification API</h2>
    <table class='table table-striped' aria-labelledby="desc-token-table">
        <tr>
            <th scope="col">Nom</th>
            <th scope="col">Créé le</th>
            <th scope="col">Expire le</th>
            <th scope="col">Action</th>
        </tr>

        <?php foreach ($tokens as $token) : ?>
            <tr>
                <td><?php hecho($token['name']); ?></td>
                <td>
                    <?php hecho($token['created_at']); ?>
                </td>
                <td>
                    <?php hecho($token['expired_at'] ?? 'Jamais'); ?>
                    <?php if ($token['is_expired']) : ?>
                        <p class="badge bg-danger">Expiré</p>
                    <?php endif; ?>
                </td>
                <td>
                    <a
                        class='btn btn-warning'
                        href='Utilisateur/renewToken?id=<?php echo $token['id']; ?>'
                        onclick="return confirm(
                                'Êtes-vous certain de vouloir renouveler ce jeton (l\'ancien token sera perdu) ?'
                                )"
                    >
                        <i class="fa fa-refresh"></i>&nbsp;Renouveler
                    </a>
                    <a
                        class='btn btn-danger'
                        href='Utilisateur/deleteToken?id=<?php echo $token['id']; ?>'
                        onclick="return confirm('Êtes-vous certain de vouloir supprimer définitivement ce jeton ?')"
                    >
                        <i class="fa fa-trash"></i>&nbsp;Supprimer
                    </a>

                </td>
            </tr>
        <?php endforeach;?>
    </table>

    <a href='Utilisateur/addToken' class='btn btn-primary'><i class="fa fa-pencil"></i>&nbsp;Ajouter un jeton</a>
</div>
