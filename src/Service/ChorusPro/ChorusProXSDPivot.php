<?php

declare(strict_types=1);

namespace Pastell\Service\ChorusPro;

use DOMDocument;

final class ChorusProXSDPivot
{
    public function getSchemaPath(): string
    {
        return __DIR__ . '/xsd-pivot/CPPFacturePivot_V2_02.xsd';
    }

    /**
     * @throws ChorusProException
     */
    public function checkIsFormatPivot(string $filePath): void
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->load($filePath, LIBXML_PARSEHUGE);
        $err =  $dom->schemaValidate($this->getSchemaPath());
        if (!$err) {
            $last_error = libxml_get_errors();
            $msg = ' ';
            foreach ($last_error as $err) {
                $msg .= "[Erreur #{$err->code}] " . $err->message . "\n";
            }
            libxml_use_internal_errors($previous);
            throw new ChorusProException($msg);
        }
        libxml_use_internal_errors($previous);
    }
}
