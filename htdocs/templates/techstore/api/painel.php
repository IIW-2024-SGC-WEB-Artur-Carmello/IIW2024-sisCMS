<?php
// Painel da loja TechSpy: pedidos + atalhos para o admin do Joomla. Senha: parametro "Senha do painel" do template.
session_start();
header('X-Robots-Tag: noindex');
require __DIR__ . '/db.php';
$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$brl = static fn($c) => 'R$ ' . number_format($c / 100, 2, ',', '.');
$raiz = rtrim(dirname($_SERVER['SCRIPT_NAME'], 4), '/');
$adm = $raiz . '/administrator/index.php';
$statuses = ['Pendente', 'Pago', 'Enviado', 'Entregue', 'Cancelado'];

try { [$pdo, $pre, $params] = ts_db(); } catch (Throwable $e) { http_response_code(500); exit('Sem acesso ao banco. Confirme que o Joomla esta instalado.'); }
$senha = (string) ($params['painel_senha'] ?? '');
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));

if (($_POST['acao'] ?? '') === 'entrar' && $senha !== '') {
    if (hash_equals($senha, (string) ($_POST['senha'] ?? ''))) { session_regenerate_id(true); $_SESSION['ok'] = true; header('Location: painel.php'); exit; }
    sleep(1); $erro = 'Senha incorreta.';
}
if (isset($_GET['sair'])) { session_destroy(); header('Location: painel.php'); exit; }
$logado = !empty($_SESSION['ok']) && $senha !== '';

if ($logado && ($_POST['acao'] ?? '') === 'status' && hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
    if (in_array($_POST['status'] ?? '', $statuses, true)) {
        $pdo->prepare("UPDATE `{$pre}ts_pedidos` SET status=? WHERE id=?")->execute([$_POST['status'], (int) $_POST['id']]);
    }
    header('Location: painel.php'); exit;
}
if ($logado && isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=pedidos-techspy.csv');
    echo "\xEF\xBB\xBF"; $o = fopen('php://output', 'w');
    fputcsv($o, ['Pedido', 'Data', 'Cliente', 'Email', 'Cidade', 'Pagamento', 'Cupom', 'Total', 'Status'], ';');
    foreach ($pdo->query("SELECT * FROM `{$pre}ts_pedidos` ORDER BY id DESC") as $r) {
        fputcsv($o, [$r['num'], $r['criado'], $r['nome'], $r['email'], $r['cidade'], $r['pagamento'], $r['cupom'], number_format($r['total'] / 100, 2, ',', ''), $r['status']], ';');
    }
    exit;
}
$pedidos = $logado ? $pdo->query("SELECT * FROM `{$pre}ts_pedidos` ORDER BY id DESC LIMIT 200")->fetchAll() : [];
$receita = 0; foreach ($pedidos as $p) { if ($p['status'] !== 'Cancelado') { $receita += (int) $p['total']; } }
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Painel TechSpy</title>
<style>
:root{--g:#14201b;--g2:#1c2d26;--m:#e3a83a;--mh:#f1c460;--ink:#101512;--r:#e5483f;color-scheme:dark}
*{box-sizing:border-box}body{margin:0;background:var(--g);color:#e8efe9;font:16px/1.5 system-ui,sans-serif}
header{background:var(--m);color:var(--ink);display:flex;gap:16px;align-items:center;justify-content:space-between;padding:14px 24px;flex-wrap:wrap}
header b{font:900 1.6rem/1 "Arial Narrow",Impact,sans-serif;text-transform:uppercase}header a{color:var(--ink);font-weight:700}
main{max-width:1100px;margin:0 auto;padding:24px}h2{font:900 1.4rem/1 "Arial Narrow",Impact,sans-serif;text-transform:uppercase;color:var(--mh);margin:32px 0 12px}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
.cards a{display:block;background:var(--m);color:var(--ink);text-decoration:none;padding:16px;font-weight:700;border-radius:2px 12px 2px 2px}.cards a:hover{background:var(--mh)}
.cards small{display:block;font-weight:400;opacity:.8}
.kpi{display:flex;gap:12px;flex-wrap:wrap}.kpi div{background:var(--g2);padding:14px 20px;border-top:4px solid var(--m)}.kpi b{display:block;font-size:1.7rem}
table{width:100%;border-collapse:collapse;background:var(--g2)}th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #2e4439;vertical-align:top;font-size:.92rem}th{color:var(--mh)}
select,input,button{font:inherit;padding:8px 10px;border:2px solid var(--m);background:var(--g);color:#fff}button{background:var(--m);color:var(--ink);font-weight:700;cursor:pointer}
.box{max-width:380px;margin:12vh auto;background:var(--g2);padding:28px;border-top:6px solid var(--m)}.box input{width:100%;margin:10px 0}.err{color:var(--r)}
.wrap{overflow:auto}
</style></head><body>
<?php if (!$logado) : ?>
<div class="box"><h2 style="margin-top:0">Painel TechSpy</h2>
<?php if ($senha === '') : ?>
  <p>Defina uma senha primeiro: <b>Sistema &gt; Estilos de template do site &gt; techstore &gt; aba Loja TechSpy &gt; Senha do painel</b>. Depois recarregue esta página.</p>
<?php else : ?>
  <form method="post"><input type="hidden" name="acao" value="entrar"><label>Senha<input type="password" name="senha" autofocus required></label>
  <?php if (!empty($erro)) { echo '<p class="err">' . $h($erro) . '</p>'; } ?><button>Entrar</button></form>
<?php endif; ?></div>
<?php else : ?>
<header><b>Painel TechSpy</b><span><a href="<?php echo $h($raiz ?: '/'); ?>/">Ver loja</a> · <a href="?sair=1">Sair</a></span></header>
<main>
<h2>Atalhos</h2>
<div class="cards">
 <a href="<?php echo $h($adm); ?>?option=com_content&task=article.add">Novo produto<small>Crie um artigo com "Preço: R$ 0,00" e foto</small></a>
 <a href="<?php echo $h($adm); ?>?option=com_content&view=articles">Produtos<small>Editar, despublicar, excluir</small></a>
 <a href="<?php echo $h($adm); ?>?option=com_templates&view=styles&client_id=0">Configurar loja<small>Nome, frete, cupons, cor</small></a>
 <a href="<?php echo $h($adm); ?>?option=com_users&view=users">Clientes<small>Contas cadastradas</small></a>
 <a href="<?php echo $h($adm); ?>?option=com_menus&view=items&menutype=mainmenu">Menu do site<small>Abas do cabeçalho</small></a>
 <a href="<?php echo $h($adm); ?>?option=com_media">Imagens<small>Enviar fotos de produtos</small></a>
</div>
<h2>Resumo</h2>
<div class="kpi"><div>Pedidos<b><?php echo count($pedidos); ?></b></div><div>Receita simulada<b><?php echo $brl($receita); ?></b></div>
<div>Pendentes<b><?php echo count(array_filter($pedidos, fn($p) => $p['status'] === 'Pendente')); ?></b></div></div>
<h2>Pedidos <a style="font-size:.8rem;color:#fff" href="?csv=1">baixar CSV</a></h2>
<div class="wrap"><table><tr><th>Pedido</th><th>Cliente</th><th>Itens</th><th>Total</th><th>Pagamento</th><th>Status</th></tr>
<?php foreach ($pedidos as $p) : $it = json_decode($p['itens'], true) ?: []; ?>
<tr><td><b><?php echo $h($p['num']); ?></b><br><small><?php echo $h($p['criado']); ?></small></td>
<td><?php echo $h($p['nome']); ?><br><small><?php echo $h($p['email']); ?><br><?php echo $h($p['endereco'] . ', ' . $p['cidade'] . ' ' . $p['cep']); ?></small></td>
<td><?php foreach ($it as $i) { echo (int) $i['qtd'] . '× ' . $h($i['nome']) . '<br>'; } ?></td>
<td><?php echo $brl((int) $p['total']); ?><?php echo $p['cupom'] ? '<br><small>cupom ' . $h($p['cupom']) . '</small>' : ''; ?></td>
<td><?php echo $h($p['pagamento']); ?></td>
<td><form method="post"><input type="hidden" name="acao" value="status"><input type="hidden" name="csrf" value="<?php echo $h($_SESSION['csrf']); ?>"><input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
<select name="status" onchange="this.form.submit()"><?php foreach ($statuses as $s) { echo '<option' . ($s === $p['status'] ? ' selected' : '') . '>' . $h($s) . '</option>'; } ?></select></form></td></tr>
<?php endforeach; if (!$pedidos) { echo '<tr><td colspan="6">Nenhum pedido ainda.</td></tr>'; } ?>
</table></div>
</main>
<?php endif; ?>
</body></html>
