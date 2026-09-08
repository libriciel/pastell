<?php

class ExtensionsGraphique
{
    private $extensions;
    private $workspace_path;

    public function __construct($workspacePath, Extensions $extensions)
    {
        $this->extensions = $extensions;
        $this->workspace_path = $workspacePath;
    }

    public function getGraphiquePath()
    {
        return $this->workspace_path . "/extensions_graphe.jpg";
    }

    public function creerGraphe()
    {
        // Lecture des manifest.yml, Ecriture de extensions-graphe.dot, Création de extensions-graphe.jpg
        // Utilisation de GraphViz (! apt-get install graphviz)
        $type = "jpg";
        $file = $this->workspace_path . "/extensions_graphe.dot";
        $file_jpg = $this->getGraphiquePath();

        $color = [
            "extension" => "lavender",
            "version_ko" => "lightblue2",
            "manque_extension" => "lightblue3",
            "connecteur_type" => "blue4",
            "connecteur" => "darkorchid4",
            "flux" => "deeppink4",
        ];

        if ($fp = @ fopen($file, "w")) {
            fputs($fp, "digraph G {\n");
            fputs($fp, "graph [rankdir=LR];\n");
            fputs($fp, "edge [color=lightskyblue,arrowsize=1]\n");
            fputs($fp, "node [color=" . $color["extension"] . ",fontsize = \"10\",shape=plaintext,style=\"rounded,filled\", width=0.3, height=0.3]\n");
            if ($extension_list = $this->extensions->getAll()) {
                foreach ($extension_list as $id_e => $extension) {
                    $extension_id = preg_replace("#[^a-zA-Z0-9._ ]#", "_", $extension['id']);
                    if (empty($extension['manifest'])) {
                        continue;
                    }
                    $label = $this->graphLabelNoeud($extension_id, $extension, $color);
                    fputs($fp, $extension_id . "[label=" . $label . "]\n");
                    foreach ($extension['manifest']['extension_needed'] as $extension_needed => $extension_needed_info) {
                        $extension_needed_id = preg_replace("#[^a-zA-Z0-9._ ]#", "_", $extension_needed);
                        fputs($fp, $extension_id . "->" . $extension_needed_id . "\n");
                        if (empty($extension_needed_info['extension_presente'])) {//KO Manque extension
                            fputs($fp, $extension_needed_id . "[label=\"" . $extension_needed . "\", color = " . $color["manque_extension"] . "]\n");
                        } elseif (! $extension_needed_info['extension_version_ok']) {//Version KO
                            fputs($fp, $extension_needed_id . "[label=\"" . $extension_needed . "\", color = " . $color["version_ko"] . "]\n");
                        }
                    }
                }
            }

            // legende
            fputs($fp, $this->graphLegende($color));

            fputs($fp, "}");
            fclose($fp);

            exec("dot -T$type -o$file_jpg $file");
        }
        return $file_jpg;
    }

    private function graphLabelNoeud($extension_id, $extension, $color)
    {

        $extension_nom = preg_replace("#[^a-zA-Z0-9._ ]#", "_", $extension['nom']);
        $label = '< <TABLE BORDER="0" CELLBORDER="0" CELLSPACING="0">';
        $label .= '<TR><TD COLSPAN="2">' . $extension_nom . ' (' . $extension_id . ')</TD></TR>';

        foreach ($extension['connecteur-type'] as $connecteur_type) {
            $connecteur_type = preg_replace("#[^a-zA-Z0-9._ ]#", "_", $connecteur_type);
            $label .= '<TR><TD ALIGN="right"><FONT COLOR="' . $color["connecteur_type"] . '">Connecteur-type</FONT></TD>';
            $label .= '<TD ALIGN="left"><FONT COLOR="' . $color["connecteur_type"] . '">' . $connecteur_type . '</FONT></TD></TR>';
        }

        foreach ($extension['connecteur'] as $connecteur) {
            $connecteur = preg_replace("#[^a-zA-Z0-9._ ]#", "_", $connecteur);
            $label .= '<TR><TD ALIGN="right"><FONT COLOR="' . $color["connecteur"] . '">Connecteur</FONT></TD>';
            $label .= '<TD ALIGN="left"><FONT COLOR="' . $color["connecteur"] . '">' . $connecteur . '</FONT></TD></TR>';
        }

        foreach ($extension['flux'] as $flux) {
            $flux = preg_replace("#[^a-zA-Z0-9._ ]#", "_", $flux);
            $label .= '<TR><TD ALIGN="right"><FONT COLOR="' . $color["flux"] . '">Flux</FONT></TD>';
            $label .= '<TD ALIGN="left"><FONT COLOR="' . $color["flux"] . '">' . $flux . '</FONT></TD></TR>';
        }

        $label .= '</TABLE>>';

        return $label;
    }

    private function graphLegende($color)
    {

        $label_noeud = '< <TABLE BORDER="0" CELLBORDER="0" CELLSPACING="0">';
        $label_noeud .= '<TR><TD COLSPAN="2">Extension</TD></TR>';
        $label_noeud .= '<TR><TD ALIGN="right"><FONT COLOR="' . $color["connecteur_type"] . '">Connecteur-type</FONT></TD></TR>';
        $label_noeud .= '<TR><TD ALIGN="right"><FONT COLOR="' . $color["connecteur"] . '">Connecteur</FONT></TD></TR>';
        $label_noeud .= '<TR><TD ALIGN="right"><FONT COLOR="' . $color["flux"] . '">Flux</FONT></TD></TR>';
        $label_noeud .= '</TABLE>>';

        $cluster = "subgraph cluster_legende {\n";
        $cluster .= "label = \"Légende\"\n";
        $cluster .= "style = \"rounded, filled\"\n";
        $cluster .= "color = lavender\n";
        $cluster .= "fontsize = 10\n";
        $cluster .= "fillcolor = gray100\n";
        $cluster .= "E1[label=" . $label_noeud . "]\n";
        $cluster .= "E2[label=" . $label_noeud . "]\n";
        $cluster .= "V[label=\"Extension attendue en version incorrecte\", color = " . $color["version_ko"] . "]\n";
        $cluster .= "M[label=\"Extension attendue manquante\", color = " . $color["manque_extension"] . "]\n";
        $cluster .= "E1->E2[label=\"dépend de\" ,fontsize = \"10\"]\n";
        $cluster .= "E1->V\n";
        $cluster .= "E1->M\n";
        $cluster .= "}\n";
        return $cluster;
    }
}
