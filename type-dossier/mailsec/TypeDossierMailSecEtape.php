<?php

use Pastell\Configuration\ModuleElement;

class TypeDossierMailSecEtape implements TypeDossierEtapeSetSpecificInformation
{
    public function setSpecificInformation(
        TypeDossierEtapeProperties $typeDossierEtape,
        array $result,
        StringMapper $stringMapper
    ): array {
        $sendMailSecErrorAction = $stringMapper->get('send-mailsec-error');
        $result[DocumentType::ACTION][Action::MODIFICATION][Action::ACTION_RULE]
            [Action::ACTION_RULE_LAST_ACTION][] = $sendMailSecErrorAction;
        $result[DocumentType::ACTION]['supression'][Action::ACTION_RULE]
            [Action::ACTION_RULE_LAST_ACTION][] = $sendMailSecErrorAction;

        if ($typeDossierEtape->specific_type_info['add_displayed_fields']) {
            foreach (['sent_mail_number', 'sent_mail_read'] as $fieldId) {
                $result[ModuleElement::CHAMPS_AFFICHES->value][] = $stringMapper->get($fieldId);
            }
        }
        return $result;
    }
}
