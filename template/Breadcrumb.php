<?php

/**
 * @var bool $display_entite_racine
 * @var string $navigation_url
 * @var array $navigation
 */
?>

<ul class="breadcrumb">

    <?php if ($display_entite_racine) : ?>
        <li>
            <strong>Entité Racine</strong>
        </li>
        <li>
            <span class="divider">/</span>
        </li>
    <?php endif; ?>

    <?php if (isset($navigation)) : ?>
        <?php
        $parentEntityId = 0;
        foreach ($navigation as $nav) :
            $formId = 'bc_form_' . $nav['id_e'];
            $idSelect = 'select2_id_e_bc_' . $nav['id_e'];
            $idSelectSubmit = $idSelect . '_submit';
            ?>

            <?php if ($nav['is_last'] && $nav['is_root']) : ?>
            <li>
                <strong><?php hecho($nav['name']) ?></strong>
            </li>
            <?php endif; ?>

            <?php if (!$nav['is_root']) : ?>
            <li>
                <form action='<?php echo $navigation_url ?>' method='get' id="<?php hecho($formId); ?>">

                    <?php if (!empty($type)) : ?>
                    <input type='hidden' name='type' value='<?php hecho($type); ?>'/>
                    <?php endif; ?>
                    <select name='id_e' class='select2_breadcrumb' id='<?php hecho($idSelect); ?>'>
                        <?php foreach ($nav['same_level_entities'] as $fille) : ?>
                            <option
                                    value='<?php echo $fille['id_e'] ?>'
                                <?php echo $nav['id_e'] == $fille['id_e'] ? 'selected' : '' ?>
                            >
                                <?php hecho($fille['denomination']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input style='display:none' type='submit' value='go' id='<?php hecho($idSelectSubmit); ?>'/>
                </form>

            </li>
                <?php if ($nav['has_children']) : ?>
            <li>
                <span class="divider">/</span>
            </li>
                <?php endif; ?>
            <script>
                $(document).ready(function () {
                    $("#<?php hecho($idSelect); ?>").change(function () {
                        $(this).parents("form").submit();
                    });

                    $('.select2_breadcrumb').on("select2:unselecting", function (e) {
                        e.preventDefault();
                        let currentEntityId = e.params.args.data.id;
                        if (currentEntityId == <?php hecho($nav['id_e']); ?>) {
                            let parentEntityFormId = 'bc_form_' + '<?php hecho($parentEntityId); ?>';
                            console.log(parentEntityFormId);
                            if (parentEntityFormId in document.forms) {
                                document.forms[parentEntityFormId].submit();
                            } else {
                                $(this).parents('form').submit();
                            }
                        }
                    });
                });
            </script>
            <?php endif; ?>


            <?php if ($nav['is_last'] && !empty($nav['children'])) : ?>
            <li>
                <span class="divider">/</span>
            </li>
            <li>
                <form action='<?php hecho($navigation_url); ?>' method='get' id="bc_form">
                    <?php if (!empty($type)) : ?>
                    <input type='hidden' name='type' value='<?php hecho($type); ?>'/>
                    <?php endif; ?>
                    <select name='id_e' class='select2_breadcrumb' id='select2_id_e_bc'>
                        <option></option>
                        <?php foreach ($nav['children'] as $fille) : ?>
                            <option value='<?php echo $fille['id_e'] ?>'><?php hecho($fille['denomination']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input style='display:none' type='submit' value='go' id='select2_id_e_bc_submit'/>
                </form>
            </li>

            <script>
                $(document).ready(function () {
                    $("#select2_id_e_bc").change(function () {
                        $(this).parents("form").submit();
                    });
                });
            </script>
            <?php endif; ?>
            <?php
            $parentEntityId = $nav['id_e'];
        endforeach;
        ?>
    <?php endif; ?>

</ul>
