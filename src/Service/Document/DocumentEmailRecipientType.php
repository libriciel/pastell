<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

enum DocumentEmailRecipientType: string
{
    case TO = 'to';
    case CC = 'cc';
    case BCC = 'bcc';

    public function label(): string
    {
        return match ($this) {
            self::TO => 'Destinataire',
            self::CC => 'Copie à',
            self::BCC => 'Copie caché à',
        };
    }
}
