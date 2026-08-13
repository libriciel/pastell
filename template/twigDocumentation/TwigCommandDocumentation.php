<?php

use Pastell\Service\SimpleTwigRendererExemple;

$simpleTwigRendererExemple = new SimpleTwigRendererExemple();

?>
<table class="table table-striped" aria-label="Exemple de commande twig">
    <tr>
        <th id="twig_type" class="w300">Type de transformation</th>
        <th id="twig_exemple">Exemple de transformation</th>
        <th id="twig_explanation">Explication</th>
    </tr>
    <?php
    foreach ($simpleTwigRendererExemple->getExemple() as $type => $exemple) : ?>
        <tr>
            <td><?php
                hecho($type) ?></td>
            <td><?php
                hecho($exemple[0]) ?></td>
            <td><?php
                hecho($exemple[1]) ?></td>
        </tr>
        <?php
    endforeach; ?>

    <tr>
        <td>Expression xpath</td>
        <td> {{ xpath('pes_aller','//EnTetePES/CodBud/@V') }}</td>
        <td>Extrait l'expression xpath (valeur du code budget) à partir du fichier XML identifié par l'élément
            pes_aller
        </td>
    </tr>
    <tr>
        <td>Expression xpath retournant une liste</td>
        <td> {{ xpath_array('pes_aller','//EnTetePES/CodBud/@V') | join(', ') }}</td>
        <td>Identique à l'expression précédente, mais tous les résultats sont retournés et peuvent être concaténés avec
            un filtre join
        </td>
    </tr>
    <tr>
        <td>Expression jsonpath</td>
        <td> {{ jsonpath('parapheur_metadata','$.metadata1') }}</td>
        <td>Extrait l'expression jsonpath (valeur de metadata1) à partir du fichier JSON identifié par l'élément
            parapheur_metadata
        </td>
    </tr>
    <tr>
        <td>Expression JSONPath retournant une liste</td>
        <td> {{ jsonpath_array('parapheur_metadata','$.elements[*].valeur') | join(', ') }}</td>
        <td>Extrait une liste de valeurs (éléments) à partir du fichier JSON identifié par l'élément parapheur_metadata,
            en utilisant l'expression JSONPath appropriée. Les résultats sont retournés sous forme de liste et peuvent
            être concaténés avec un filtre join.
        </td>
    </tr>
    <tr>
        <td>Expression csv</td>
        <td> {{ csvpath('data_csv',1,12) }}</td>
        <td>Extrait le contenu de la seconde colonne de la treizième ligne du fichier CSV identifié par l'élément
            data_csv (les index commencent à 0). Le contenu du fichier CSV doit être encodé en UTF-8
        </td>
    </tr>
    <tr>
        <td>Expression csv (avec un autre séparateur de champs)</td>
        <td> {{ csvpath('data_csv',1,12,";",'"',"\\") }}</td>
        <td>Idem que l'expression précédente, mais en spécifiant <strong>;</strong> comme caractère séparateur de
            colonne (courant avec un tableur en français), " comme clôture de champs et \ comme caractère d'échappement
            (par défaut on utilise , " et \). <br/>
            Voir <a href="https://fr.wikipedia.org/wiki/Comma-separated_values"
            >https://fr.wikipedia.org/wiki/Comma-separated_values</a>
        </td>
    </tr>
</table>

<p>Aide externe : </p>
<ul>
    <li>
        <a href="https://twig.symfony.com/doc/3.x/" target="_blank">Documentation Twig</a>
    </li>
    <li>
        <a href="http://jsonpath.com/" target="_blank">Tester les expressions JSONPath</a>
    </li>
    <li>
        <a href="https://xpather.com/" target="_blank">Tester les expressions XPath</a>
    </li>
</ul>
