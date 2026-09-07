<?php

/**
 * @var Gabarit $this
 * @var array $info_contact
 * @var int $id_e
 * @var int $id_a
 * @var array $all_groups
 */

?>

<div class="box">
    <form action='MailSec/doContactEdition' method='post'>
        <?php
        $this->displayCSRFInput(); ?>
        <input type='hidden' name='id_e' value='<?= $id_e ?>'/>
        <input type='hidden' name='id_a' value='<?= $id_a ?>'/>
        <table class="table table-striped">
            <tr>
                <th>Description</th>
                <td><input type='text' name='description' value='<?php
                    hecho($info_contact['description']) ?>' class="form-control col-md-4"/></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><input class="form-control col-md-4" type='text' name='email' value='<?php
                    hecho($info_contact['email']) ?>'/></td>
            </tr>
            <tr>
                <th>Groupes</th>
                <td>
                    <ul>
                        <?php
                        foreach ($all_groups as $groupe) : ?>
                            <li>
                                <input type='checkbox' name='id_g_list[]'
                                        <?= in_array(
                                            (int)$groupe['id_g'],
                                            $info_contact['id_g_list'],
                                            true
                                        ) ? 'checked' : '' ?>
                                       value='<?= $groupe['id_g'] ?>'>
                                <a href="<?= "MailSec/groupeDetail?id_e={$id_e}&id_g={$groupe['id_g']}" ?> "><?php
                                    hecho("{$groupe['id_g']} - {$groupe['nom']}") ?></a>
                            </li>
                            <?php
                        endforeach; ?>
                    </ul>
                </td>
            </tr>
        </table>
        <a class='btn btn-outline-primary'
           href='<?= $id_a ? "MailSec/contactDetail?id_a=$id_a&id_e=$id_e" : "MailSec/annuaire?id_e=$id_e" ?>'><i
                    class="fas fa-circle-xmark"></i>&nbsp;Annuler</a>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
        </button>
    </form>
</div>
