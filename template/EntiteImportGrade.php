<?php

/** @var Gabarit $this */
?>
<div class="box">
    <form action="Entite/importGrade" method='post' enctype='multipart/form-data'>
        <?php $this->displayCSRFInput() ?>
        <table class='table'>

        <tr>
            <th class='w140'>Fichier CSV</th>
            <td><input type='file' name='csv_grade' class="btn btn-outline-primary col-md-4"/></td>
        </tr>
        </table>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-upload"></i>&nbsp;Importer
        </button>   </form>
</div>

<div class="alert alert-info">
    <p><strong>Format du fichier</strong></p>
    <p>Le fichier CSV doit contenir un grade par ligne.</p>
    <p>Les lignes sont formatées de la manière suivante :
    Filière (C);Filière (L);Cadre d'emplois (C);Cadre d'emplois (L);Grade (C);Grade (L)
    </p>
    <p>Note: si le fichier est trop gros (&gt;  <?php echo ini_get("upload_max_filesize") ?>) 
    vous pouvez le compresser avec gzip.
    </p>
</div>
