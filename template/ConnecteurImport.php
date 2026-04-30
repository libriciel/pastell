<?php

/**
 * @var Gabarit $this
 * @var array $connecteur_entite_info
 */
?>

<div class="box">
    <h2>
        Connecteur <?php hecho($connecteur_entite_info['type']) ?>
        - <?php hecho($connecteur_entite_info['id_connecteur'])?>
        : <?php hecho($connecteur_entite_info['libelle']) ?>
    </h2>

    <form action='<?php $this->url('Connecteur/doImport') ?>' method='post' enctype="multipart/form-data">
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_ce' value='<?php echo $connecteur_entite_info['id_ce'] ?>' />
        <table class='table table-striped'>

            <tr>
                <th class='w200'>Fichier à importer (*.json)</th>
                <td><input type='file' name='pser' class="btn btn-outline-primary col-md-4"/>
                </td>
            </tr>

            <tr>
                <th>
                    <label for='password'>Mot de passe (si existant)</label>
                </th>
                <td>
                    <div class="input-group">
                        <input
                            id="password"
                            type="password"
                            class="form-control col-md-4 ls-box-input"
                            name="password"
                            value=''
                        />
                        <span class="input-group-text">
                            <i class="fas fa-eye-slash" onclick="switchInputType('password',this)"></i>
                        </span>
                    </div>
                </td>
            </tr>

        </table>
        <a
            class='btn btn-outline-primary'
            href='Connecteur/edition?id_ce=<?php echo $connecteur_entite_info['id_ce']?>'
        >
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>


        <button type="submit" class="btn btn-primary">
            <i class="fas fa-upload"></i>&nbsp;Importer
        </button>
    </form>

</div>
