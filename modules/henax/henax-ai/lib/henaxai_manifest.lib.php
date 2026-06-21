<?php
/**
 * henax-ai — MANIFEST ENGINE (L0).  [PORTING STUB]
 *
 * Da portare 1:1 da henax-architect (è il motore maturo), con rename architect_* -> henaxai_*:
 *   - lib/architect_manifest.lib.php          -> schema + loader + validator + extract_skyllam_block
 *   - lib/architect_manifest_builder.lib.php  -> manifest.yaml -> README + architect.json + skyllam.json + autodiscovery
 *   - bin/build_manifests.php / bin/validate_manifests.php (bootstrap Dolibarr parametrico, NON hardcodare /var/www/html)
 *   - lib/architect_discovery.lib.php         -> grafo architettura (henaxai_discovery.lib.php)
 *
 * Questo è il CONTRATTO DI INTEROPERABILITA' centrale (vedi INTEROP.md §2):
 *   manifest.yaml = source-of-truth per documentazione (README), struttura (architect.json) e
 *   superficie AI-queryable (block skyllam: entities[] con detail_sql, stats[] con sql).
 *
 * Funzioni target (firme da architect):
 *   henaxai_manifest_schema(): array
 *   henaxai_load_manifest(string $modulePath): ?array
 *   henaxai_validate_manifest(array $m, $db = null): array{ok,errors,warnings}
 *   henaxai_extract_skyllam_block(string $modulePath): ?array        // usato dai consumer (es. SkyllamManifest)
 *   henaxai_manifest_build_all|diff|apply(string $modulePath): array
 *
 * Disaccoppiamenti rispetto ad architect:
 *   - sanare nomi tabella col trattino (llx_henax-architect_* -> llx_henaxai_*)
 *   - soft-dep opzionali via function_exists: hub_compute_kpi_cached (domicare), henaxinnerhelp_default_repo_map
 */

if (!function_exists('henaxai_manifest_schema')) {
    function henaxai_manifest_schema(): array
    {
        // Placeholder: copiare lo schema reale da architect_manifest_schema().
        return array(
            'module' => 'string', 'label' => 'string', 'description' => 'string', 'version' => 'string',
            'depends_on' => 'array', 'tables' => 'array', 'services' => 'array', 'endpoints' => 'array',
            'rights_range' => 'array', 'cron' => 'array', 'skyllam' => 'array',
        );
    }
}
