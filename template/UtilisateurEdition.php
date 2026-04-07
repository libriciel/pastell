<?php

/**
 * @var Gabarit $this
 * @var int $id_u
 * @var array $infoUtilisateur
 * @var RoleUtilisateur $roleUtilisateur
 * @var SQLQuery $sqlQuery
 * @var array $arbre
 * @var int $id_e
 * @var bool $new_user
 * @var bool $is_api
 */

use Pastell\Utilities\Certificate;

?>

<div class="box">
    <form action='Utilisateur/doEdition' method='post' enctype='multipart/form-data' autocomplete="off">
        <?php $this->displayCSRFInput(); ?>
        <input type='hidden' name='id_u' value='<?php echo $id_u ?>'>
        <input type="hidden" name="dont_delete_certificate_if_empty" value="true"/>

        <table class='table table-striped'>
            <tr>
                <th class="w300"><label for='login'>
                        Identifiant (login)
                        <span class='obl'>*</span></label></th>
                <td>
                    <input class="form-control col-md-4" type='text' name='login'
                           value='<?php hecho($infoUtilisateur['login']); ?>'/>
                </td>
            </tr>
            <tr id="email_row">
                <th>
                    <label for='email'>Email<span class='obl'>*</span></label>
                    <p class='form_commentaire'>
                        Ignoré pour les utilisateurs API avec authentification via jetons
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-4" type='text' name='email'
                           value='<?php hecho($infoUtilisateur['email']); ?>'/>
                </td>
            </tr>
            <tr>
                <th><label for='nom'>Nom<span class='obl'>*</span></label></th>
                <td>
                    <input class="form-control col-md-4" type='text' name='nom'
                           value='<?php hecho($infoUtilisateur['nom']); ?>'/>
                </td>
            </tr>
            <tr>
                <th><label for='prenom'>Prénom<span class='obl'>*</span></label></th>
                <td>
                    <input class="form-control col-md-4" type='text' name='prenom'
                           value='<?php hecho($infoUtilisateur['prenom']); ?>'/>
                </td>
            </tr>

            <?php
            $tabEntite = $roleUtilisateur->getEntite($this->getAuthentification()->getId(), 'entite:edition');
            $entiteListe = new EntiteListe($sqlQuery);
            ?>
            <tr>
                <th>Entité de base</th>
                <td>
                    <select name='id_e' class="form-select col-md-4">
                        <option value=''>Entité racine</option>
                        <?php foreach ($arbre as $entiteInfo) : ?>
                            <option value='<?php echo $entiteInfo['id_e'] ?>'
                                <?php echo $entiteInfo['id_e'] == $infoUtilisateur['id_e'] ? 'selected' : '' ?>
                            >
                                <?php for ($i = 0; $i < $entiteInfo['profondeur']; $i++) {
                                    echo "&nbsp&nbsp;";
                                } ?>
                                |_<?php hecho($entiteInfo['denomination']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php if ($new_user || $is_api) : ?>
            <tr>
                <th>
                    <label for='api_user'>Utilisateur API</label>
                    <p class='form_commentaire'>
                        pour authentification exclusivement par jetons
                    </p>
                </th>
                <td>
                    <input
                        type='checkbox'
                        name='api_user'
                        id='api_user'
                        <?php echo $is_api ? 'disabled checked' : '' ?>
                    />
                </td>
            </tr>
            <?php endif ?>
        </table>

        <?php if ($id_u) : ?>
            <a class='btn btn-outline-primary'
               href='Utilisateur/detail?id_u=<?php echo $id_u ?>'
            ><i class="fa fa-times-circle"></i>&nbsp;Annuler</a>
        <?php elseif ($id_e) : ?>
            <a class='btn btn-outline-primary'
               href='Entite/utilisateur?id_e=<?php echo $id_e ?>'
            ><i class="fa fa-times-circle"></i>&nbsp;Annuler</a>
        <?php else : ?>
            <a class='btn btn-outline-primary'
               href='Entite/utilisateur?id_e=<?php echo $id_e ?>'
            ><i class="fa fa-times-circle"></i>&nbsp;Annuler</a>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">
            <i class="fa fa-floppy-o"></i>&nbsp;Enregistrer
        </button>

    </form>
</div>

<script>
    /** @type HTMLInputElement */
    const checkbox = document.getElementById('api_user');
    let emailRow = document.getElementById('email_row');

    if(checkbox.checked) {
        emailRow.setAttribute('hidden', 'true');
    }
    checkbox.addEventListener('change', () => {
        if(checkbox.checked) {
            emailRow.setAttribute('hidden', 'true');
        } else {
            emailRow.removeAttribute('hidden');
        }
    });
</script>
