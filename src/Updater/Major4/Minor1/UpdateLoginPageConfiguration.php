<?php

declare(strict_types=1);

namespace Pastell\Updater\Major4\Minor1;

use Pastell\Updater\Version;

final class UpdateLoginPageConfiguration implements Version
{
    public function __construct(private readonly string $loginPageConfigurationLocation)
    {
    }

    /**
     * @throws \JsonException
     */
    public function update(): void
    {
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
