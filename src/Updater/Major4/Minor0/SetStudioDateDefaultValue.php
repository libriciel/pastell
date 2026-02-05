<?php

declare(strict_types=1);

namespace Pastell\Updater\Major4\Minor0;

use Exception;
use Pastell\Configuration\ElementType;
use Pastell\Updater\Version;
use PastellLogger;
use Recuperateur;
use TypeDossierFormulaireElementProperties;
use TypeDossierService;
use TypeDossierSQL;

final class SetStudioDateDefaultValue implements Version
{
    public function __construct(
        private readonly TypeDossierSQL $typeDossierSQL,
        private readonly TypeDossierService $typeDossierService,
        private readonly ?PastellLogger $logger = null,
    ) {
    }

    /**
     * @throws Exception
     */
    public function update(): void
    {
        $this->logger?->info('Start');

        $modules = $this->typeDossierSQL->getAll();
        foreach ($modules as $module) {
            $studioId = $module['id_t'];
            $dates = $this->typeDossierService->getFieldWithType($module['id_t'], ElementType::DATE->value);
            /** @var TypeDossierFormulaireElementProperties $date */
            foreach ($dates as $date) {
                if ($date->default_value === false) {
                    $date->default_value = true;
                    $field = (array)$date;
                    $field['orig_element_id'] = $date->element_id;
                    $this->typeDossierService->editionElement($studioId, new Recuperateur($field));
                }
            }
        }
    }
}
