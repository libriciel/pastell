<?php

/** @var Gabarit $this */
?>
<a class='btn btn-link' href='<?php echo "MailSec/annuaire?id_e={$id_e}" ?>'><i class="fas fa-arrow-left"></i>&nbsp;<?php hecho($infoEntite['denomination']) ?></a>

<div class="box">
    <form action="MailSec/doImport" method='post' enctype='multipart/form-data'>
        <?php $this->displayCSRFInput(); ?>
        <input type='hidden' name='id_e' value='<?php hecho($id_e)?>' />

        <table class="table">
        <tr>
            <th class='w200'>Fichier CSV</th>
            <td><input type='file' name='csv' class="btn btn-outline-primary col-md-4"/></td>
        </tr>
        </table>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-upload"></i>&nbsp;Importer
        </button>
    </form>
</div>

<div class="alert alert-info">
    <p><strong>Format du fichier</strong></p>
    <p>Le fichier CSV doit contenir une adresse email par ligne.</p>
    <p>Les lignes sont formatées de la manière suivante :
    "Email","Description","Groupe ..."</p>
    <p>Note: si le fichier est trop gros (&gt;  <?php echo ini_get("upload_max_filesize") ?>)
    vous pouvez le compresser avec gzip.
    </p>
    <p>Les emails déjà existants verront leurs propriétés (description, groupe(s)) remplacées </p>
</div>
