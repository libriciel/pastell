<?php

/**
 * @var int $id_u
 * @var int $id_e
 * @var string $type
 * @var Gabarit $this
 * @var string $titreSelectAction
 * @var array $action_list
 * @var bool $has_daily_digest
 * @var string $cancel_url
 * @var string $source
 */
?>
<div class='box'>
    <h2><?php hecho($titreSelectAction); ?></h2>
    <form action='Utilisateur/doNotificationEdit' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_u' value='<?php hecho($id_u); ?>'/>
        <input type='hidden' name='id_e' value='<?php hecho($id_e); ?>'/>
        <input type='hidden' name='type' value='<?php hecho($type); ?>'/>
        <input type='hidden' name='source' value='<?php hecho($source); ?>'/>

        <table class="table table-striped">
            <tr>
                <th><input type="checkbox" name="select-all" id="select-all"/> Nom de l'action</th>
            </tr>
            <?php foreach ($action_list as $action) :?>
                <tr>
                    <td><input type='checkbox' name='<?php hecho($action['id'])?>' <?php echo $action['checked'] ? 'checked="checked"' : '' ?>/>
                        <?php hecho($action['action_name']) ?>
                    </td>

                </tr>
            <?php endforeach;?>
            <tr>
                <td>
                    <label for="type_envoi" class="label">Type d'envoi</label>
                    <select id="type_envoi" class="form-select col-md-4" name="has_daily_digest">
                        <option value="0" <?php echo $has_daily_digest ?: 'selected="selected"'?> >Envoi à chaque événement</option>
                        <option value="1" <?php echo $has_daily_digest ? 'selected="selected"' : ''?> >Résumé journalier</option>
                    </select>
                </td>
            </tr>
        </table>
        <a href='<?php echo $cancel_url ?>' class='btn btn-outline-primary'><i class="fa fa-times-circle"></i>&nbsp;Annuler</a>
        <button type='submit' class='btn btn-primary'><i class="fa fa-floppy-o"></i>&nbsp;Enregistrer</button>
    </form>
</div>

<div class='alert alert-warning'>
    <p>Toutes ces actions ne produisent pas forcément des notifications !</p>
    <p>La notification est envoyée lorsque le document entre dans l'état correspondant</p>
</div>

