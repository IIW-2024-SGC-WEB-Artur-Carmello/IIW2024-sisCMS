<?php
// Recebe o pedido SIMULADO do checkout e grava na tabela ts_pedidos. Responde JSON.
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('{"ok":false}'); }
$in = json_decode(file_get_contents('php://input', false, null, 0, 20000), true);
if (!is_array($in) || empty($in['itens']) || !is_array($in['itens'])) { http_response_code(400); exit('{"ok":false}'); }

$t = static fn($v, $n) => mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $v)), 0, $n);
$itens = [];
foreach (array_slice($in['itens'], 0, 30) as $i) {
    $itens[] = ['nome' => $t($i['nome'] ?? '', 150), 'qtd' => max(1, min(99, (int) ($i['qtd'] ?? 1))), 'preco' => max(0, (int) ($i['preco'] ?? 0))];
}
$nome = $t($in['nome'] ?? '', 120); $email = $t($in['email'] ?? '', 120);
if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$itens) { http_response_code(422); exit('{"ok":false}'); }

try {
    require __DIR__ . '/db.php';
    [$pdo, $pre] = ts_db();
    $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'techspy');
    $n = $pdo->prepare("SELECT COUNT(*) FROM `{$pre}ts_pedidos` WHERE ip_hash=? AND criado > (NOW() - INTERVAL 1 HOUR)");
    $n->execute([$ip]);
    if ((int) $n->fetchColumn() >= 10) { http_response_code(429); exit('{"ok":false}'); }
    $ins = $pdo->prepare("INSERT INTO `{$pre}ts_pedidos` (criado,nome,email,cep,cidade,endereco,pagamento,cupom,total,itens,ip_hash) VALUES (NOW(),?,?,?,?,?,?,?,?,?,?)");
    $ins->execute([$nome, $email, $t($in['cep'] ?? '', 12), $t($in['cidade'] ?? '', 80), $t($in['end'] ?? '', 200),
        $t($in['pagamento'] ?? '', 30), $t($in['cupom'] ?? '', 40), max(0, (int) ($in['total'] ?? 0)), json_encode($itens, JSON_UNESCAPED_UNICODE), $ip]);
    $id = (int) $pdo->lastInsertId();
    $num = 'TS-' . date('ymd') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE `{$pre}ts_pedidos` SET num=? WHERE id=?")->execute([$num, $id]);
    echo json_encode(['ok' => true, 'num' => $num]);
} catch (Throwable $e) {
    http_response_code(500);
    echo '{"ok":false}';
}
