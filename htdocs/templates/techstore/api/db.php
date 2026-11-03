<?php
// Conexao com o banco do Joomla (reaproveita configuration.php) e tabela de pedidos da loja.
// Fica fora do ciclo do Joomla de proposito: roda leve e funciona em hospedagem gratuita.
function ts_db(): array
{
    require_once dirname(__DIR__, 3) . '/configuration.php';
    $c = new JConfig;
    $pre = (string) $c->dbprefix;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $pre)) { throw new RuntimeException('prefixo invalido'); }
    $host = $c->host;
    $port = 3306;
    if (strpos($host, ':') !== false) { [$host, $port] = explode(':', $host, 2); }
    $pdo = new PDO("mysql:host=$host;port=$port;dbname={$c->db};charset=utf8mb4", $c->user, $c->password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$pre}ts_pedidos` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        num VARCHAR(32) NOT NULL DEFAULT '',
        criado DATETIME NOT NULL,
        nome VARCHAR(120) NOT NULL, email VARCHAR(120) NOT NULL,
        cep VARCHAR(12) NOT NULL, cidade VARCHAR(80) NOT NULL, endereco VARCHAR(200) NOT NULL,
        pagamento VARCHAR(30) NOT NULL, cupom VARCHAR(40) NOT NULL DEFAULT '',
        total INT UNSIGNED NOT NULL, itens TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Pendente',
        ip_hash CHAR(64) NOT NULL, KEY (criado), KEY (ip_hash)
    ) DEFAULT CHARSET=utf8mb4");
    $st = $pdo->prepare("SELECT params FROM `{$pre}template_styles` WHERE template='techstore' AND client_id=0 ORDER BY home DESC, id LIMIT 1");
    $st->execute();
    $params = json_decode((string) $st->fetchColumn(), true) ?: [];
    return [$pdo, $pre, $params];
}
