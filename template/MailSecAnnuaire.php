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

<div class="box">
    <?php if ($annuaire_edition) : ?>
        <a href="MailSec/contactEdition?id_e=<?php echo $id_e?>" class='btn btn-primary'
        ><i class="fas fa-plus-circle"></i>&nbsp;Ajouter</a>
    <?php endif ?>
    <h2>Rechercher un contact</h2>
    <form action="MailSec/annuaire" method='get' class="table-end">
        <input type='hidden' name='id_e' value='<?= $id_e?>'/>
        <input type='hidden' name='page' value='1'/>
        <table class='table table-striped'>
            <tr>
                <td>Groupe</td>
                <td>
                    <select name='id_g' class="form-select col-md-2 me-2">
                        <option value=''>Tous les groupes</option>
                        <?php foreach ($groupe_list as $groupe) : ?>
                            <option value='<?= $groupe['id_g'] ?>'
                                    <?= $id_g == $groupe['id_g'] ? 'selected' : '' ?>
                            ><?php hecho($groupe['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td>Recherche</td>
                <td>
                    <input class="form-control col-md-5" type='text' name='search'
                           value='<?php hecho($search) ?>' placeholder="Rechercher par nom ou email"
                    />
                </td>
            </tr>
        </table>
        <a href="MailSec/annuaire?id_e=<?php hecho($id_e) ?>" class="btn btn-outline-primary">
            <i class="fas fa-undo"></i>&nbsp;Réinitialiser
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-search"></i>&nbsp;Rechercher
        </button>
    </form>

    <h2>Liste des contacts - résultats de la recherche</h2>
    <a class='btn btn-outline-primary' href='MailSec/contactExport?id_e=<?php echo $id_e ?>'
    ><i class='fas fa-download'></i>&nbsp;Exporter</a>
    <?php if ($annuaire_edition) : ?>
        <a href="MailSec/contactImport?id_e=<?php echo $id_e ?>" class='btn btn-primary'
        ><i class="fas fa-upload"></i>&nbsp;Importer</a>
        <button type='submit' form='form-suppression-lot' class='btn btn-danger' id='btn-suppression-lot-top' disabled>
            <i class='fa fa-trash'></i>&nbsp;Supprimer la sélection
        </button>
    <?php endif; ?>
    <?php $this->suivantPrecedent($offset, $limit, $nb_email, "MailSec/annuaire?id_e=$id_e&search=$search"); ?>

    <form action='MailSec/contactSuppression' method='post' id='form-suppression-lot'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_e' value='<?php echo $id_e ?>'/>

        <table class="table table-striped">
            <thead>
            <tr>
                <?php if ($annuaire_edition) : ?>
                    <th><input type='checkbox' id='select-all-annuaire' title='Tout sélectionner'/></th>
                <?php endif; ?>
                <th>Description</th>
                <th>Email</th>
                <th>Groupes</th>
            </tr>
            </thead>
            <?php foreach ($listUtilisateur as $utilisateur) : ?>
                <tr>
                    <?php if ($annuaire_edition) : ?>
                        <td>
                            <input type='checkbox' name='id_a[]' value='<?php hecho($utilisateur['id_a']) ?>'
                                   class='annuaire-checkbox'/>
                        </td>
                    <?php endif; ?>
                    <td>
                        <a href='MailSec/contactDetail?id_a=<?php echo $utilisateur['id_a'] ?>&id_e=<?php echo $id_e ?>'
                        ><?php hecho($utilisateur['description']); ?></a>
                    </td>
                    <td><?php echo $utilisateur['email'] ?></td>
                    <td>
                        <?php foreach ($utilisateur['groupe'] as $i => $groupe) : ?>
                            <?php
                            $mailsecGroupUrl = \sprintf(
                                'MailSec/groupeDetail?id_e=%s&id_g=%s',
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
        <a class='btn btn-outline-primary' href='MailSec/contactExport?id_e=<?php echo $id_e ?>'
        ><i class='fas fa-download'></i>&nbsp;Exporter</a>
        <?php if ($annuaire_edition) : ?>
            <a href="MailSec/contactImport?id_e=<?php echo $id_e ?>" class='btn btn-primary'
            ><i class="fas fa-upload"></i>&nbsp;Importer</a>
            <button type='submit' form='form-suppression-lot' class='btn btn-danger' id='btn-suppression-lot' disabled>
                <i class='fa fa-trash'></i>&nbsp;Supprimer la sélection
            </button>
        <?php endif; ?>
    </form>
    <?php if ($annuaire_edition) : ?>
    <script>
        document.getElementById('select-all-annuaire').addEventListener('change', function () {
            document.querySelectorAll('.annuaire-checkbox').forEach(cb => cb.checked = this.checked);
            updateDeleteButton();
        });
        document.querySelectorAll('.annuaire-checkbox').forEach(cb => cb.addEventListener('change', updateDeleteButton));
        function updateDeleteButton() {
            const anyChecked = document.querySelectorAll('.annuaire-checkbox:checked').length > 0;
            document.getElementById('btn-suppression-lot').disabled = !anyChecked;
            document.getElementById('btn-suppression-lot-top').disabled = !anyChecked;
        }
    </script>
    <?php endif; ?>
</div>