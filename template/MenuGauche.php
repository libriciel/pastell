<?php

declare(strict_types=1);

use Pastell\Service\Menu\MenuGaucheOption;

/**
 * @var Gabarit $this
 * @var string $menu_gauche_select
 * @var array<string, MenuGaucheOption[]> $menu
 */

?>

<div id="main_gauche" class="no-breadcrumb ls-on">
    <?php
    $i = 0;
    foreach ($menu as $title => $options) :
        $selected_section = false;
        foreach ($options as $option) {
            if ($option->id === $menu_gauche_select) {
                $selected_section = true;
                break;
            }
        }
        ?>
        <h3 data-bs-toggle="collapse" data-bs-target="#collapse-<?= $i ?>" aria-expanded=<?php
        hecho($selected_section ? 'true' : 'false'); ?> aria-controls="collapse-<?= $i ?>">
            <?php
            hecho($title); ?>
        </h3>
        <div class="menu collapse <?php hecho($selected_section ? 'show' : ''); ?>" id="collapse-<?= $i ?>">
            <ul>
                <?php foreach ($options as $option) : ?>
                    <li>
                        <a class="<?= $option->id === $menu_gauche_select ? 'actif' : '' ?>" href='<?php $this->url($option->lien); ?>'>
                            <?= $option->libelle ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        $i++;
    endforeach ?>
</div>
