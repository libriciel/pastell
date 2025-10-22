<?php

/**
 * TODO: Move functions in Pastell namespace
 */

function get_hecho(?string $message = '', int $quote_style = ENT_QUOTES): string
{
    return htmlentities($message ?? '', $quote_style, 'utf-8');
}

function hecho(?string $message = '', int $quot_style = ENT_QUOTES): void
{
    echo get_hecho($message ?? '', $quot_style);
}

function getDateIso($value)
{
    if (!$value) {
        return '';
    }
    return preg_replace('#^(\d{2})/(\d{2})/(\d{4})$#', '$3-$2-$1', $value);
}

function date_iso_to_fr($date)
{
    if (!$date) {
        return '';
    }
    return date('d/m/Y', strtotime($date));
}

function time_iso_to_fr($datetime)
{
    return date('d/m/Y H:i:s', strtotime($datetime));
}

function date_fr_to_iso($date)
{
    return preg_replace('#^(\d{2})/(\d{2})/(\d{4})$#', '$3-$2-$1', $date);
}

function header_wrapper($str)
{
    /** @phpstan-ignore-next-line */
    if (TESTING_ENVIRONNEMENT) {
        echo "$str\n";
    } else {
        header($str);
    }
}

/**
 * @throws Exception
 */
function exit_wrapper(int $code = 0): never
{
    /** @phpstan-ignore-next-line */
    if (TESTING_ENVIRONNEMENT) {
        throw new Exception("Exit called with code $code");
    }
    /** @phpstan-ignore-next-line */
    exit($code);
}

/**
 * @deprecated 4.0.8
 */
function setcookie_wrapper(
    $name,
    $value = '',
    $expire = 0,
    $path = '',
    $domain = '',
    $secure = false,
    $httponly = false
) {
    /** @phpstan-ignore-next-line */
    if (TESTING_ENVIRONNEMENT) {
        $logger = ObjectInstancierFactory::getObjetInstancier()->getInstance(PastellLogger::class);
        $logger->info("Call setcookie($name,$value,$expire,$path,$domain,$secure,$httponly)");
    } else {
        setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }
}

function move_uploaded_file_wrapper($filename, $destination)
{
    /** @phpstan-ignore-next-line */
    if (TESTING_ENVIRONNEMENT) {
        return rename($filename, $destination);
    }
    /** @phpstan-ignore-next-line */
    return move_uploaded_file($filename, $destination);
}

function utf8_encode_array($array)
{
    if (!is_array($array) && !is_object($array)) {
        return mb_convert_encoding($array, 'UTF-8', 'ISO-8859-1');
    }
    $result = [];
    foreach ($array as $cle => $value) {
        $result[mb_convert_encoding($cle, 'UTF-8', 'ISO-8859-1')] = utf8_encode_array($value);
    }
    return $result;
}

function number_format_fr($number): string
{
    return number_format($number, 0, ',', ' ');
}
