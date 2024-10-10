<?php

declare(strict_types=1);

namespace Pastell\Client\S2low;

final class S2lowClientAuth
{
    public string $username;
    public string $password;
    public string $user_certificat_pem;
    public string $user_key_pem;
    public string $user_certificat_password;
}