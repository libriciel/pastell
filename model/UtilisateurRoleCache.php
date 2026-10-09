<?php

class UtilisateurRoleCache
{
    public function __construct(
        private readonly UtilisateurRoleSQL $utilisateurRoleSQL,
        private readonly MemoryCache $memoryCache,
        private readonly int $cache_ttl_in_seconds,
    ) {
    }

    public function getDroitsForEntite(int $id_u, int $id_e): array
    {
        $key = $this->getCacheKey($id_u, $id_e);
        $cached = $this->memoryCache->fetch($key);
        if ($cached) {
            return $cached;
        }
        $data = $this->utilisateurRoleSQL->getDroitsForEntite($id_u, $id_e);
        $this->memoryCache->store($key, $data, $this->cache_ttl_in_seconds);
        return $data;
    }

    public function getAllDroit(int $id_u): array
    {
        $key = $this->getAllDroitCacheKey($id_u);
        $cached = $this->memoryCache->fetch($key);
        if ($cached) {
            return $cached;
        }
        $data = $this->utilisateurRoleSQL->getAllDroit($id_u);
        $this->memoryCache->store($key, $data, $this->cache_ttl_in_seconds);
        return $data;
    }

    public function invalidate(int $id_u, int $id_e): void
    {
        $this->memoryCache->delete($this->getCacheKey($id_u, $id_e));
        $this->memoryCache->delete($this->getAllDroitCacheKey($id_u));
    }

    private function getCacheKey(int $id_u, int $id_e): string
    {
        return "pastell_role_utilisateur_{$id_u}_{$id_e}";
    }

    private function getAllDroitCacheKey(int $id_u): string
    {
        return "pastell_role_utilisateur_{$id_u}_all";
    }
}
