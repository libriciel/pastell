<?php

declare(strict_types=1);

use Sabre\DAV\Client;
use Sabre\HTTP\ClientHttpException;
use Sabre\HTTP\Request;

class WebdavWrapper
{
    private Client $dav;
    private WebdavClientFactory $webdavClientFactory;

    public function __construct()
    {
        $this->setWebdavClientFactory(new WebdavClientFactory());
    }

    public function setWebdavClientFactory(WebdavClientFactory $webdavClientFactory): void
    {
        $this->webdavClientFactory = $webdavClientFactory;
    }

    public function setDataConnexion(string $url, string $user, string $password): void
    {
        $settings = [
            'baseUri' => $this->normalizeBaseUri($url),
            'userName' => $user,
            'password' => $password,
        ];
        $this->dav = $this->webdavClientFactory->getInstance($settings);
    }

    public function setAuthenticationByCertificate(
        string $certificatePath,
        string $keyPath,
        string $certificatePassword
    ): void {
        $this->dav->addCurlSetting(CURLOPT_SSLCERT, $certificatePath);
        $this->dav->addCurlSetting(CURLOPT_SSLKEY, $keyPath);
        $this->dav->addCurlSetting(CURLOPT_SSLKEYPASSWD, $certificatePassword);
    }

    /**
     * Do not verify peer's certificate
     * Should not be used
     * @see https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYPEER.html
     */
    public function allowInsecureConnection(): void
    {
        $this->dav->addCurlSetting(CURLOPT_SSL_VERIFYPEER, false);
    }

    /**
     * If the server answers with a 200 HTTP code, it needs to have a Dav header
     * @see http://www.webdav.org/specs/rfc4918.html#HEADER_DAV
     *
     * @throws Exception
     */
    public function isConnected(): bool
    {
        $options = $this->dav->send(new Request('OPTIONS', $this->dav->getAbsoluteUrl('') ?: ''));
        if ($options->getStatus() !== 200) {
            throw new Exception($options->getStatus() . ' : ' . $options->getStatusText());
        }

        if (!$options->getHeader('Dav')) {
            throw new Exception('Le serveur ne présente pas le header Dav');
        }

        return true;
    }

    /**
     * @param int<0,1> $depth
     */
    public function propfind(string $folder, array $properties = ['{DAV:}displayname'], int $depth = 0): array
    {
        $files = $this->dav->propFind($this->normalize($folder), $properties, $depth);
        if (!$files) {
            return [];
        }
        $result = [];
        foreach ($files as $file => $value) {
            $fileAttributes = [];
            foreach ($properties as $property) {
                $fileAttributes[$property] = $value[$property] ?? null;
            }
            $result[basename($file)] = $fileAttributes;
        }
        return $result;
    }

    /**
     * @return resource|string
     * @throws Exception
     */
    public function get(string $element)
    {
        $response = $this->dav->send(new Request('GET', $this->dav->getAbsoluteUrl($element) ?: ''));
        if ($response->getStatus() !== 200) {
            throw new Exception($response->getStatus() . ' : ' . $response->getStatusText());
        }

        return $response->getBody();
    }

    /**
     * @throws Exception
     */
    public function exists(string $element): bool
    {
        try {
            /**
             * Only check the current resource
             * @see http://www.webdav.org/specs/rfc4918.html#HEADER_Depth
             */
            $this->dav->propFind($this->normalize($element), [
                '{DAV:}displayname',
            ], 0);
        } catch (ClientHttpException $e) {
            if ($e->getCode() === 404) {
                return false;
            }
            throw new Exception($e->getCode() . ' ' . $e->getMessage(), $e->getCode(), $e);
        }
        return true;
    }

    /**
     * @throws ClientHttpException
     */
    public function listFolder(string $folder): array
    {
        $nlist = $this->dav->propFind($this->normalize($folder), [
            '{DAV:}displayname',
        ], 1);

        if (!$nlist) {
            return [];
        }
        $result = [];
        foreach ($nlist as $file => $value) {
            $result[] = basename($file);
        }
        return $result;
    }

    /**
     * @throws ClientHttpException
     */
    public function createFolder(string $folder, string $newFolderName): false|array
    {
        $folder_list = $this->listFolder($folder);
        if (in_array($newFolderName, $folder_list, true)) {
            return false;
        }
        return $this->dav->request('MKCOL', $this->normalize($newFolderName));
    }

    /**
     * @throws ClientHttpException
     * @throws Exception
     */
    public function delete(string $folder, string $ficrep): array
    {
        $folder_list = $this->listFolder($folder);
        if (in_array($ficrep, $folder_list, true)) {
            $filepath = $folder
                ? $folder . '/' . $ficrep
                : $ficrep;

            return $this->dav->request('DELETE', $this->normalize($filepath));
        }

        throw new Exception($ficrep . " n'est pas dans " . $folder);
    }

    /**
     * @throws ClientHttpException
     * @throws Exception
     */
    public function addDocument(string $folder, string $remote_file, string $file_content, array $headers = []): array
    {
        if ($folder) {
            $new_file = $folder . "/" . $remote_file;
        } else {
            $new_file = $remote_file;
        }

        $folder_list = $this->listFolder($folder);
        if (in_array($remote_file, $folder_list, true)) {
            throw new Exception($remote_file . ' existe déja ' . $folder);
        }

        $response = $this->dav->request('PUT', $this->normalize($new_file), $file_content, $headers);
        if ($response['statusCode'] !== 201) {
            throw new Exception('Erreur lors du dépôt webdav : code ' . $response['statusCode']);
        }
        return $response;
    }

    private function normalizeBaseUri(string $url): string
    {
        $parts = parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return rtrim($url, '/') . '/';
        }
        $result = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $result .= ':' . $parts['port'];
        }
        $result .= rtrim($this->normalize($parts['path'] ?? ''), '/') . '/';
        return $result;
    }

    private function normalize(string $folder): string
    {
        $tmp = \rawurlencode($folder);
        return \str_replace('%2F', '/', $tmp);
    }
}
