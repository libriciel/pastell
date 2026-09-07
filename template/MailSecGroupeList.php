<?php

/**
 * @var Gabarit $this
 * @var int $id_e
 * @var array $infoEntite
 * @var array $listGroupe
 * @var AnnuaireGroupeSQL $annuaireGroupe
 * @var bool $annuaire_edition
 * @var array $groupe_herited
 */

?>

<div class="box">
    <?php if ($annuaire_edition) : ?>
        <a href="MailSec/groupeEdition?id_e=<?= $id_e?>"
           class='btn btn-primary'
        ><i class="fas fa-plus-circle"></i>&nbsp;Ajouter</a>
    <?php endif;?>
    <h2>Liste des groupes de contacts de <?php hecho($infoEntite['denomination']); ?></h2>

    <?php if ($annuaire_edition) : ?>
        <button type='submit' form='form-suppression-groupes' class='btn btn-danger' id='btn-suppression-groupes-top' disabled>
            <i class='fa fa-trash'></i>&nbsp;Supprimer la sélection
        </button>
    <?php endif;?>
    <form action='MailSec/groupeSuppression' method='post' id='form-suppression-groupes'>
        <?php $this->displayCSRFInput(); ?>
        <input type='hidden' name='id_e' value='<?php echo $id_e; ?>'/>

        <table class="table table-striped">
            <thead>
            <tr>
                <?php if ($annuaire_edition) : ?>
                    <th><input type='checkbox' id='select-all-groupes' title='Tout sélectionner'/></th>
                <?php endif; ?>
                <th>Nom</th>
                <th>Contact</th>
                <th>Partagé ?</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($listGroupe as $groupe) : ?>
                <tr>
                    <?php if ($annuaire_edition) : ?>
                        <td><input type='checkbox' name='id_g[]' value='<?php echo $groupe['id_g']; ?>' class='groupes-checkbox'/></td>
                    <?php endif; ?>
                    <td>
                        <a href='MailSec/groupeDetail?id_e=<?php echo $id_e; ?>&id_g=<?php echo $groupe['id_g']; ?>'
                        ><?php hecho($groupe['nom']); ?></a>
                    </td>
                    <td>
                        <?php if ($groupe['contactsInfo']['nb_contacts']) : ?>
                            <?php echo $groupe['contactsInfo']['contacts']; ?>
                            <?php if ($groupe['contactsInfo']['nb_contacts'] > 3) : ?>
                                <br/> et <a
                                    href='MailSec/groupeDetail?id_e=<?php echo $id_e; ?>&id_g=<?php echo $groupe['id_g']; ?>'
                                ><?php echo $groupe['contactsInfo']['nb_contacts'] - 3; ?> autres</a>
                            <?php endif; ?>
                        <?php else : ?>
                            Ce groupe est vide
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $groupe['partage'] ? 'OUI' : 'NON'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($annuaire_edition) : ?>
            <button type='submit' class='btn btn-danger' id='btn-suppression-groupes-bottom' disabled>
                <i class='fa fa-trash'></i>&nbsp;Supprimer la sélection
            </button>
        <?php endif; ?>

    </form>
</div>

<?php if ($annuaire_edition) : ?>
<script>
    document.getElementById('select-all-groupes').addEventListener('change', function () {
        document.querySelectorAll('.groupes-checkbox').forEach(cb => cb.checked = this.checked);
        updateDeleteButton();
    });
    document.querySelectorAll('.groupes-checkbox').forEach(cb => cb.addEventListener('change', updateDeleteButton));
    function updateDeleteButton() {
        const anyChecked = document.querySelectorAll('.groupes-checkbox:checked').length > 0;
        document.getElementById('btn-suppression-groupes-top').disabled = !anyChecked;
        document.getElementById('btn-suppression-groupes-bottom').disabled = !anyChecked;
    }
</script>
<?php endif; ?>

<?php if ($groupe_herited) : ?>
    <div class="box">
        <h2>Liste des groupes hérités</h2>

        <table class="table table-striped">
            <tr>
                <th style="width: 25%">Entité</th>
                <th style="width: 25%">Nom</th>
                <th style="width: 50%">Contact</th>
            </tr>

            <?php foreach ($groupe_herited as $groupe) : ?>
                <tr>
                    <td><?php hecho($groupe['denomination']); ?></td>
                    <td><?php hecho($groupe['nom']); ?></td>
                    <td
                        data-bs-toggle="collapse"
                        data-bs-target="#collapse-more-contacts-<?php
                        hecho(str_replace(' ', '-', $groupe['nom'])); ?>"
                        aria-expanded="false"
                        aria-controls="collapse-more-contacts-<?php
                        hecho(str_replace(' ', '-', $groupe['nom'])) ;?>"
                        onclick="hideMoreUsers(
                            '#collapse-more-contacts-<?php
                            hecho(str_replace(' ', '-', $groupe['nom'])); ?>',
                            '#more-contacts-info-<?php
                            hecho(str_replace(' ', '-', $groupe['nom'])); ?>')"
                    >
                        <?php if ($groupe['contactsInfo']['contacts']) : ?>
                            <?php echo $groupe['contactsInfo']['contacts']; ?>
                            <?php if ($groupe['contactsInfo']['nb_contacts'] > 3) : ?>
                                <br>
                                <div id="more-contacts-info-<?php
                                hecho(str_replace(' ', '-', $groupe['nom'])); ?>">
                                    et <span style="color:#53599a"
                                             onmouseover="this.style.color='#7076b8'; this.style.cursor='pointer';"
                                             onmouseout="this.style.color='#53599a';">
                                <?php echo $groupe['contactsInfo']['nb_contacts'] - 3; ?> autres
                                    </span>
                                </div>
                                <div id="collapse-more-contacts-<?php
                                hecho(str_replace(' ', '-', $groupe['nom'])); ?>"
                                     class="collapse">
                                    <?php echo $groupe['contactsInfo']['more_contacts']; ?>
                                    <p style="color:#53599a"
                                       onmouseover="this.style.color='#7076b8'; this.style.cursor='pointer';"
                                       onmouseout="this.style.color='#53599a';"
                                    >
                                        afficher moins
                                    </p>
                                </div>
                            <?php endif; ?>
                        <?php else : ?>
                            Ce groupe est vide
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

<?php endif; ?>

<script>
    function hideMoreUsers(collapseId, moreInfoId) {
        const collapseDiv = document.querySelector(collapseId);
        const moreUsersLink = document.querySelector(moreInfoId);
        if (collapseDiv.classList.contains('show')) {
            moreUsersLink.style.display = 'inline';
        } else {
            moreUsersLink.style.display = 'none';
        }
    }
</script>
