<?php

declare(strict_types=1);

namespace Pastell\Security;

final class LibricielFeedbackReader
{
    private const LIBRICIEL_FEEDBACK_FILE_NAME = 'libriciel-feedback.json';
    private const LIBRICIEL_FEEDBACK_STATE = 'libriciel-feedback-state';
    private const CACHE_TTL = 3600;

    private readonly string $filePath;

    public function __construct(
        private readonly bool $displaySecurityBanner,
        private readonly string $libricielFeedbackDirectory,
        private readonly \MemoryCache $memoryCache,
    ) {
        $this->filePath = $this->libricielFeedbackDirectory . DIRECTORY_SEPARATOR . self::LIBRICIEL_FEEDBACK_FILE_NAME;
    }

    private function getFileContent(): string
    {
        $fileContent = $this->memoryCache->fetch(self::LIBRICIEL_FEEDBACK_FILE_NAME);
        if ($fileContent) {
            return $fileContent;
        }

        $fileContent = '';
        if (\file_exists($this->filePath)) {
            $fileContent = \file_get_contents($this->filePath);
            if ($fileContent === false) {
                $fileContent = '';
            }
        }
        $this->memoryCache->store(self::LIBRICIEL_FEEDBACK_FILE_NAME, $fileContent, self::CACHE_TTL);
        return $fileContent;
    }

    private function getVersionState(): string
    {
        $content = $this->getFileContent();
        $decodedFeedbackData = \json_decode($content, true);
        return $decodedFeedbackData['version_installe']['etat'] ?? '';
    }

    public function hasSecurityAlert(): bool
    {
        if (!$this->displaySecurityBanner) {
            return false;
        }
        $state = $this->memoryCache->fetch(self::LIBRICIEL_FEEDBACK_STATE);
        if ($state === false) {
            $state = $this->getVersionState();
            $this->memoryCache->store(self::LIBRICIEL_FEEDBACK_STATE, $state, self::CACHE_TTL);
        }
        return $state === 'critique';
    }
}
