<?php

/**
 * @var Gabarit $this
 * @var array $role_authorized
 * @var array $info
 * @var string $denominationEntiteDeBase
 * @var Certificate $certificat
 * @var bool $utilisateur_edition
 * @var array $arbre
 * @var array $notification_list
 * @var array $all_module
 * @var int $id_u
 * @var int $id_current_u
 * @var bool $enable_certificate_authentication
 * @var Authentification $authentification
 * @var array $tokens
 * @var array $tree
 */

use Pastell\Utilities\Certificate;

?>

<a class='btn btn-link' href='Entite/utilisateur?id_e=<?php echo $info['id_e'] ?>'>
    <i class="fa fa-arrow-left"></i>&nbsp;Retour à la liste des utilisateurs</a>

<?php if (!$info['is_enabled']) : ?>
    <div class="alert alert-danger">Cet utilisateur est désactivé</div>
<?php endif; ?>
<div class="box">

    <h2>Détail de l'utilisateur <?php hecho($info['prenom'] . " " . $info['nom']); ?></h2>

    <table class='table table-striped'>

        <tr>
            <th class='w200'>Login</th>
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
            <td><?php echo time_iso_to_fr($info['date_inscription']) ?></td>
        </tr>
        <tr>
            <th>Activé</th>
            <td><?php echo $info['is_enabled'] ? 'Oui' : 'Non' ?></td>
        </tr>
        <tr>
            <th>Utilisateur API pour authentification exclusivement par jetons</th>
            <td><?php echo $info['is_api'] ? 'Oui' : 'Non' ?></td>
        </tr>

        <tr>
            <th>Entité de base</th>
            <td>
                <a href='Entite/detail?id_e=<?php echo $info['id_e'] ?>' ">
                <?php if ($info['id_e']) : ?>
                    <?php hecho($denominationEntiteDeBase); ?>
                <?php else : ?>
                    Entité racine
                <?php endif; ?>
                </a>
            </td>
        </tr>

        <?php if ($enable_certificate_authentication && $certificat->isValid()) : ?>
            <tr>
                <th>Certificat</th>
                <td>
                    <a href='Utilisateur/certificat?verif_number=<?php echo $certificat->getMD5() ?>'
                    ><?php echo $certificat->getName() ?></a>
                </td>
            </tr>
        <?php endif; ?>

        <?php
        if (
            $this->getRoleUtilisateur()->hasDroit($authentification->getId(), 'journal:lecture', $info['id_e'])
        ) : ?>
            <tr>
                <th>Dernières actions</th>
                <td>
                    <a href='Journal/index?id_u=<?php echo $id_u ?>'>
                        Dernières actions de <?php hecho($info['prenom'] . " " . $info['nom']); ?>
                    </a>
                </td>
            </tr>
        <?php endif; ?>

    </table>


    <?php if ($utilisateur_edition) : ?>
        <table>
            <tr>
                <td>
                    <a class='btn btn-primary' href="Utilisateur/edition?id_u=<?php echo $id_u ?>">
                        <i class="fa fa-pencil"></i>&nbsp;Modifier
                    </a>&nbsp;
                </td>
                <td>
                    <form action='<?php
                    if ($info['is_enabled']) {
                        $this->url('Utilisateur/disable');
                    } else {
                        $this->url('Utilisateur/enable');
                    } ?>' method='post'>
                        <?php $this->displayCSRFInput() ?>
                        <input type='hidden' name='id_u' value='<?php echo $id_u ?>'/>
                        <button type='submit' class='btn btn-warning'>
                            <i class="fa <?php echo $info['is_enabled'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                            <?php echo $info['is_enabled'] ? 'Désactiver' : 'Activer' ?>
                        </button>&nbsp;
                    </form>
                </td>
                <td>
                    <a
                            class='btn btn-danger'
                            href="<?php $this->url("Utilisateur/suppression?id_u=$id_u") ?>"
                    ><i class='fa fa-trash'></i>&nbsp;Supprimer</a>
                </td>
            </tr>
        </table>
    <?php endif; ?>

</div>

<div class="box">
    <h2>Rôle de l'utilisateur</h2>

    <table class='table table-striped'>
        <tr>
            <th class='w200'>Rôle</th>
            <th>Entité</th>
            <th>&nbsp;</th>
        </tr>

        <?php foreach ($this->getRoleUtilisateur()->getRole($id_u) as $infoRole) : ?>
            <tr>
                <td><?php hecho($infoRole['role']); ?></td>
                <td>
                    <?php if ($infoRole['id_e']) : ?>
                        <a href='Entite/detail?id_e=<?php echo $infoRole['id_e'] ?>'
                        ><?php hecho($infoRole['denomination']); ?></a>
                    <?php else : ?>
                        Toutes les collectivités
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (
                            $utilisateur_edition &&
                            ($infoRole['role'] !== 'aucun droit' ||
                            count($this->getRoleUtilisateur()->getRole($id_u)) > 1)
) : ?>
                        <?php
                        $deleteRoleUrl = \sprintf(
                            'Utilisateur/supprimeRole?id_u=%s&role=%s&id_e=%s',
                            $id_u,
                            $infoRole['role'],
                            $infoRole['id_e'],
                        );
                        ?>
                        <a class='btn btn-danger'
                           href='<?php echo $deleteRoleUrl; ?>'>
                            <i class="fa fa-times-circle"></i>&nbsp;Retirer le rôle
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <?php if ($utilisateur_edition && $role_authorized) : ?>
        <h3>Ajouter un rôle</h3>

        <form action='Utilisateur/ajoutRole' method='post' class='d-flex flex-row align-items-center'>
            <?php $this->displayCSRFInput(); ?>
            <input type='hidden' name='id_u' value='<?php echo $id_u ?>'/>
            <input id='role-entity_id' type='hidden' name='id_e' value=''/>

            <select name='role' class='select2_role p-0'>
                <option value=''>...</option>
                <?php foreach ($role_authorized as $role_info) : ?>
                    <option value='<?php hecho($role_info['role']); ?>'>
                        <?php hecho($role_info['libelle']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="treeselect-role-entity p-2"></div>

            <button type='submit' class='btn btn-primary'>
                <i class="fa fa-plus-circle"></i>&nbsp;Ajouter
            </button>
        </form>

        <br/><br/>

        <div class="alert-info alert">
            Note : Vous ne pouvez attribuer un rôle que si vous en possédez déjà tous les droits
        </div>


    <?php endif; ?>
</div>

<?php if (isset($notification_list)) { ?>
    <div class="box">
        <h2>Notification de l'utilisateur</h2>
        <table class='table table-striped'>
            <tr>
                <th class='w200'>Entité</th>
                <th>Type de dossier</th>
                <th>Actions</th>
                <th>Type d'envoi</th>
                <th>&nbsp;</th>
            </tr>

            <?php foreach ($notification_list as $infoNotification) : ?>
                <tr>
                    <td>
                        <?php if ($infoNotification['id_e']) : ?>
                            <a href='Entite/detail?id_e=<?php echo $infoNotification['id_e'] ?>'
                            ><?php hecho($infoNotification['denomination']); ?></a>
                        <?php else : ?>
                            Toutes les collectivités
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($infoNotification['type']) : ?>
                            <?php hecho(
                                $this
                                    ->getDocumentTypeFactory()
                                    ->getFluxDocumentType($infoNotification['type'])
                                    ->getName()
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
                        <?php
                        echo $infoNotification['daily_digest'] ? 'Résumé journalier' : 'Envoi à chaque événement';
                        ?>
                        <br/>
                    </td>

                    <td>
                        <?php if ($utilisateur_edition) : ?>
                            <?php
                            $userNotificationUrl = \sprintf(
                                'Utilisateur/notification?id_u=%s&id_e=%s&type=%s',
                                $infoNotification['id_u'],
                                $infoNotification['id_e'],
                                $infoNotification['type'],
                            );
                            ?>
                            <a class="btn btn-primary"
                               href='<?php echo $userNotificationUrl; ?>'
                            ><i class="fa fa-pencil"></i>&nbsp;Modifier</a>

                            <a class='btn btn-danger'
                               href='Utilisateur/notificationSuppression?id_n=<?php echo $infoNotification['id_n'] ?>'>
                                <i class="fa fa-trash"></i>&nbsp;Supprimer
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php if ($utilisateur_edition) : ?>
            <h3>Ajouter une notification</h3>
            <form action='Utilisateur/notificationAjout' method='post' class='input-group align-items-center'>
                <?php $this->displayCSRFInput(); ?>
                <input type='hidden' name='id_u' value='<?php echo $id_u ?>'/>
                <select name='id_e' class='select2_entite form-select col-md-1'>
                    <option></option>
                    <option value='0'>Entité racine</option>
                    <?php foreach ($arbre as $entiteInfo) : ?>
                        <option value='<?php echo $entiteInfo['id_e'] ?>'>
                            <?php echo str_repeat("-", $entiteInfo['profondeur']); ?>
                            <?php hecho($entiteInfo['denomination']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php $this->getDocumentTypeHtml()->displaySelect('', $all_module); ?>
                <select name='daily_digest' class="form-select col-md-2 me-2">
                    <option value=''>Envoi à chaque événement</option>
                    <option value='1'>Résumé journalier</option>
                </select>

                <button type='submit' class='btn btn-primary'><i class="fa fa-plus-circle"></i>&nbsp;Ajouter</button>
            </form>
        <?php endif; ?>

    </div>
<?php } ?>

<?php
if ($id_u == $id_current_u || ($utilisateur_edition && $info['is_api'])) : ?>
    <div class="box">
        <h2 id="desc-token-table">Jetons d'authentification API</h2>
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
                            <p class="badge badge-danger">Expiré</p>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a
                                class='btn btn-warning'
                                href='Utilisateur/renewToken?id=<?php
                                echo $token['id'] . '&source=detail'; ?>'
                                onclick="return confirm(
                                'Êtes-vous certain de vouloir renouveler ce jeton (l\'ancien token sera perdu) ?'
                                )"
                        >
                            <i class="fa fa-refresh"></i>&nbsp;Renouveler
                        </a>
                        <a
                                class='btn btn-danger'
                                href='Utilisateur/deleteToken?id=<?php
                                echo $token['id'] . '&source=detail'; ?>'
                                onclick="return confirm('Êtes-vous certain de vouloir supprimer définitivement ce jeton ?')"
                        >
                            <i class="fa fa-trash"></i>&nbsp;Supprimer
                        </a>

                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <a href='Utilisateur/addToken?<?php
        echo '&id_u=' . $id_u . '&source=detail' ?>' class='btn btn-primary'><i class="fa fa-pencil"></i>&nbsp;Ajouter
            un jeton</a>
    </div>
<?php endif; ?>


<script type="module">
    const domElement = document.querySelector('.treeselect-role-entity')
    const treeselect = new Treeselect({
        placeholder: 'Sélectionner une entité',
        parentHtmlContainer: domElement,
        options: <?php echo $tree; ?>,
        isSingleSelect: true,
        showTags: false,
        openLevel: 3
    })

    treeselect.srcElement.addEventListener('input', (e) => {
        document.getElementById('role-entity_id').value = e.detail;
    })
</script>
