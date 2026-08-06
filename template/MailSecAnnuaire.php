<?php

/**
 * @var Gabarit $this
 * @var int $id_e
 * @var array $infoEntite
 * @var string $search
 * @var array $groupe_list
 * @var int $id_g
 * @var bool $annuaire_edition
 * @var int $offset
 * @var int $limit
 * @var int $nb_email
 * @var array $listUtilisateur
 */

?>
<a class='btn btn-link' href='Entite/detail?id_e=<?php echo $id_e ?>'
><i class="fas fa-arrow-left"></i>&nbsp;Administration de <?php hecho($infoEntite['denomination']); ?></a>

<div class='box'>

    <a class='btn btn-link' href='MailSec/groupeList?id_e=<?php echo $id_e ?>'
    ><i class='fas fa-eye'></i>&nbsp;Visualiser les groupes</a>

    <a class='btn btn-link' href='MailSec/groupeRoleList?id_e=<?php echo $id_e ?>'
    ><i class='fas fa-eye'></i>&nbsp;Visualiser les groupes basés sur les rôles</a>

</div>

<div class="box">
    <h2>Liste des contacts de <?php hecho($infoEntite['denomination']); ?></h2>

    <form action='MailSec/annuaire' method='get' class="input-group">
        <input type='hidden' name='id_e' value='<?php echo $id_e; ?>'/>
        <input type='text' name='search' value='<?php echo $search; ?>'
               class="form-control col-md-2 me-2" placeholder="Nom ou email"/>
        <select name='id_g' class="form-select col-md-2 me-2">
            <option value=''>Tous les groupes</option>
            <?php foreach ($groupe_list as $groupe) : ?>
                <option value='<?php echo $groupe['id_g'] ?>'
                    <?php echo $id_g == $groupe['id_g'] ? 'selected' : '' ?>
                ><?php hecho($groupe['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type='submit' class='btn btn-primary'><i class='fas fa-search'></i>&nbsp;Rechercher</button>
        <?php if ($annuaire_edition) : ?>
            <a href="MailSec/import?id_e=<?php echo $id_e ?>" class='btn btn-primary ms-2'
            ><i class="fas fa-upload"></i>&nbsp;Importer</a>
        <?php endif; ?>

    </form>

    <?php $this->suivantPrecedent($offset, $limit, $nb_email, "MailSec/annuaire?id_e=$id_e&search=$search"); ?>

    <form action='MailSec/delete' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_e' value='<?php echo $id_e ?>'/>

        <table class="table table-striped">
            <tr>
                <th>Description</th>
                <th>Email</th>
                <th>Groupes</th>
            </tr>
            <?php foreach ($listUtilisateur as $utilisateur) : ?>
                <tr>
                    <td>
                        <?php if ($annuaire_edition) : ?>
                            <input type='checkbox' name='id_a[]' value='<?php hecho($utilisateur['id_a']) ?>'/>
                        <?php endif; ?>
                        <a href='MailSec/detail?id_a=<?php echo $utilisateur['id_a'] ?>&id_e=<?php echo $id_e ?>'
                        ><?php hecho($utilisateur['description']); ?></a>
                    </td>
                    <td><?php echo $utilisateur['email'] ?></td>
                    <td>
                        <?php foreach ($utilisateur['groupe'] as $i => $groupe) : ?>
                            <?php
                            $mailsecGroupUrl = \sprintf(
                                'MailSec/groupe?id_e=%s&id_g=%s',
                                $groupe['id_e'],
                                $groupe['id_g'],
                            );
                            ?>
                            <a href='<?php echo $mailsecGroupUrl; ?>'><?php hecho($groupe['nom']); ?></a>
                            <?php if ($i != count($utilisateur['groupe']) - 1) :?>
                            ,
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

        </table>

        <?php $this->suivantPrecedent($offset, $limit, $nb_email, "MailSec/annuaire?id_e=$id_e&search=$search"); ?>

        <?php if ($annuaire_edition) : ?>
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-trash"></i>&nbsp;Supprimer
            </button>
        <?php endif; ?>
    </form>

</div>

<?php if ($annuaire_edition) : ?>
    <div class="box">
        <h2>Ajouter un contact</h2>
        <form action='MailSec/addContact' method='post'>
            <?php $this->displayCSRFInput() ?>
            <input type='hidden' name='id_e' value='<?php echo $id_e ?>'/>

            <table class="table table-striped">

                <tr>
                    <th>Description</th>
                    <td>
                        <input class="form-control col-md-4" type='text' name='description'
                               value='<?php echo $this->getLastError()->getLastInput('description'); ?>'/>
                    </td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td>
                        <input class="form-control col-md-4" type='text' name='email'
                               value='<?php echo $this->getLastError()->getLastInput('email'); ?>'/>
                    </td>
                </tr>

            </table>
            <button type='submit' class='btn btn-primary'><i class='fas fa-plus-circle'></i>&nbsp;Ajouter</button>
        </form>
    </div>
<?php endif; ?>

<a class='btn btn-outline-primary' href='MailSec/export?id_e=<?php echo $id_e ?>'
><i class='fas fa-upload'></i>&nbsp;Exporter l'annuaire (CSV)</a>
