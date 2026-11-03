<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/** @var Joomla\CMS\Document\HtmlDocument $this */
$app   = Factory::getApplication();
$menu  = $app->getMenu();
$home  = $menu->getActive() === $menu->getDefault() && !in_array($app->getInput()->getCmd('view'), ['article', 'login', 'registration', 'profile', 'reset', 'remind'], true);
$tpl   = Uri::root(true) . '/templates/' . $this->template;
$root  = Uri::root(true) . '/';
$p     = $this->params;
$nome  = htmlspecialchars($p->get('loja_nome', 'TechSpy'), ENT_QUOTES, 'UTF-8');
$cor   = preg_match('/^#[0-9a-f]{6}$/i', $p->get('cor_carimbo', '')) ? $p->get('cor_carimbo') : '#e5483f';
$wa    = preg_replace('/\D/', '', (string) $p->get('whatsapp', ''));

$cupons = [];
foreach (preg_split('/\r?\n/', (string) $p->get('cupons', '')) as $linha) {
    $c = array_map('trim', explode('|', $linha));
    if ($c[0] !== '' && isset($c[1])) {
        $cupons[strtolower($c[0])] = ['pct' => (float) $c[1], 'min' => (float) ($c[2] ?? 0)];
    }
}
$user   = $app->getIdentity();
$conta  = $root . 'index.php?option=com_users&view=' . ($user->guest ? 'login' : 'profile');
$sair   = $root . 'index.php?option=com_users&task=user.logout&' . \Joomla\CMS\Session\Session::getFormToken() . '=1&return=' . base64_encode($root);
$cadas  = $root . 'index.php?option=com_users&view=registration';
$cfg = [
    'api'        => $tpl . '/api/pedido.php',
    'nome'       => $p->get('loja_nome', 'TechSpy'),
    'frete'      => (float) $p->get('frete', 49.9),
    'freteGratis'=> (float) $p->get('frete_gratis', 5000),
    'cupons'     => $cupons,
    'whatsapp'   => $wa,
    'raiz'       => $root,
];
?>
<!doctype html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
<head>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#14201b">
<jdoc:include type="metas" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;900&family=Hanken+Grotesk:wght@400;600;800&family=Special+Elite&display=swap">
<link rel="stylesheet" href="<?php echo $tpl; ?>/css/techspy.css?v=<?php echo filemtime(__DIR__ . '/css/techspy.css'); ?>">
<style>:root{--stamp:<?php echo $cor; ?>}</style>
<jdoc:include type="styles" />
<jdoc:include type="scripts" />
<script>window.TS=<?php echo json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;</script>
</head>
<body class="ts-body<?php echo $home ? ' ts-home' : ''; ?>">
<a class="ts-skip" href="#conteudo">Pular para o conteúdo</a>

<div class="ts-strip"><span><?php echo htmlspecialchars($p->get('aviso', ''), ENT_QUOTES, 'UTF-8'); ?></span></div>

<header class="ts-header">
  <a class="ts-brand" href="<?php echo $root; ?>" aria-label="<?php echo $nome; ?>, página inicial">
    <svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="17" cy="17" r="10" fill="none" stroke="currentColor" stroke-width="4"/><path d="m25 25 11 11" stroke="currentColor" stroke-width="5" stroke-linecap="round"/><circle cx="17" cy="17" r="3.5" fill="currentColor"/></svg>
    <span><?php echo $nome; ?></span>
  </a>
  <nav class="ts-tabs" aria-label="Menu principal"><jdoc:include type="modules" name="menu" style="none" /></nav>
  <div class="ts-account">
    <?php if ($user->guest) : ?>
      <a href="<?php echo $conta; ?>">Entrar</a><a href="<?php echo $cadas; ?>">Criar conta</a>
    <?php else : ?>
      <a href="<?php echo $conta; ?>">Olá, <?php echo htmlspecialchars(explode(' ', trim($user->name))[0], ENT_QUOTES, 'UTF-8'); ?></a><a href="<?php echo $sair; ?>">Sair</a>
    <?php endif; ?>
  </div>
  <button class="ts-cartbtn" type="button" data-ts-open="cart" aria-label="Abrir carrinho">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2.2l2.1 11h10.4l2-8H6.2M9.5 20.2h.01M17 20.2h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Carrinho</span><b data-ts-count>0</b>
  </button>
</header>

<?php if ($home) : ?>
<section class="ts-hero" aria-labelledby="ts-hero-t">
  <div class="ts-folder">
    <span class="ts-folder__tab">DOSSIÊ Nº 023</span>
    <span class="ts-stamp" aria-hidden="true">CONFIDENCIAL</span>
    <h1 id="ts-hero-t">Hardware fichado, peça por peça.</h1>
    <p>Processadores, placas de vídeo, memória, SSD, fonte e gabinete, cada um com ficha técnica, preço e carrinho de verdade. Só a compra é simulada.</p>
    <p class="ts-leak">Conteúdo do arquivo: <button type="button" class="ts-redact" aria-label="Revelar texto">8 peças, 1 carrinho, nenhuma cobrança</button></p>
    <a class="ts-btn ts-btn--ink" href="#conteudo">Abrir catálogo</a>
    <div class="ts-evidence" data-ts-evidence aria-hidden="true"></div>
  </div>
</section>
<?php endif; ?>

<main class="ts-main" id="conteudo">
  <jdoc:include type="message" />
  <jdoc:include type="component" />
</main>

<footer class="ts-footer">
  <div class="ts-footer__grid">
    <div><strong class="ts-footer__brand"><?php echo $nome; ?></strong><p><?php echo htmlspecialchars($p->get('rodape_texto', ''), ENT_QUOTES, 'UTF-8'); ?></p></div>
    <div class="ts-footer__nav"><jdoc:include type="modules" name="footer" style="none" />
      <?php if ($wa !== '') : ?><a href="https://wa.me/<?php echo $wa; ?>" rel="noopener">Falar no WhatsApp</a><?php endif; ?>
      <a href="#" data-ts-open="orders">Meus pedidos</a>
      <a href="<?php echo $tpl; ?>/api/painel.php" rel="nofollow">Painel da loja</a>
    </div>
  </div>
  <p class="ts-footer__legal">Site fictício, criado apenas para brincadeira e demonstração. Produtos, preços, estoque, ofertas e condições exibidos não representam vendas reais. Nenhum dado digitado aqui sai do seu navegador.</p>
</footer>

<div class="ts-scrim" data-ts-close hidden></div>
<aside class="ts-drawer" id="ts-drawer" role="dialog" aria-modal="true" aria-label="Carrinho" hidden>
  <div class="ts-drawer__head"><b data-ts-title>Carrinho</b><button type="button" data-ts-close aria-label="Fechar">Fechar</button></div>
  <div class="ts-drawer__body" data-ts-body></div>
</aside>

<script src="<?php echo $tpl; ?>/js/techspy.js?v=<?php echo filemtime(__DIR__ . '/js/techspy.js'); ?>" defer></script>
<jdoc:include type="modules" name="debug" style="none" />
</body>
</html>
