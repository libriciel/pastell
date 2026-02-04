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
 * @var bool $enable_certificate_authentication
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
                    <a href='Utilisateur/certificat?verif_number=<?php echo $certificat->getMD5() ?>'><?php echo $certificat->getName() ?></a>
                </td>
            </tr>
        <?php endif; ?>

        <?php if ($this->RoleUtilisateur->hasDroit($authentification->getId(), "journal:lecture", $info['id_e'])) : ?>
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

        <?php foreach ($this->RoleUtilisateur->getRole($id_u) as $infoRole) : ?>
            <tr>
                <td><?php hecho($infoRole['role']); ?></td>
                <td>
                    <?php if ($infoRole['id_e']) : ?>
                        <a href='Entite/detail?id_e=<?php echo $infoRole['id_e'] ?>'><?php hecho($infoRole['denomination']); ?></a>
                    <?php else : ?>
                        Toutes les collectivités
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($utilisateur_edition) : ?>
                        <a class='btn btn-danger'
                           href='Utilisateur/supprimeRole?id_u=<?php echo $id_u ?>&role=<?php echo $infoRole['role'] ?>&id_e=<?php echo $infoRole['id_e'] ?>'>
                            <i class="fa fa-times-circle"></i>&nbsp;Retirer le rôle
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <?php if ($utilisateur_edition && $role_authorized) : ?>
        <h3>Ajouter un rôle</h3>

        <form action='Utilisateur/ajoutRole' method='post' class='form-inline'>
            <?php $this->displayCSRFInput(); ?>
            <input type='hidden' name='id_u' value='<?php echo $id_u ?>'/>

            <select name='role' class='select2_role form-control col-md-1'>
                <option value=''>...</option>
                <?php foreach ($role_authorized as $role_info) : ?>
                    <option value='<?php hecho($role_info['role']); ?>'> <?php hecho($role_info['libelle']); ?> </option>
                <?php endforeach; ?>
            </select>

            <div class="dropdown hierarchy-select mr-2" id="entity-selection-for-role">
                <i class="fa fa-caret-down position-absolute" style="right:2%; top:30%; color:#7f8686"></i>
                <button type="button" style="background-color: #ffffff; color: #474d4d; border-color:#a4adad;"
                        class="text-left btn btn-outline-secondary btn-block" id="hierarchy-select-button"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                </button>
                <div class="dropdown-menu" aria-labelledby="hierarchy-select-button">
                    <div class="hs-searchbox">
                        <input type="text" class="form-control w-100" autocomplete="off" aria-label="Recherche entité">
                    </div>
                    <div class="hs-menu-inner">
                        <a  class="dropdown-item" data-value="0" data-level="0">Entité racine</a>
                        <?php foreach ($arbre as $entiteInfo) : ?>
                            <a class="dropdown-item" data-value='<?php echo $entiteInfo['id_e'] ?>'
                               data-level="<?php echo $entiteInfo['profondeur'] + 2; ?>">
                                <?php hecho($entiteInfo['denomination']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input class="d-none" name="id_e" readonly="readonly"
                       aria-hidden="true" type="text" aria-label="Entité"/>
            </div>

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
                            <a href='Entite/detail?id_e=<?php echo $infoNotification['id_e'] ?>'><?php hecho($infoNotification['denomination']); ?></a>
                        <?php else : ?>
                            Toutes les collectivités
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($infoNotification['type']) : ?>
                            <?php
                            hecho($this->DocumentTypeFactory->getFluxDocumentType($infoNotification['type'])->getName());
                            ?>
                        <?php else : ?>
                            Tous
                        <?php endif; ?>
                    </td>
                    <td>
                        <ul id='ulNotification'>
                            <?php foreach ($infoNotification['action'] as $action) : ?>
                                <li><?php echo $action ? $action : 'Toutes' ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                    <td>
                        <?php echo $infoNotification['daily_digest'] ? "Résumé journalier" : "Envoi à chaque événement" ?>
                        <br/>
                    </td>

                    <td>
                        <?php if ($utilisateur_edition) : ?>
                            <a class="btn btn-primary"
                               href='Utilisateur/notification?id_u=<?php echo $infoNotification['id_u'] ?>&id_e=<?php echo $infoNotification['id_e'] ?>&type=<?php echo $infoNotification['type'] ?>'><i
                                        class="fa fa-pencil"></i>&nbsp;Modifier</a>

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
            <form action='Utilisateur/notificationAjout' method='post' class='form-inline'>
                <?php $this->displayCSRFInput(); ?>
                <input type='hidden' name='id_u' value='<?php echo $id_u ?>'/>
                <select name='id_e' class='select2_entite form-control col-md-1'>
                    <option></option>
                    <option value='0'>Entité racine</option>
                    <?php foreach ($arbre as $entiteInfo) : ?>
                        <option value='<?php echo $entiteInfo['id_e'] ?>'>
                            <?php echo str_repeat("-", $entiteInfo['profondeur']); ?>
                            <?php hecho($entiteInfo['denomination']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php $this->DocumentTypeHTML->displaySelect('', $all_module); ?>
                <select name='daily_digest' class="form-control col-md-2 mr-2">
                    <option value=''>Envoi à chaque événement</option>
                    <option value='1'>Résumé journalier</option>
                </select>

                <button type='submit' class='btn btn-primary'><i class="fa fa-plus-circle"></i>&nbsp;Ajouter</button>
            </form>
        <?php endif; ?>

    </div>
<?php } ?>

<script>
    $('#entity-selection-for-role').hierarchySelect({
        width: "auto"
    });
</script>
