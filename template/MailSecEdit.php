<?php

/** @var Gabarit $this */
?>

<div class="box">
<h2>Édition d'un contact</h2>
<form action='MailSec/doEdit' method='post' >
    <?php $this->displayCSRFInput(); ?>
    <input type='hidden' name='id_e' value='<?php echo $info['id_e'] ?>' />
    <input type='hidden' name='id_a' value='<?php echo $info['id_a'] ?>' />
    <table class="table table-striped">
            <tr>
                <th>Description</th>
                <td><input type='text' name='description' value='<?php hecho($info['description']) ?>' class="form-control col-md-4"/></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><input class="form-control col-md-4" type='text' name='email' value='<?php echo hecho($info['email']) ?>'/></td>
            </tr>
            <tr>
                <th>Groupes</th>
                <td>
                    <ul>
                    <?php foreach ($groupe_list as $groupe) :?>
                        <li><input type='checkbox' name='id_g[]' <?php echo $groupe['id_a'] ? "checked='checked'" : ""?> value='<?php echo $groupe['id_g']?>'><?php hecho($groupe['nom'])?></li>
                    <?php endforeach;?>
                    </ul>
                </td>
            </tr>
    </table>
    <a class='btn btn-outline-primary' href='MailSec/detail?id_a=<?php echo $info['id_a'] ?>&id_e=<?php echo $info['id_e'] ?>'><i class="fas fa-circle-xmark"></i>&nbsp;Annuler</a>

    <button type="submit" class="btn btn-primary">
        <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
    </button>
</form>
</div>
