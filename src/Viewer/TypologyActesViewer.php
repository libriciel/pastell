<?php

declare(strict_types=1);

namespace Pastell\Viewer;

final class TypologyActesViewer implements Viewer
{
    /**
     * @throws \JsonException
     */
    public function display(string $filename, string $filepath): void
    {
        if (!\file_exists($filepath)) {
            echo "La typologie n'a pas été choisie";
            return;
        }
        $filecontent = \file_get_contents($filepath);

        $json = \json_decode($filecontent, true, 512, \JSON_THROW_ON_ERROR);
        if (!$json) {
            echo "La typologie n'a pas pu être récupéré. Merci de la choisir à nouveau";
            return;
        }

        ?>
        <table style="border-style: solid; border-width: thin;" aria-label="Typologie des pièces">
            <tr>
                <th>Pièce</th>
                <th>Nom original du fichier</th>
                <th>Type de la pièce</th>
            </tr>
            <?php
            foreach ($json as $i => $line) : ?>
                <tr>
                    <td><?php
                        echo $i ? "Annexe numéro $i" : "Pièce principale" ?></td>
                    <td><?php
                        \hecho($line['filename'] ?? "erreur") ?></td>
                    <td><?php if (! \array_key_exists('typologie', $line)) :
                        \hecho('erreur');
                        elseif ($line['typologie'] === '') : ?>
                        <span style="color: var(--bs-orange, #d97706); font-weight: bold;">Typologie à définir</span>
                        <?php else :
                            \hecho($line['typologie']);
                        endif; ?></td>
                </tr>
                <?php
            endforeach; ?>
        </table>
        <?php
    }
}
