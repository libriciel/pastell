<?php

/**
 * @var Gabarit $this
 * @var bool $cdg_feature
 */
?>



<div class="box">

<form action="Entite/doEdition" method='post'>
    <?php $this->displayCSRFInput(); ?>
<input type='hidden' name='id_e' value='<?php echo $id_e ?>' />

<?php if ($entite_mere) : ?>
<input type='hidden' name='entite_mere' value='<?php echo $entite_mere ?>' />
<?php else : ?>
<input type='hidden' name='entite_mere' value='<?php echo $infoEntite['entite_mere'] ?>' />

<?php endif;?>

<table class='table table-striped'>
    <?php if ($cdg_feature) : ?>
    <tr>

        <th class='w300'>Type d'entité</th>

    <td><select name='type' class="form-select col-md-4">
        <?php foreach ([EntiteSQL::TYPE_COLLECTIVITE, EntiteSQL::TYPE_CENTRE_DE_GESTION] as $type) :?>
        <option value='<?php echo $type?>'
             <?php echo $infoEntite['type'] == $type ? 'selected="selected"' : ''?>> 
            <?php echo EntiteSQL::getNom($type) ?> </option>   
        <?php endforeach;?>

    </select></td>
    </tr>
    <?php endif;?>
<tr>
    <th class="w300">
        <label for="denomination">Nom<span class='obl'>*</span></label>
        <p class='form_commentaire'>128 caractères maximum</p>
    </th>
    <td>
        <input class="form-control col-md-4" type="text" maxlength="128" name="denomination"
               id="denomination" value='<?php hecho($infoEntite['denomination']); ?>'/>
    </td>
</tr>
<tr>
    <th>
        <label for="siren">SIREN</label>
        <p class='form_commentaire'>9 chiffres ou champ vide</p>
    </th>
    <td>
        <input class="form-control col-md-4" type="text" maxlength="9"
               name="siren" id="siren" value='<?php echo $infoEntite['siren']?>'/>
    </td>
</tr>
    <?php if ($cdg_feature) : ?>
<tr>
    <th><label for="cdg">Centre de gestion</label></th>
    <td>
        <?php $this->render("CDGSelect"); ?>
    </td>
</tr>
    <?php endif; ?>

</table>

    <?php if ($id_e) : ?>
        <a class='btn btn-outline-primary' href='Entite/detail?id_e=<?php echo $id_e?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>
    <?php elseif ($entite_mere) : ?>
        <a class='btn btn-outline-primary' href='Entite/detail?id_e=<?php echo $infoMere['id_e']?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>
    <?php else : ?>
        <a class='btn btn-outline-primary' href='Entite/detail'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>
    <?php endif;?>


    <?php if (! $id_e) : ?>
        <input type="hidden" name="create" value="true"/>
    <?php endif;?>

    <button type="submit" class="btn btn-primary" id="entity-edit">
        <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
    </button>

</form>

</div>
