<?php

/**
 * @var Gabarit $this
 * @var int $id_e
 * @var array $all_connecteur_dispo
 */

use Pastell\Configuration\ConnectorConfiguration;

?>


<div class="box">

<h2>Ajouter un connecteur</h2>
<form action='<?php $this->url("/Connecteur/doNew") ?>' method='post' >
    <?php $this->displayCSRFInput() ?>
<input type='hidden' name='id_e' value='<?php echo $id_e ?>' />
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
    <a class='btn btn-outline-primary' href='Entite/connecteur?id_e=<?php echo $id_e?>'><i class="fa fa-times-circle"></i>&nbsp;Annuler</a>

    <button type="submit" class="btn btn-primary">
        <i class="fa fa-plus"></i>&nbsp; Créer
    </button>

</form>
</div>
<br/><br/>
