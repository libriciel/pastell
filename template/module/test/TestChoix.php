<?php

/**
 * @var Gabarit $this
 * @var string $id_d
 * @var int $id_e
 * @var int $page
 * @var string $field
 */
?>
<a class='btn btn-mini' href='<?php $this->url("Document/edition?id_d=$id_d&id_e=$id_e&page=$page"); ?>'>
    <i class="fas fa-arrow-left"></i>&nbsp;Retour à l'édition du document <em><?php echo $id_d?></em></a>



<div class="box">
    <h2>Choix</h2>

    <form action='Document/doExternalData' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_d' value='<?php echo $id_d?>' />
        <input type='hidden' name='id_e' value='<?php echo $id_e?>' />
        <input type='hidden' name='page' value='<?php echo $page?>' />
        <input type='hidden' name='field' value='<?php echo $field?>' />

        <input type='text' name='choix'  value=''/></td>

        <input type='submit' value='Choisir' class='btn' />

    </form>
</div>
