<?php

declare(strict_types=1);

namespace Pastell\Seda;

use Pastell\Helpers\SedaHelper;
use SimpleXMLElement;

final class VitamSedaHelper extends SedaHelper
{
    public function getSAEArchivalIdentifierFromAtrXpath(): array
    {
        return [
            self::SEDA_0_2_NS => ['/seda:ArchiveTransferAcceptance/seda:Archive/seda:ArchivalAgencyArchiveIdentifier'],
            self::SEDA_1_0_NS => ['/seda:ArchiveTransferReply/seda:Archive/seda:ArchivalAgencyArchiveIdentifier'],
            self::SEDA_2_1_NS => [
                '(/seda:ArchiveTransferReply/seda:DataObjectPackage/seda:DescriptiveMetadata' .
                '/seda:ArchiveUnit/seda:Content/seda:SystemId)[1]',
            ],
            self::SEDA_2_2_NS => [
                '(/seda:ArchiveTransferReply/seda:DataObjectPackage/seda:DescriptiveMetadata' .
                '/seda:ArchiveUnit/seda:Content/seda:SystemId)[1]',
            ],
        ];
    }

    /**
     * @throws \UnrecoverableException
     */
    public function getComment(SimpleXMLElement $xml): string
    {
        if ($xml->Operation) {
            $expression = ['//seda:Operation/seda:Event[last()]/seda:OutcomeDetailMessage'];
            $xpath = [
                self::SEDA_0_2_NS => $expression,
                self::SEDA_1_0_NS => $expression,
                self::SEDA_2_1_NS => $expression,
                self::SEDA_2_2_NS => $expression,
            ];

            return $this->getElement($xml, $xpath, 'Commentaire');
        }

        return '';
    }

    public function isSIPAccepted(SimpleXMLElement $xml): bool
    {
        return $this->getReplyCode($xml) === 'OK';
    }
}
