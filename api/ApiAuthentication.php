<?php

declare(strict_types=1);

use Pastell\Service\LoginAttemptLimit;
use Pastell\Service\Utilisateur\UserTokenService;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;

class ApiAuthentication
{
    private const DEFAULT_RATE_LIMITER_VARIABLE = 'REMOTE_ADDR';
    private array $server = [];
    private array $request = [];

    public function __construct(
        private readonly ConnexionControler $connexionControler,
        private readonly SQLQuery $sqlQuery,
        private readonly LoginAttemptLimit $loginAttemptLimit,
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly UserTokenService $userTokenService,
        private readonly string $rateLimiterVariable,
    ) {
    }

    public function setServerInfo(array $server): void
    {
        $this->server = $server;
    }

    public function setRequestInfo(array $request): void
    {
        $this->request = $request;
    }

    /**
     * @throws UnauthorizedException
     */
    public function getUtilisateurId(): int
    {
        try {
            $id_u = $this->getUtilisateurIdThrow();
        } catch (RateLimitExceededException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new UnauthorizedException($e->getMessage());
        }
        return $id_u;
    }

    /**
     * @throws Exception
     */
    private function getUtilisateurIdThrow(): int
    {
        $recuperateur = new Recuperateur($this->request);
        $auth = $recuperateur->get("auth");

        $id_u = false;

        if ($auth === 'cas') {
            $id_u = $this->connexionControler->apiExternalConnexion(null, false);
        }

        $certificatConnexion = new CertificatConnexion($this->sqlQuery);
        $utilisateur = new UtilisateurSQL($this->sqlQuery);
        $utilisateurListe = new UtilisateurListe($this->sqlQuery);

        if (!$id_u) {
            $id_u = $certificatConnexion->autoConnect();
        }

        if (!$id_u) {
            if (!empty($this->server['HTTP_AUTHORIZATION']) && $this->isBearer($this->server['HTTP_AUTHORIZATION'])) {
                $id_u = $this->authenticateByToken();
            } elseif (!empty($this->server['PHP_AUTH_USER'])) {
                $id_u = $this->authenticateByPassword($utilisateurListe, $utilisateur, $certificatConnexion);
            }
        }

        if (!$id_u) {
            throw new UnauthorizedException("Accès interdit");
        }
        if (! $this->utilisateurSQL->isEnabled($id_u)) {
            throw new UnauthorizedException('Votre compte a été désactivé');
        }
        return $id_u;
    }

    private function authenticateByToken(): ?int
    {
        $this->checkRateLimit();
        $authorizationHeader = $this->server['HTTP_AUTHORIZATION'];
        $token = substr($authorizationHeader, 7);
        $user = $this->userTokenService->getUserFromToken($token);
        if ($user !== null && !$user['is_expired']) {
            $this->resetRateLimit();
            return $user['id_u'];
        }
        return null;
    }

    private function authenticateByPassword(
        UtilisateurListe $utilisateurListe,
        UtilisateurSQL $utilisateur,
        CertificatConnexion $certificatConnexion
    ): ?int {
        $this->checkRateLimit();
        $userId = $utilisateurListe->getUtilisateurByLogin($this->server['PHP_AUTH_USER']);
        if ($userId && $utilisateur->verifPassword($userId, $this->server['PHP_AUTH_PW'])) {
            $this->resetRateLimit();
        } else {
            $userId = null;
        }
        if (!$certificatConnexion->connexionGranted($userId)) {
            $userId = null;
        }
        return $userId;
    }

    private function isBearer(string $authorizationHeader): bool
    {
        return \str_starts_with($authorizationHeader, 'Bearer ');
    }

    private function getRateLimiterVariable(): ?string
    {
        if (
            isset($this->server[$this->rateLimiterVariable])
            && $this->server[$this->rateLimiterVariable] !== ''
        ) {
            return $this->rateLimiterVariable;
        }
        if (
            isset($this->server[self::DEFAULT_RATE_LIMITER_VARIABLE])
            && $this->server[self::DEFAULT_RATE_LIMITER_VARIABLE] !== ''
        ) {
            return self::DEFAULT_RATE_LIMITER_VARIABLE;
        }
        return null;
    }

    private function checkRateLimit(): void
    {
        $rateLimiterVariable = $this->getRateLimiterVariable();
        if ($rateLimiterVariable === null) {
            return;
        }
        if ($this->loginAttemptLimit->isLoginAttemptAuthorized($this->server[$rateLimiterVariable]) === false) {
            throw new RateLimitExceededException(
                $this->loginAttemptLimit->getRateLimit($this->server[$rateLimiterVariable])
            );
        }
    }

    private function resetRateLimit(): void
    {
        $rateLimiterVariable = $this->getRateLimiterVariable();
        if ($rateLimiterVariable === null) {
            return;
        }
        $this->loginAttemptLimit->resetLoginAttempt($this->server[$rateLimiterVariable]);
    }
}
