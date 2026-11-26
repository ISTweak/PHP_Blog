<?php
declare(strict_types=1);

/**
 * Active Directory（LDAP）認証
 */
final class Ldap
{
    /** "EXAMPLE\taro" や "taro@example.local" を "taro" に正規化。不正な形式なら null */
    public static function normalizeUsername(string $username): ?string
    {
        $username = trim($username);
        if (str_contains($username, '\\')) {
            $username = substr($username, strrpos($username, '\\') + 1);
        }
        if (str_contains($username, '@')) {
            $username = (string) strstr($username, '@', true);
        }
        return preg_match('/^[A-Za-z0-9._\-]{1,64}$/', $username) ? strtolower($username) : null;
    }

    /**
     * 認証に成功したらユーザー情報を返す。ID・パスワード違いは null。
     *
     * @return array{username:string,display_name:string,email:string}|null
     * @throws RuntimeException サーバーに接続できない等
     */
    public static function authenticate(string $username, string $password): ?array
    {
        if (!function_exists('ldap_connect')) {
            throw new RuntimeException('PHP の ldap 拡張が有効になっていません。');
        }
        if ($password === '') {
            return null; // 空パスワードは匿名バインド扱いになるため必ず拒否
        }

        $cfg = (array) App::config('ldap', []);
        $scheme = !empty($cfg['use_ssl']) ? 'ldaps' : 'ldap';
        $uri = sprintf('%s://%s:%d', $scheme, $cfg['host'] ?? '', (int) ($cfg['port'] ?? ($scheme === 'ldaps' ? 636 : 389)));

        $conn = ldap_connect($uri);
        if ($conn === false) {
            throw new RuntimeException('LDAP の接続先が不正です: ' . $uri);
        }
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, (int) ($cfg['timeout'] ?? 5));
        if (defined('LDAP_OPT_TIMELIMIT')) {
            ldap_set_option($conn, LDAP_OPT_TIMELIMIT, (int) ($cfg['timeout'] ?? 5));
        }

        try {
            if (!empty($cfg['start_tls']) && !@ldap_start_tls($conn)) {
                throw new RuntimeException('StartTLS に失敗しました: ' . ldap_error($conn));
            }

            $bindName = $username . '@' . ($cfg['domain'] ?? '');
            if (!@ldap_bind($conn, $bindName, $password)) {
                $errno = ldap_errno($conn);
                if ($errno === 49) { // Invalid credentials
                    return null;
                }
                throw new RuntimeException('LDAP バインドに失敗しました (' . $errno . '): ' . ldap_error($conn));
            }

            $filter = '(&(objectCategory=person)(objectClass=user)(sAMAccountName='
                . ldap_escape($username, '', LDAP_ESCAPE_FILTER) . ')';
            $group = trim((string) ($cfg['required_group'] ?? ''));
            if ($group !== '') {
                // 1.2.840.113556.1.4.1941 = 入れ子グループも含めて判定
                $filter .= '(memberOf:1.2.840.113556.1.4.1941:=' . ldap_escape($group, '', LDAP_ESCAPE_FILTER) . ')';
            }
            $filter .= ')';

            $result = @ldap_search($conn, (string) ($cfg['base_dn'] ?? ''), $filter,
                ['samaccountname', 'displayname', 'mail', 'useraccountcontrol'], 0, 1);
            if ($result === false) {
                throw new RuntimeException('LDAP 検索に失敗しました: ' . ldap_error($conn));
            }
            $entries = ldap_get_entries($conn, $result);
            if (!is_array($entries) || ($entries['count'] ?? 0) < 1) {
                return null; // ユーザーが見つからない、または必須グループに属していない
            }
            $entry = $entries[0];
            if (((int) ($entry['useraccountcontrol'][0] ?? 0)) & 2) {
                return null; // 無効化されたアカウント
            }
            return [
                'username'     => strtolower((string) ($entry['samaccountname'][0] ?? $username)),
                'display_name' => (string) ($entry['displayname'][0] ?? $username),
                'email'        => (string) ($entry['mail'][0] ?? ''),
            ];
        } finally {
            @ldap_unbind($conn);
        }
    }
}
