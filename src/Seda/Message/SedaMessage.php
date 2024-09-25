<?php

declare(strict_types=1);

namespace Pastell\Seda\Message;

use Pastell\Seda\Message\Part\AppraisalRule;
use Pastell\Seda\Message\Part\ArchiveUnit;
use Pastell\Seda\Message\Part\File;
use Pastell\Seda\Message\Part\Keyword;
use Pastell\Seda\SedaVersion;

use function Pastell\areNullOrEmptyStrings;

class SedaMessage implements \JsonSerializable
{
    private SedaVersion $version;

    public ?string $title;
    public ?string $comment;
    public ?string $archivalAgreement;
    public ?string $archivalProfile;
    public ?string $language;
    public ?string $descriptionLanguage;
    public ?string $startDate;
    public ?string $endDate;
    public ?string $description;
    public ?string $descriptionLevel;
    public ?string $custodialHistory;
    public ?string $serviceLevel;
    public ?string $originatingAgencyIdentifier;
    public ?string $submissionAgencyIdentifier;
    public ?string $transferringAgencyArchiveIdentifier;
    public ?string $originatingAgencyArchiveIdentifier;
    public ?string $externalReferenceName;
    public ?string $externalReference;

    /** @var ?array{Identifier: string, Name: string} $archivalAgency */
    private ?array $archivalAgency = null;
    /** @var ?array{Identifier: string, Name: string} $transferringAgency */
    private ?array $transferringAgency = null;
    /** @var ?array{Identifier: string, Name: string} $originationAgency */
    private ?array $originationAgency = null;
    /** @var ?array{Rule: string, StartDate: string} $accessRule */
    private ?array $accessRule = null;

    private ?AppraisalRule $appraisalRule = null;
    /** @var ArchiveUnit[] $archiveUnits */
    private array $archiveUnits = [];
    /** @var File[] $files */
    private array $files = [];

    /** @var Keyword[] $keywords */
    private array $keywords = [];

    private array $custom = [];

    public function setVersion(SedaVersion $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function getVersion(): SedaVersion
    {
        return $this->version;
    }

    public function setArchivalAgency(?string $identifier, ?string $name): self
    {
        if (!areNullOrEmptyStrings($identifier, $name)) {
            $this->archivalAgency['Identifier'] = $identifier;
            $this->archivalAgency['Name'] = $name;
        }
        return $this;
    }

    public function setTransferringAgency(?string $identifier, ?string $name): self
    {
        if (!areNullOrEmptyStrings($identifier, $name)) {
            $this->transferringAgency['Identifier'] = $identifier;
            $this->transferringAgency['Name'] = $name;
        }
        return $this;
    }

    public function setOriginationAgency(?string $identifier, ?string $name): self
    {
        if (!areNullOrEmptyStrings($identifier, $name)) {
            $this->originationAgency['Identifier'] = $identifier;
            $this->originationAgency['Name'] = $name;
        }
        return $this;
    }

    public function setAccessRule(?string $rule, ?string $startDate): self
    {
        if (!areNullOrEmptyStrings($rule, $startDate)) {
            $this->accessRule['Rule'] = $rule;
            $this->accessRule['StartDate'] = $startDate;
        }
        return $this;
    }

    public function setAppraisalRule(?string $rule, ?string $finalAction, ?string $startDate): self
    {
        if (!areNullOrEmptyStrings($rule, $finalAction, $startDate)) {
            $this->appraisalRule = new AppraisalRule($rule, $finalAction, $startDate);
        }
        return $this;
    }

    public function addKeyword(string $content, ?string $reference, ?string $type): self
    {
        $keyword = new Keyword();
        $keyword->keywordContent = $content;
        $keyword->keywordReference = $reference;
        $keyword->keywordType = $type;
        $this->keywords[] = $keyword;
        return $this;
    }

    public function addCustom(string $id, ?string $value): self
    {
        $this->custom[$id] = $value;
        return $this;
    }

    public function addFile(File $file): self
    {
        $this->files[] = $file;
        return $this;
    }

    public function addArchiveUnit(ArchiveUnit $archiveUnit): self
    {
        $this->archiveUnits[] = $archiveUnit;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            'version' => $this->version->value,
            'Comment' => $this->comment,
            'Title' => $this->title,
            'ArchivalAgreement' => $this->archivalAgreement,
            'ArchivalProfile' => $this->archivalProfile,
            'ServiceLevel' => $this->serviceLevel,
            'Language' => $this->language,
            'Description' => $this->description,
            'DescriptionLanguage' => $this->descriptionLanguage,
            'DescriptionLevel' => $this->descriptionLevel,
            'StartDate' => $this->startDate,
            'EndDate' => $this->endDate,
            'AccessRule' => $this->accessRule,
            'AppraisalRule' => $this->appraisalRule,
            'Keywords' => $this->keywords,
            'Custom' => $this->custom,
            'ArchiveUnits' => $this->archiveUnits,
            'Files' => $this->files,
            'CustodialHistory' => $this->custodialHistory,
            'OriginatingAgency' => $this->originationAgency,
            'ArchivalAgency' => $this->archivalAgency,
            'TransferringAgency' => $this->transferringAgency,
            'OriginatingAgencyIdentifier' => $this->originatingAgencyIdentifier,
            'SubmissionAgencyIdentifier' => $this->submissionAgencyIdentifier,
            'TransferringAgencyArchiveIdentifier' => $this->transferringAgencyArchiveIdentifier,
            'OriginatingAgencyArchiveIdentifier' => $this->originatingAgencyArchiveIdentifier,
            'ExternalReferenceName' => $this->externalReferenceName,
            'ExternalReference' => $this->externalReference,
        ]);
    }
}
