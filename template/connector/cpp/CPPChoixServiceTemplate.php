<?php

/**
 * @var Gabarit $this
 * @var int $id_ce
 * @var string $field
 * @var array $service_list
 * @var int $offset
 * @var int $limit
 * @var int $count
 */
?>

<a class='btn btn-link' href='Connecteur/editionModif?id_ce=<?php echo $id_ce?>'><i class="fas fa-arrow-left"></i>&nbsp;Retour à la définition du connecteur</a>

<?php $this->suivantPrecedent($offset, $limit, $count, "/Connecteur/externalData?id_ce=$id_ce&field=$field"); ?>
<div class="box">
<div class="alert alert-info">Cliquez sur le code du service pour le sélectionner</div>

<table class="table table-striped">
    <tr>
        <th>Code du service</th>
        <th>Libellé du service</th>
        <th>Date début du service</th>
        <th>Est actif</th>
    </tr>
    <?php foreach ($service_list['listeServices'] as $service_info) : ?>
        <tr>
            <td>
                <form action="Connecteur/doExternalData" method="post">
                    <?php $this->displayCSRFInput(); ?>
                    <input type='hidden' name='id_ce' value='<?php echo $id_ce; ?>'/>
                    <input type='hidden' name='idService' value='<?php hecho($service_info['idService']); ?>'/>
                    <input type='hidden' name='field' value='<?php echo $field; ?>'/>
                    <button type='submit' class="btn btn-outline-primary"><?php hecho($service_info['codeService']); ?></button>
                </form>
            </td>
            <td><?php hecho($service_info['libelleService']); ?></td>
            <td><?php echo $this->getFancyDate()->getDateFr($service_info['dateDbtService']); ?></td>
            <td><?php hecho($service_info['estActif'] ? "OUI" : "NON"); ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<form action="Connecteur/doExternalData" method="post" id='form_sign'>
    <?php $this->displayCSRFInput(); ?>
    <input type='hidden' name='id_ce' value='<?php echo $id_ce; ?>'/>
    <input type='hidden' name='field' value='<?php echo $field; ?>'/>
    <input type='hidden' name='idService' value=''/>
    <button type="submit" class="btn btn-danger" name="submit" value="Supprimer la sélection du service">
        <i class="fas fa-trash"></i>&nbsp;Supprimer la sélection du service
    </button>
</form>
</div>
<?php $this->suivantPrecedent($offset, $limit, $count, "/Connecteur/externalData?id_ce=$id_ce&field=$field"); ?>
