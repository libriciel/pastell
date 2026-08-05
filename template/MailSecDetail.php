<?php

/** @var Gabarit $this */
?>

<div class="box">
<h2>Propriétés</h2>
<table  class="table table-striped">
    <tr>
        <th class="w300">Description</th>
        <td><?php hecho($info['description'])?></td>
    </tr>
    <tr>
        <th class="w300">E-mail</th>
        <td><?php hecho($info['email'])?></td>
    </tr>
    <tr>
        <th class="w300">Groupe(s)</th>
        <td>
            <ul>
            <?php foreach ($groupe_list as $groupe) : ?>
                <li><a href='MailSec/groupe?id_e=<?php echo $groupe['id_e']?>&id_g=<?php echo $groupe['id_g']?>'><?php hecho($groupe['nom'])?></a></li>
            <?php endforeach;?>
            </ul>
        </td>
    </tr>   
</table>

<?php if ($annuaire_edition) : ?>
<table>
<tr>
    <td>
    <a class='btn btn-outline-primary mr-2' href='MailSec/annuaire?id_e=<?php echo $info['id_e'] ?>'><i class="fas fa-circle-xmark"></i>&nbsp;Annuler</a>
    </td>
    <td>

    <form action='MailSec/delete' method='post' >
    <?php $this->displayCSRFInput(); ?>
    <input type='hidden' name='id_e' value='<?php echo $info['id_e'] ?>' />
    <input type='hidden' name='id_a' value='<?php echo $info['id_a'] ?>' />
    <button type="submit" class="btn btn-danger mr-2">
        <i class="fas fa-trash"></i>&nbsp;Supprimer
    </button>
</form>
</td>
<td>
<form action='MailSec/edit' method='get' >
    <input type='hidden' name='id_e' value='<?php echo $info['id_e'] ?>' />
    <input type='hidden' name='id_a' value='<?php echo $info['id_a'] ?>' />
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-pen"></i>&nbsp;Modifier
    </button></form>
</td>
</tr>
</table>
<?php endif; ?>

</div>
