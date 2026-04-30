<?php

/**
 * @var Gabarit $this
 * @var int $id_e
 * @var int $id_ce
 * @var string $field
 * @var array $properties
 */
?>
<div class="box">
<form action='<?php

$this->url("Connecteur/doExternalData") ?>' method='post'>
    <?php $this->displayCSRFInput();?>
    <input type="hidden" name="id_e" value="<?php echo $id_e ?>"/>
    <input type="hidden" name="id_ce" value="<?php echo $id_ce ?>"/>
    <input type="hidden" name="field" value="<?php echo $field ?>" />
    <input type="hidden" name="go" value="true"/>

    <table class="table table-striped">
    <?php foreach ($properties as $property => $value) : ?>
        <tr>
            <td><?php hecho($property) ?></td>
            <td><input name="<?php hecho($property) ?>" value="<?php hecho($value)?>"/></td>
        </tr>
    <?php endforeach; ?>
    </table>
    <a class='btn btn-secondary' href='Connecteur/edition?id_ce=<?php echo $id_ce?>'>
        <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
    </a>
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
    </button>
</form>
</div>