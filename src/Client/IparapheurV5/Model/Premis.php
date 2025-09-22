<?php

declare(strict_types=1);

namespace Pastell\Client\IparapheurV5\Model;

use DOMDocument;
use DOMException;
use FileToSign;
use Libriciel\IparapheurV5\Client\Model\Action;
use RuntimeException;

final class Premis
{
    /** @var PremisObject[] */
    public array $object;
    /** @var Event[] */
    public array $event;
    /** @var Agent[] */
    public array $agent;
    public const string CADES_BASELINE_B = 'CAdES_BASELINE_B';

    public static function fromFileToSign(FileToSign $fileToSign, bool $multi_doc = false): self
    {
        $intellectual = new PremisObject();
        $intellectual->type = PremisObject::INTELLECTUAL_ENTITY;
        $intellectual->originalName = $fileToSign->dossierTitre;

        $significantProperties = [];

        $typeProp = new SignificantProperties();
        $typeProp->significantPropertiesType = SignificantProperties::TYPE;
        $typeProp->significantPropertiesValue = $fileToSign->type;
        $significantProperties[] = $typeProp;

        $subtypeProp = new SignificantProperties();
        $subtypeProp->significantPropertiesType = SignificantProperties::SUBTYPE;
        $subtypeProp->significantPropertiesValue = $fileToSign->sousType;
        $significantProperties[] = $subtypeProp;

        if (!empty($fileToSign->date_limite)) {
            $dueDateProp = new SignificantProperties();
            $dueDateProp->significantPropertiesType = SignificantProperties::DUE_DATE;
            $dueDateProp->significantPropertiesValue = $fileToSign->date_limite;
            $significantProperties[] = $dueDateProp;
        }

        foreach ($fileToSign->metadata as $key => $value) {
            $currentMetadata = new SignificantProperties();
            $currentMetadata->significantPropertiesType = $key;
            $currentMetadata->significantPropertiesValue = (string)$value;
            $significantProperties[] = $currentMetadata;
        }

        $intellectual->significantProperties = $significantProperties;

        $mainDoc = self::createFileObject($fileToSign->document->filename, true);
        if (!empty($fileToSign->signature_content)) {
            $signature = new Signature();
            $signature->signatureEncoding = 'UTF-8';
            $signature->signatureMethod = self::CADES_BASELINE_B;
            $signature->signatureValue = $fileToSign->signature_content;
            $signatureInformation = new SignatureInformation();
            $signatureInformation->signature = $signature;
            $mainDoc->signatureInformation = $signatureInformation;
        }

        $annexes = [];
        foreach ($fileToSign->annexes as $annexe) {
            $annexes[] = self::createFileObject($annexe->filename, $multi_doc);
        }

        $instance = new self();
        $instance->object = array_merge([$intellectual, $mainDoc], $annexes);
        $instance->event = [];
        $instance->agent = [];
        return $instance;
    }

    private static function createFileObject(string $filename, bool $isMain): PremisObject
    {
        $object = new PremisObject();
        $object->type = PremisObject::FILE;
        $object->originalName = $filename;

        $mainDocProp = new SignificantProperties();
        $mainDocProp->significantPropertiesType = SignificantProperties::MAIN_DOCUMENT;
        $mainDocProp->significantPropertiesValue = $isMain ? SignificantProperties::TRUE : SignificantProperties::FALSE;

        $object->significantProperties = [$mainDocProp];

        return $object;
    }

    public function getIntellectualEntity(): PremisObject
    {
        foreach ($this->object as $object) {
            if ($object->type === PremisObject::INTELLECTUAL_ENTITY) {
                return $object;
            }
        }
        throw new RuntimeException('Intellectual entity not found in Premis object.');
    }

    public function getRefusalMessage(): ?string
    {
        foreach ($this->event as $event) {
            if (
                isset($event->eventOutcomeInformation->eventOutcomeDetail->eventOutcomeDetailNote) &&
                strtoupper($event->eventType ?? '') === Action::REJECT
            ) {
                return $event->eventOutcomeInformation->eventOutcomeDetail->eventOutcomeDetailNote;
            }
        }
        return null;
    }

    public function getStartEvent(): Event
    {
        if (empty($this->event)) {
            throw new RuntimeException('No event found in the premis.');
        }

        foreach ($this->event as $event) {
            if (isset($event->eventType) && strtoupper($event->eventType) === Action::START) {
                return $event;
            }
        }
        throw new RuntimeException('Start event not found in the premis.');
    }

    /**
     * @return Event[]
     */
    public function getAllCurrentEvents(): array
    {
        if (empty($this->event)) {
            return [];
        }

        $tasks = [];
        foreach ($this->event as $event) {
            if (
                isset($event->eventIdentifier->eventIdentifierType, $event->eventIdentifier->eventIdentifierValue) &&
                $event->eventIdentifier->eventIdentifierType === Event::TASK_ID &&
                !empty($event->eventIdentifier->eventIdentifierValue)
            ) {
                $tasks[] = $event;
            }
        }

        return $tasks;
    }

    /**
     * @throws DOMException
     */
    public function generateDraftPremis(): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $premis = $dom->createElementNS('http://www.loc.gov/premis/v3', 'premis');
        $premis->setAttributeNS(
            'http://www.w3.org/2001/XMLSchema-instance',
            'xsi:schemaLocation',
            'http://www.loc.gov/premis/v3 http://www.loc.gov/standards/premis/v3/premis.xsd'
        );
        $premis->setAttribute('version', '3.0');
        $dom->appendChild($premis);

        foreach ($this->object as $premisObject) {
            $objectNode = $dom->createElement('object');
            $objectNode->setAttributeNS(
                'http://www.w3.org/2001/XMLSchema-instance',
                'xsi:type',
                $premisObject->type ?? 'file'
            );
            foreach ($premisObject->significantProperties as $property) {
                $prop = $dom->createElement('significantProperties');
                $prop->appendChild(
                    $dom->createElement('significantPropertiesType', $property->significantPropertiesType)
                );
                $prop->appendChild(
                    $dom->createElement('significantPropertiesValue', $property->significantPropertiesValue)
                );
                $objectNode->appendChild($prop);
            }
            if (!empty($premisObject->originalName)) {
                $objectNode->appendChild($dom->createElement('originalName', $premisObject->originalName));
            }

            if (!empty($premisObject->signatureInformation) && isset($premisObject->signatureInformation->signature)) {
                $signatureInfoNode = $dom->createElement('signatureInformation');
                $signatureNode = $dom->createElement('signature');

                $signature = $premisObject->signatureInformation->signature;

                $signatureNode->appendChild($dom->createElement('signatureEncoding', $signature->signatureEncoding));
                if (!empty($signature->signatureMethod)) {
                    $signatureNode->appendChild(
                        $dom->createElement('signatureMethod', $signature->signatureMethod)
                    );
                }
                $signatureNode->appendChild($dom->createElement('signatureValue', $signature->signatureValue));
                if (!empty($signature->signatureValidationRules)) {
                    $signatureNode->appendChild(
                        $dom->createElement('signatureValidationRules', $signature->signatureValidationRules)
                    );
                }

                $signatureInfoNode->appendChild($signatureNode);
                $objectNode->appendChild($signatureInfoNode);
            }
            $premis->appendChild($objectNode);
        }
        return $dom->saveXML();
    }

    public function getAgent(string $linkingAgentIdentifierValue): Agent
    {
        foreach ($this->agent as $agent) {
            if (
                isset(
                    $agent->agentIdentifier->agentIdentifierType,
                    $agent->agentIdentifier->agentIdentifierValue
                ) &&
                $agent->agentIdentifier->agentIdentifierType === AgentIdentifier::USER_ID &&
                $agent->agentIdentifier->agentIdentifierValue === $linkingAgentIdentifierValue
            ) {
                return $agent;
            }
        }
        throw new RuntimeException('Agent with identifier ' . $linkingAgentIdentifierValue . ' not found.');
    }
}
