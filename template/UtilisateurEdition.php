<?php

/**
 * @var Gabarit $this
 * @var int $id_u
 * @var array $infoUtilisateur
 * @var bool $enable_certificate_authentication
 * @var Certificate $certificat
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
            <tr>
                <th><label for='email'>Email<span class='obl non-api-required'>*</span></label></th>
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
            <?php if ($enable_certificate_authentication) : ?>
                <tr>
                    <th><label for='certificat'>Certificat (PEM)</label></th>
                    <td><input class="btn btn-outline-primary col-md-4" type='file' name='certificat'/><br/>
                        <?php if ($certificat->isValid()) : ?>
                            <?php echo $certificat->getName() ?>&nbsp;-&nbsp;
                            <a class='btn btn-mini btn-danger'
                               href="Utilisateur/supprimerCertificat?id_u=<?php echo $id_u ?>"
                            >Supprimer</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>

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
                <th><label for='api_user'>Utilisateur exclusivement API</label></th>
                <td>

                    <input class="" type='checkbox' name='api_user' id='api_user' <?php
                    if ($is_api) :
                        echo 'disabled checked value="on"';
                    endif; ?>/>
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
    let checkbox = document.getElementById('api_user');
    let elementsToHide = document.querySelectorAll('.non-api-required');
    if(checkbox.value === "on")
    {
        toggle();
    }
    checkbox.addEventListener('change', function () {
        toggle();
    });

    function toggle() {
        for (let i = 0; i < elementsToHide.length; i++) {
            if (checkbox.checked) {
                elementsToHide[i].style.opacity = "0";
            } else {
                elementsToHide[i].style.opacity = "1";
            }
        }
    }
</script>
