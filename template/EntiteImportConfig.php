<?php

/**
 * @var Gabarit $this
 * @var int $id_e
 */

?>

<div class="box">

    <form action='Entite/doImportConfig' method='post' enctype="multipart/form-data">
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_e' value='<?php hecho($id_e)?>'>
        <table class='table table-striped' aria-label="Import de la configuration">
            <tr>
                <th class='w200'>Fichier à importer (*.json)</th>
                <td><input type='file' name='pser' class="btn btn-outline-primary col-md-4"/>
                </td>
            </tr>
            <tr>
                <th>
                    <label for='password'>
                        Mot de passe<span class='obl'>*</span>
                    </label>
                </th>
                <td>
                    <div class="input-group">
                        <input
                            id="password"
                            type="password"
                            class="form-control col-md-4 ls-box-input"
                            name="password"
                            value=''
                            minlength="8"
                            required
                        />
                        <span class="input-group-text">
                            <i class="fa fa-eye-slash" onclick="switchInputType('password',this)"></i>
                        </span>
                    </div>
                </td>
            </tr>
        </table>

        <button type='submit' class='btn btn-primary' value="go">
            <i class="fa fa-download"></i>&nbsp;Importer les élements
        </button>

    </form>
</div>

