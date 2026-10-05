<?php

class UtilisateurRoleCache
{
    public function __construct(
        private readonly UtilisateurRoleSQL $utilisateurRoleSQL,
        private readonly MemoryCache $memoryCache,
        private readonly int $cache_ttl_in_seconds,
    ) {
    }

    public function getAllDroitEntite($id_u, $id_e): array
    {
        $key = $this->getCacheKey($id_e, $id_u);
        $cached = $this->memoryCache->fetch($key);
        if ($cached !== false) {
            return $cached;
        }
        $data = $this->utilisateurRoleSQL->getAllDroitEntite($id_u, $id_e);
        $this->memoryCache->store($key, $data, $this->cache_ttl_in_seconds);
        return $data;
    }

    public function getAllDroit(int $id_u): array
    {
        $key = $this->getCacheKey('all', $id_u);
        $cached = $this->memoryCache->fetch($key);
        if ($cached !== false) {
            return $cached;
        }
        $data = $this->utilisateurRoleSQL->getAllDroit($id_u);
        $this->memoryCache->store($key, $data, $this->cache_ttl_in_seconds);
        return $data;
    }

    public function invalidate($id_u, $id_e): void
    {
        $this->memoryCache->delete($this->getCacheKey($id_e, $id_u));
        $this->memoryCache->delete($this->getCacheKey('all', $id_u));
    }

    private function getCacheKey($id_e, $id_u): string
    {
        return "pastell_role_utilisateur_{$id_u}_{$id_e}";
    }
}
