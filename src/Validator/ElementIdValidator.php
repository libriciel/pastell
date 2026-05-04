<?php

declare(strict_types=1);

namespace Pastell\Validator;

use UnrecoverableException;

final class ElementIdValidator
{
    public const ELEMENT_ID_MAX_LENGTH = 64;
    public const ELEMENT_ID_REGEXP = '^[a-z_][0-9a-z_]*$';

    /**
     * @throws UnrecoverableException
     */
    public function validate(string $elementId = ''): bool
    {
        if (preg_match('#^\d#', $elementId)) {
            throw new UnrecoverableException("L'identifiant de l'élément ne doit pas commencer par un chiffre");
        }

        if (!preg_match("#" . self::ELEMENT_ID_REGEXP . "#", $elementId)) {
            throw new UnrecoverableException(
                "L'identifiant de l'élément « " . get_hecho(
                    $elementId
                ) . " » ne respecte pas l'expression rationnelle : " . self::ELEMENT_ID_REGEXP
            );
        }
        if (\strlen($elementId) > self::ELEMENT_ID_MAX_LENGTH) {
            throw new UnrecoverableException(
                "L'identifiant de l'élément « " . get_hecho(
                    $elementId
                ) . ' » ne doit pas dépasser ' . self::ELEMENT_ID_MAX_LENGTH . ' caractères'
            );
        }
        return true;
    }
}
