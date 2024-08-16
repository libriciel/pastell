<?php

/**
 * @var Gabarit $this
 * @var int $id_ce
 * @var string $field
 * @var array $transformation_data
 * @var string $flux
 * @var Field[] $fieldsList
 */

use Pastell\Validator\ElementIdValidator;

?>
<div id='box_signature' class='box'>

    <form action='Connecteur/doExternalData' method='post' id='form_sign'>
        <?php $this->displayCSRFInput(); ?>
        <input type='hidden' name='id_ce' value='<?php echo $id_ce ?>'/>
        <input type='hidden' name='field' value='<?php echo $field ?>'/>
        <table class="table table-striped">
            <?php
            $i = 0;
            foreach ($transformation_data as $element_id => $twig_expression) :
                $i++;
                ?>
                <tr>
                    <th class="w500">
                        <label for="id_element_<?php echo $i; ?>">Identifiant de l'élément</label>
                        <p class="form_commentaire">64 caractères maximum (chiffres, lettres en minuscules ou _).</p>
                        <input
                                class="form-control col-md-12"
                                type="text"
                                maxlength="<?= ElementIdValidator::ELEMENT_ID_MAX_LENGTH ?>"
                                pattern="<?= ElementIdValidator::ELEMENT_ID_REGEXP ?>"
                                name="id_element[]"
                                id="id_element_<?php echo $i; ?>"
                                value="<?php hecho($element_id) ?>"
                        />
                    </th>
                    <td>
                        <label for="defintion_<?php echo $i; ?>">Transformation</label>
                        <textarea
                                name='definition[]'
                                id='defintion_<?php echo $i; ?>'
                                cols="80"
                                rows="<?php echo max(5, substr_count($twig_expression, "\n") + 1); ?>"
                                class="form-control col-md-12"><?php hecho($twig_expression); ?></textarea>
                    </td>
                </tr>
            <?php endforeach ?>
        </table>

        <a class='btn btn-secondary'
           href='Connecteur/editionModif?id_ce=<?php echo $id_ce ?>'>
            <i class="fa fa-times-circle"></i>&nbsp;Annuler
        </a>

        <button type="submit" class="btn btn-primary" id="add_button" name="add_button" value="add">
            <i class="fa fa-check"></i>&nbsp;Ajouter un élement
        </button>

        <button type="submit" class="btn btn-primary" id="submit_button" name="submit_button">
            <i class="fa fa-check"></i>&nbsp;Enregistrer
        </button>
    </form>

</div>

<?php $this->render('/connector/transformation/TransformationTwigExplanation'); ?>
<?php $this->render('/twigDocumentation/TwigElementFlux'); ?>
<?php $this->render('/twigDocumentation/TwigPastellMetadataDescription'); ?>
