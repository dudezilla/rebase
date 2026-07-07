<?php
/*
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.
*/
/*
 * vault.php — optional HashiCorp Vault client + Vault-validated authorization for congruency.
 *
 * When VAULT_ADDR + VAULT_TOKEN + VAULT_AUTH_PATH are configured (install.json or env), the App holds a
 * Vault token and CHECKS Vault to authorize: the shared auth secret lives in Vault, not at rest in the CMS
 * DB. Reads a KV secret and compares (constant-time) a presented token against a field of it.
 *
 * Config resolution (per key): a non-empty defined constant wins, else the environment variable of the same
 * name (so the secret VAULT_TOKEN can stay in env / a deploy-local install.json and out of committed files).
 *   VAULT_ADDR         e.g. http://127.0.0.1:8200
 *   VAULT_TOKEN        the App's Vault token (env/install.json — NOT committed)
 *   VAULT_AUTH_PATH    the KV path holding the auth secret (KV v2 e.g. secret/data/congruency; v1 secret/congruency)
 *   VAULT_TOKEN_FIELD  which field of the secret is the shared token (default: token)
 *
 * GRACEFUL BY DESIGN: unconfigured, unreachable, or malformed -> the helpers return null/false and the
 * existing api_keys/admin-session auth applies. All HTTP is best-effort with a short timeout; nothing throws.
 */

function congruency_vault_cfg($name, $default = '') {
    if (defined($name) && constant($name) !== '') { return (string) constant($name); }
    $v = getenv($name);
    return ($v === false || $v === '') ? $default : (string) $v;
}

function congruency_vault_configured() {
    return congruency_vault_cfg('VAULT_ADDR') !== ''
        && congruency_vault_cfg('VAULT_TOKEN') !== ''
        && congruency_vault_cfg('VAULT_AUTH_PATH') !== '';
}

/* GET a KV secret from Vault; returns the unwrapped data map (KV v2 -> data.data, KV v1 -> data) or null. */
function congruency_vault_get($path = null) {
    $addr  = congruency_vault_cfg('VAULT_ADDR');
    $token = congruency_vault_cfg('VAULT_TOKEN');
    if ($addr === '' || $token === '') { return null; }
    if ($path === null) { $path = congruency_vault_cfg('VAULT_AUTH_PATH'); }
    if ($path === '') { return null; }
    $url = rtrim($addr, '/') . '/v1/' . ltrim($path, '/');

    $raw = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array('X-Vault-Token: ' . $token),
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 3,
        ));
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(array('http' => array(
            'method'        => 'GET',
            'header'        => 'X-Vault-Token: ' . $token,
            'timeout'       => 3,
            'ignore_errors' => true,
        )));
        $raw = @file_get_contents($url, false, $ctx);
    }
    if ($raw === false || $raw === null || $raw === '') { return null; }
    $j = json_decode($raw, true);
    if (!is_array($j) || !isset($j['data']) || !is_array($j['data'])) { return null; }
    $d = $j['data'];
    if (isset($d['data']) && is_array($d['data'])) { return $d['data']; }   // KV v2
    return $d;                                                              // KV v1
}

/* Does the presented token match the App's authorization secret in Vault? Constant-time. */
function congruency_vault_authorizes($presented) {
    if (!is_string($presented) || $presented === '' || !congruency_vault_configured()) { return false; }
    $data = congruency_vault_get();
    if (!is_array($data)) { return false; }
    $field = congruency_vault_cfg('VAULT_TOKEN_FIELD', 'token');
    if (!isset($data[$field]) || !is_string($data[$field]) || $data[$field] === '') { return false; }
    return hash_equals($data[$field], $presented);
}

/* Can the App authorize itself at Vault right now (its token reads the auth secret)? For status views. */
function congruency_vault_ok() {
    if (!congruency_vault_configured()) { return false; }
    $d = congruency_vault_get();
    $field = congruency_vault_cfg('VAULT_TOKEN_FIELD', 'token');
    return is_array($d) && isset($d[$field]) && $d[$field] !== '';
}
?>
