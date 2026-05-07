<?php

/**
 * @var Gabarit $this
 * @var int $id_e
 * @var array $all_connecteur_dispo
 * @var int $global
 */

use Pastell\Configuration\ConnectorConfiguration;

?>


<div class="box">

<h2>Ajouter un connecteur</h2>
<form action='<?php $this->url("/Connecteur/doNew") ?>' method='post' >
    <?php $this->displayCSRFInput() ?>
<input type='hidden' name='id_e' value='<?php echo $id_e ?>' />
<input type='hidden' name='global' value='<?php echo $global ?>' />
<table class='table table-striped'>

<tr>
    <th class='w200'><label for="libelle">Libellé de l'instance</label></th>
<td><input type='text' name='libelle' value='' id="libelle" class="form-control col-md-2"/></td>
</tr>

<tr>
    <th><label for="id_connecteur">Connecteur</label></th>
<td><select name='id_connecteur' id="id_connecteur" class="input-xxlarge form-select col-md-2" >
        <?php foreach ($all_connecteur_dispo as $id_connecteur => $connecteur) : ?>
            <option value='<?php hecho($id_connecteur)?>'>
                <?php hecho($connecteur[ConnectorConfiguration::NOM])?> (<?php hecho($connecteur[ConnectorConfiguration::TYPE])?>)
            </option>
        <?php endforeach;?>
    </select></td>
</tr>

</table>
    <a class='btn btn-outline-primary' href='Entite/connecteur?global=<?php echo $global?>&id_e=<?php echo $id_e?>'>
        <i class="fas fa-circle-xmark"></i>&nbsp;Annuler</a>

    <button type="submit" class="btn btn-primary">
        <i class="fas fa-plus"></i>&nbsp; Créer
    </button>

</form>
</div>
<br/><br/>
