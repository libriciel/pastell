<?php

declare(strict_types=1);

namespace Pastell\Viewer;

use JsonException;

use function file_exists;
use function file_get_contents;
use function hecho;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class JsonViewer implements Viewer
{
    /**
     * @throws JsonException
     */
    public function display(string $filename, string $filepath): void
    {
        if (!file_exists($filepath)) {
            echo 'Le fichier n\'existe pas';
            return;
        }
        $filecontent = file_get_contents($filepath);

        $json = json_decode($filecontent, true, 512, JSON_THROW_ON_ERROR);
        if (!$json) {
            echo 'Le fichier est vide';
            return;
        }
        ?>
        <table style="border-style: solid; border-width: thin;" aria-label="Métadonnées">
            <tr>
                <th>Identifiant de l'élément</th>
                <th>Valeur</th>
            </tr>
            <?php
            foreach ($json as $elementId => $value) : ?>
                <tr>
                    <td>
                        <?php
                        hecho($elementId ?? 'ERREUR'); ?>
                    </td>
                    <td>
                        <?php
                        hecho($value); ?>
                    </td>
                </tr>
                <?php
            endforeach; ?>
        </table>
        <?php
    }
}
