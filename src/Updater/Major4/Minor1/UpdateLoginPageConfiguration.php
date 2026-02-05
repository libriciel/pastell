<?php

declare(strict_types=1);

namespace Pastell\Updater\Major4\Minor1;

use Pastell\Updater\Version;
use PastellLogger;

final class UpdateLoginPageConfiguration implements Version
{
    public function __construct(
        private readonly string $loginPageConfigurationLocation,
        private readonly ?PastellLogger $logger = null,
    ) {
    }

    /**
     * @throws \JsonException
     */
    public function update(): void
    {
        $this->logger?->info('Start');

        if (!\file_exists($this->loginPageConfigurationLocation)) {
            return;
        }
        $fileContent = \file_get_contents($this->loginPageConfigurationLocation);
        if ($fileContent === false) {
            return;
        }
        $asArray = \json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR);
        if (\array_key_exists('imgData', $asArray) && !\array_key_exists('imageData', $asArray)) {
            $asArray['imageData'] = $asArray['imgData'];
        }
        if (\array_key_exists('imgName', $asArray) && !\array_key_exists('imageName', $asArray)) {
            $asArray['imageName'] = $asArray['imgName'];
        }

        \file_put_contents($this->loginPageConfigurationLocation, \json_encode($asArray, \JSON_THROW_ON_ERROR));
    }
}
