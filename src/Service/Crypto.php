<?php

namespace Pastell\Service;

use Exception;
use InvalidArgumentException;
use ParagonIE\ConstantTime\Hex;
use ParagonIE\Halite\Alerts\CannotPerformOperation;
use ParagonIE\Halite\Alerts\InvalidDigestLength;
use ParagonIE\Halite\Alerts\InvalidKey;
use ParagonIE\Halite\Alerts\InvalidMessage;
use ParagonIE\Halite\Alerts\InvalidSalt;
use ParagonIE\Halite\Alerts\InvalidSignature;
use ParagonIE\Halite\Alerts\InvalidType;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\Crypto as HaliteSymmetricCrypto;
use ParagonIE\HiddenString\HiddenString;
use UnrecoverableException;

class Crypto
{
    /** @var string */
    public const LIBSODIUM_MINIMUM_VERSION_EXPECTED = '1.0.18';

    /** @var int */
    public const PASSWORD_MINIMUM_LENGTH = 8;

    /**
     * @throws CannotPerformOperation
     * @throws InvalidDigestLength
     * @throws InvalidKey
     * @throws InvalidMessage
     * @throws InvalidSalt
     * @throws InvalidType
     * @throws Exception
     */
    public function encrypt(string $message, string $password): string
    {
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);

        $encryptionKey = KeyFactory::deriveEncryptionKey(new HiddenString($password), $salt);

        return json_encode([
            'salt' => Hex::encode($salt),
            'message' => HaliteSymmetricCrypto::encrypt(new HiddenString($message), $encryptionKey)
        ]);
    }

    /**
     * @throws CannotPerformOperation
     * @throws InvalidDigestLength
     * @throws InvalidKey
     * @throws InvalidSalt
     * @throws InvalidSignature
     * @throws InvalidType
     * @throws UnrecoverableException
     */
    public function decrypt(string $encryptedMessage, string $password): string
    {
        $pieces = json_decode($encryptedMessage, true);

        if (!isset($pieces['message'], $pieces['salt'])) {
            throw new InvalidArgumentException("Le message est incorrect.");
        }

        $encryptionKey = KeyFactory::deriveEncryptionKey(new HiddenString($password), Hex::decode($pieces['salt']));
        try {
            $clearMessage = HaliteSymmetricCrypto::decrypt($pieces['message'], $encryptionKey)->getString();
        } catch (InvalidMessage $e) {
            throw new UnrecoverableException("Le mot de passe est incorrect");
        }
        return $clearMessage;
    }
}
