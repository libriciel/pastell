<?php

namespace Pastell\Service;

class SimpleTwigRendererExemple
{
    public function getExemple(): array
    {
        return [
            'Constante' => [
                'Actes soumis au contrôle de légalité',
                '',
                [
                    [],
                    'Actes soumis au contrôle de légalité'
                ]
            ],
            "Contenu d'un élément du formulaire" => [
                '{{ acte_numero }}',
                '',
                [
                    ['acte_numero' => 42],
                    '42'
                ]
            ],
            'Mélange constante et élément du formulaire' => [
                'Actes numéro {{ actes_numero }} concernant {{ agent_prenom }} {{ agent_nom }}',
                '',
                [
                    ['actes_numero' => '007', 'agent_prenom' => 'James', 'agent_nom' => 'Bond'],
                    'Actes numéro 007 concernant James Bond'
                ]
            ],
            'Valeur associée à un champ de type select' => [
                "Nature de l'acte: {{ select_value('acte_nature') }} ({{ acte_nature }})",
                "Nature de l'acte: Actes individuels (3)",
                [
                    ['acte_nature' => 3],
                    "Nature de l'acte:  (3)"
                ]
            ],
            'Expression conditionnelle' => [
                '{% if acte_nature == 4 %}AR038{% else %}AR048{% endif %}',
                'Si acte_nature est égale à 4, sera remplacé par AR038, sinon AR048',
                [
                    ['acte_nature' => 3],
                    'AR048'
                ]
            ],
            'Expression conditionnelle complexe' => [
                <<<EOT
{% if (acte_nature in ['3','4'] and classification starts with '4\.')
   or (acte_nature == '3' and classification starts with '8\.2')
%}AR048{% else %}AR038{% endif %}
EOT,
                <<<EOT
Si acte_nature est égale à 3 ou 4 et que la classification commence par '4.',
ou bien si la nature est 3 et que la classification commence par 8.2, alors, sera remplacé par AR048, sinon AR038
EOT,
                [
                    ['acte_nature' => 3, 'classification' => '8.2'],
                    'AR048'
                ]
            ],
        ];
    }
}
