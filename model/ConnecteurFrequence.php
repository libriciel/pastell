<?php

use Pastell\Service\Entite\RootEntity;

/**
 * @deprecated 4.1.26 Use ConnecteurFrequenceData and ConnecteurFrequenceService instead
 */
class ConnecteurFrequence
{
    public const TYPE_GLOBAL = 'global';
    public const TYPE_ENTITE = 'entite';

    public const TYPE_ACTION_DOCUMENT = 'document';
    public const TYPE_ACTION_CONNECTEUR = 'connecteur';

    public $id_cf;

    public $type_connecteur;
    public $famille_connecteur;
    public $id_connecteur;
    public $id_ce;

    public $action_type;
    public $type_document;
    public $action;
    public $expression;
    public $id_verrou;

    private $libelle;
    private $denomination;

    public function getArray()
    {
        return get_object_vars($this);
    }

    public function getArrayForSQL()
    {
        $result = $this->getArray();
        unset($result['id_cf']);
        unset($result['libelle']);
        unset($result['denomination']);
        return $result;
    }

    public function __construct(array $input = [])
    {
        foreach ($this->getArray() as $key => $value) {
            if (isset($input[$key])) {
                $this->$key = $input[$key] ?: '';
            } else {
                $this->$key = '';
            }
        }
        if (isset($input['id_cf'])) {
            $input['id_cf'] = intval($input['id_cf']);
        }
    }

    public function getAttributeName()
    {
        $result = $this->getArrayForSQL();
        return array_keys($result);
    }

    public function getConnecteurSelector()
    {
        if ($this->type_connecteur == '') {
            return 'Tous les connecteurs';
        }
        if ($this->type_connecteur == self::TYPE_GLOBAL) {
            $result = "(Global)";
        } else {
            $result = "(Entité)";
        }

        if ($this->famille_connecteur == '') {
            return $result . " Tous les connecteurs";
        }

        $result .= " " . $this->famille_connecteur;

        if ($this->id_connecteur == '') {
            return $result;
        }

        return $result . ":" . $this->id_connecteur;
    }

    public function getActionSelector()
    {
        if ($this->action_type == '') {
            return "Toutes les actions";
        }
        $result = "";
        if ($this->action_type == self::TYPE_ACTION_CONNECTEUR) {
            $result .= "(Connecteur) ";
        } else {
            $result .= "(Document) ";
            if ($this->type_document == '') {
                return $result . "Tous les types de dossiers";
            }
            $result .= "{$this->type_document}: ";
        }
        if ($this->action == '') {
            $result .= "toutes les actions";
        } else {
            $result .= $this->action;
        }
        return $result;
    }

    public function getNextTry($nb_try, $relative_date = '')
    {
        if (! $this->expression) {
            return '';
        }

        $frequence_list = $this->getExpressionArray();
        $total_try = 0;
        $i = 0;
        while ($total_try <= $nb_try && isset($frequence_list[$i]) && $frequence_list[$i]['nb_try']) {
            $total_try += $frequence_list[$i]['nb_try'];
            if ($total_try <= $nb_try) {
                $i++;
            }
        }
        if (empty($frequence_list[$i])) {
            throw new Exception("Trop d'essai sur le connecteur");
        }

        if ($frequence_list[$i]['cron']) {
            $cron = Cron\CronExpression::factory($frequence_list[$i]['cron']);
            $time = $relative_date ?: "now";
            return $cron->getNextRunDate($time)->format("Y-m-d H:i:s");
        }

        $nextTry = $frequence_list[$i]['frequence'];
        $nextTryUnit = $frequence_list[$i]['unit'];
        return date('Y-m-d H:i:s', strtotime(sprintf('%s +%d %s', $relative_date, $nextTry, $nextTryUnit)));
    }

    private function getExpressionArray(): array
    {
        $all_line = explode("\n", $this->expression);
        $frequence_list = [];
        foreach ($all_line as $line) {
            $unit = 'minutes';
            preg_match('#([^X]*)\s*X?\s*(\d*)#', $line, $matches);
            $expression = trim($matches[1]);
            $nb_try = intval($matches[2]);
            $frequence = '';
            $cron = '';
            if (preg_match("#\(([^\)]*)\)#", $expression, $matches)) {
                $cron = $matches[1];
            } else {
                if (\preg_match('/\d*\s*s/', $expression)) {
                    $unit = 'seconds';
                }
                $frequence = (int)$expression;
            }
            $frequence_list[] = ['frequence' => $frequence, 'unit' => $unit, 'cron' => $cron, 'nb_try' => $nb_try];
        }
        return $frequence_list;
    }

    public function getExpressionAsString(): string
    {
        $expression_list = $this->getExpressionArray();
        $result = '';
        $nb_expression = 0;
        foreach ($expression_list as $nb_expression => $expression) {
            if ($expression['frequence']) {
                $unit = $expression['unit'];
                if ($unit === 'seconds') {
                    $unit = 'secondes';
                }
                if ($expression['frequence'] == 1) {
                    $result .= "Toutes les $unit";
                } else {
                    $result .= "Toutes les {$expression['frequence']} $unit";
                }
            } elseif ($expression['cron']) {
                $result .= "A ({$expression['cron']})";
            }
            if ($expression['nb_try']) {
                $result .= " ({$expression['nb_try']} fois)";
            }
            $result .= "\n";
        }
        if ($expression_list[$nb_expression]['nb_try']) {
            $result .= 'Suspendre le travail';
        }

        return $result;
    }

    public function getInstanceConnecteurAsString()
    {
        if (! $this->id_ce) {
            return "";
        }
        $denomination = $this->denomination ?: RootEntity::DENOMINATION;
        return "{$this->libelle} [{$denomination}]";
    }
}
