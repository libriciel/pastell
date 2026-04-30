<?php

/**
 * @var Gabarit $this
 * @var int $id_ce
 * @var string $field
 */
?>
<a class='btn btn-mini' href='<?php $this->url("Document/edition?id_ce=$id_ce"); ?>'>
    <i class="fas fa-arrow-left"></i>&nbsp;Retour à la définition du connecteur <em><?php echo $id_ce?></em></a>


<div class="box">
    <h2>Choix</h2>

    <form action='<?php $this->url("Connecteur/doExternalData") ?>' method='post'>
        <?php $this->displayCSRFInput();?>
        <input type='hidden' name='id_ce' value='<?php echo $id_ce?>' />
        <input type='hidden' name='field' value='<?php echo $field?>' />

        <input type='text' name='choix'  value=''/></td>

        <input type='submit' value='Choisir' class='btn' />

    </form>
</div>
