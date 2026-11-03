/* TechSpy: catálogo em fichas, carrinho e compra simulada. Tudo roda no navegador (localStorage). */
(() => {
  const CFG = window.TS || { frete: 0, freteGratis: 0, cupons: {}, raiz: '/' };
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const brl = (c) => (c / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const load = (k, d) => { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch (e) { return d; } };
  const save = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* modo privado: carrinho vale só na página */ } };

  let cart = load('techspy-cart', []);
  let view = 'cart';
  let coupon = null;
  let ship = {};

  const drawer = $('#ts-drawer');
  const scrim = $('.ts-scrim');
  const body = $('[data-ts-body]');
  const title = $('[data-ts-title]');
  let lastFocus = null;

  /* ---------- produtos: lê os artigos do Joomla ---------- */
  const cents = (txt) => {
    const m = /R\$\s*([\d.]+),(\d{2})/.exec(txt || '');
    return m ? parseInt(m[1].replace(/\./g, ''), 10) * 100 + parseInt(m[2], 10) : null;
  };
  const idOf = (s) => { let h = 0; for (const c of s) h = (h * 31 + c.charCodeAt(0)) | 0; return 'p' + (h >>> 0).toString(36); };
  const readProduct = (root) => {
    const a = $('h1 a, h2 a', root);
    const t = (a || $('h1, h2', root));
    const name = t ? t.textContent.trim() : '';
    const price = cents(root.textContent);
    const img = $('img', root);
    const firstP = $$('p', root).map((p) => p.textContent.trim()).find((x) => x && !/^Pre[çc]o:|^SKU:/i.test(x)) || '';
    return name && price != null ? { id: idOf(name), name, price, img: img ? img.getAttribute('src') : '', url: a ? a.href : location.href, desc: firstP } : null;
  };

  const cardHTML = (p) => `<div class="ts-card">
    <a class="ts-card__photo" href="${esc(p.url)}" tabindex="-1" aria-hidden="true">${p.img ? `<img src="${esc(p.img)}" alt="" loading="lazy">` : ''}</a>
    <h2><a href="${esc(p.url)}">${esc(p.name)}</a></h2>
    <p class="ts-card__desc">${esc(p.desc)}</p>
    <div class="ts-card__row"><span class="ts-price"><small>R$</small>${esc((p.price / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 }))}</span></div>
    <div class="ts-card__actions"><button type="button" class="ts-add" data-ts-add="${p.id}">Adicionar ao carrinho</button><a class="ts-ghost" href="${esc(p.url)}">Ficha</a></div>
  </div>`;

  const products = {};
  $$('.blog-item').forEach((it) => {
    const p = readProduct(it);
    if (!p) return;
    products[p.id] = p;
    it.insertAdjacentHTML('beforeend', cardHTML(p));
    it.classList.add('ts-js');
  });
  const art = $('.com-content-article');
  if (art && !$('.blog-item')) {
    const p = readProduct(art);
    if (p) {
      products[p.id] = p;
      p.img = p.img || '';
      const priceP = $$('p', art).find((x) => /R\$\s*[\d.]+,\d{2}/.test(x.textContent)) || art.lastElementChild;
      priceP.insertAdjacentHTML('afterend', `<div class="ts-buybar"><span class="ts-price"><small>R$</small>${esc((p.price / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 }))}</span><button type="button" class="ts-add" data-ts-add="${p.id}">Adicionar ao carrinho</button></div>`);
      priceP.hidden = true;
    }
  }

  /* evidências no hero: as 3 primeiras fotos reais do catálogo */
  const ev = $('[data-ts-evidence]');
  if (ev) {
    const list = Object.values(products).filter((p) => p.img).slice(0, 3);
    ev.innerHTML = list.map((p, i) => `<figure><img src="${esc(p.img)}" alt="" loading="lazy"><figcaption>ALVO 0${i + 1}</figcaption></figure>`).join('');
    if (!list.length) ev.hidden = true;
  }

  /* tarjas reveláveis */
  $$('.ts-redact').forEach((b) => b.addEventListener('click', () => b.classList.toggle('is-open')));

  /* ---------- carrinho ---------- */
  const count = () => cart.reduce((n, l) => n + l.qty, 0);
  const subtotal = () => cart.reduce((n, l) => n + l.qty * l.price, 0);
  const discount = () => (coupon ? Math.round(subtotal() * coupon.pct / 100) : 0);
  const shipping = () => {
    if (!cart.length) return 0;
    const sub = subtotal() - discount();
    return CFG.freteGratis > 0 && sub >= CFG.freteGratis * 100 ? 0 : Math.round(CFG.frete * 100);
  };
  const total = () => subtotal() - discount() + shipping();

  const persist = () => { save('techspy-cart', cart); $$('[data-ts-count]').forEach((e) => { e.textContent = count(); }); };
  const add = (id) => {
    const p = products[id];
    if (!p) return;
    const l = cart.find((x) => x.id === id);
    l ? l.qty++ : cart.push({ id, name: p.name, price: p.price, img: p.img, url: p.url, qty: 1 });
    persist();
    open('cart');
  };

  /* ---------- gaveta ---------- */
  function open(v) {
    lastFocus = document.activeElement;
    view = v;
    drawer.hidden = false;
    scrim.hidden = false;
    document.documentElement.style.overflow = 'hidden';
    render();
    $('button, input', drawer.querySelector('[data-ts-body]'))?.focus({ preventScroll: true });
  }
  function close() {
    drawer.hidden = true;
    scrim.hidden = true;
    document.documentElement.style.overflow = '';
    lastFocus?.focus?.();
  }

  const summary = () => `<div class="ts-sum">
    <div><span>Subtotal</span><span>${brl(subtotal())}</span></div>
    ${coupon ? `<div><span>Cupom ${esc(coupon.code)} (-${coupon.pct}%)</span><span>-${brl(discount())}</span></div>` : ''}
    <div><span>Frete</span><span>${shipping() ? brl(shipping()) : 'Grátis'}</span></div>
    <div class="ts-total"><span>Total</span><span>${brl(total())}</span></div></div>`;

  const views = {
    cart() {
      title.textContent = 'Carrinho';
      if (!cart.length) return `<p class="ts-empty">O carrinho está vazio.<br>Escolha um alvo no catálogo.</p><button class="ts-btn ts-btn--ink" type="button" data-ts-close>Voltar ao catálogo</button>`;
      return cart.map((l) => `<div class="ts-line">
        <img src="${esc(l.img)}" alt="">
        <div><b>${esc(l.name)}</b><small>${brl(l.price)}</small>
          <div class="ts-qty"><button type="button" data-ts-dec="${l.id}" aria-label="Diminuir">−</button><span>${l.qty}</span><button type="button" data-ts-inc="${l.id}" aria-label="Aumentar">+</button></div>
          <button class="ts-rm" type="button" data-ts-rm="${l.id}">Remover</button></div>
        <b>${brl(l.price * l.qty)}</b></div>`).join('')
        + `<form class="ts-coupon" data-ts-coupon><input name="c" placeholder="Cupom" aria-label="Cupom" autocomplete="off"><button class="ts-ghost" type="submit">Aplicar</button></form>
        <div class="ts-err" data-ts-err role="alert"></div>${summary()}
        <button class="ts-btn ts-btn--ink" type="button" data-ts-go="ship">Continuar para entrega</button>`;
    },
    ship() {
      title.textContent = 'Entrega';
      const v = (k) => esc(ship[k] || '');
      return `<form class="ts-form" data-ts-ship novalidate>
        <p class="ts-note">Use dados fictícios. Nada é enviado: tudo fica só neste navegador.</p>
        <label>Nome completo<input name="nome" value="${v('nome')}" required autocomplete="off"></label>
        <label>E-mail<input name="email" type="email" value="${v('email')}" required autocomplete="off"></label>
        <div class="two"><label>CEP<input name="cep" value="${v('cep')}" inputmode="numeric" maxlength="9" required autocomplete="off"></label>
        <label>Cidade<input name="cidade" value="${v('cidade')}" required autocomplete="off"></label></div>
        <label>Endereço<input name="end" value="${v('end')}" required autocomplete="off"></label>
        <div class="ts-err" data-ts-err role="alert"></div>
        <button class="ts-btn ts-btn--ink" type="submit">Continuar para pagamento</button>
        <button class="ts-ghost" type="button" data-ts-go="cart">Voltar</button></form>`;
    },
    pay() {
      title.textContent = 'Pagamento';
      return `<form class="ts-form" data-ts-pay>
        <p class="ts-note">Pagamento simulado. Nenhuma cobrança será feita.</p>
        <div class="ts-pay" role="radiogroup" aria-label="Forma de pagamento">
          <label><input type="radio" name="pg" value="PIX" checked> PIX (aprovação na hora)</label>
          <label><input type="radio" name="pg" value="Cartão de crédito"> Cartão de crédito em 1x</label>
          <label><input type="radio" name="pg" value="Boleto"> Boleto bancário</label></div>
        ${summary()}
        <button class="ts-btn ts-btn--ink" type="submit">Finalizar compra simulada</button>
        <button class="ts-ghost" type="button" data-ts-go="ship">Voltar</button></form>`;
    },
    done() {
      title.textContent = 'Pedido';
      const o = load('techspy-orders', [])[0];
      if (!o) return views.cart();
      return `<div class="ts-done"><span class="ts-stamp">APROVADO · SIMULADO</span><h3>Pedido ${esc(o.num)}</h3>
        <div class="ts-order">${o.items.map((i) => `${i.qty}× ${esc(i.name)}`).join('<br>')}<br><br>Pagamento: ${esc(o.pay)}<br>Entrega: ${esc(o.ship.end)}, ${esc(o.ship.cidade)}<br><b>Total: ${brl(o.total)}</b></div>
        <p class="ts-note">Compra de demonstração. Nenhum produto será enviado e nada foi cobrado.</p>
        <button class="ts-btn ts-btn--ink" type="button" data-ts-close>Voltar ao catálogo</button></div>`;
    },
    orders() {
      title.textContent = 'Meus pedidos';
      const os = load('techspy-orders', []);
      if (!os.length) return `<p class="ts-empty">Nenhum pedido neste navegador ainda.</p>`;
      return os.map((o) => `<div class="ts-order"><b>${esc(o.num)}</b> · ${esc(o.date)}<br>${o.items.map((i) => `${i.qty}× ${esc(i.name)}`).join('<br>')}<br>${esc(o.pay)} · <b>${brl(o.total)}</b></div>`).join('');
    },
  };

  function render() {
    body.innerHTML = (views[view] || views.cart)();
  }

  const setErr = (m) => { const e = $('[data-ts-err]', body); if (e) e.textContent = m; };

  /* ---------- eventos ---------- */
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-ts-add],[data-ts-open],[data-ts-close],[data-ts-go],[data-ts-inc],[data-ts-dec],[data-ts-rm]');
    if (!t) return;
    const d = t.dataset;
    if ('tsAdd' in d) return add(d.tsAdd);
    if ('tsOpen' in d) { e.preventDefault(); return open(d.tsOpen); }
    if ('tsClose' in d) return close();
    if ('tsGo' in d) { view = d.tsGo; return render(); }
    const l = cart.find((x) => x.id === (d.tsInc || d.tsDec || d.tsRm));
    if (!l) return;
    if ('tsInc' in d) l.qty++;
    if ('tsDec' in d) l.qty = Math.max(1, l.qty - 1);
    if ('tsRm' in d) cart = cart.filter((x) => x !== l);
    persist();
    render();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !drawer.hidden) close(); });

  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (f.matches('[data-ts-coupon]')) {
      e.preventDefault();
      const code = f.c.value.trim().toLowerCase();
      const c = CFG.cupons[code];
      if (!code) return;
      if (!c) return setErr('Cupom inválido.');
      if (subtotal() < c.min * 100) return setErr(`Este cupom vale a partir de ${brl(c.min * 100)}.`);
      coupon = { code, pct: c.pct };
      render();
    } else if (f.matches('[data-ts-ship]')) {
      e.preventDefault();
      ship = Object.fromEntries(new FormData(f));
      if (!ship.nome.trim() || !/^\S+@\S+\.\S+$/.test(ship.email) || !ship.cep.trim() || !ship.cidade.trim() || !ship.end.trim()) return setErr('Preencha todos os campos com um e-mail válido.');
      view = 'pay';
      render();
    } else if (f.matches('[data-ts-pay]')) {
      e.preventDefault();
      const btn = $('button[type=submit]', f);
      btn.disabled = true;
      btn.textContent = 'Enviando…';
      const pay = new FormData(f).get('pg');
      const now = new Date();
      const pedido = {
        nome: ship.nome, email: ship.email, cep: ship.cep, cidade: ship.cidade, end: ship.end,
        pagamento: pay, cupom: coupon ? coupon.code : '', total: total(),
        itens: cart.map((l) => ({ nome: l.name, qtd: l.qty, preco: l.price })),
      };
      const fim = (num) => {
        const orders = load('techspy-orders', []);
        orders.unshift({
          num: num || 'TS-' + now.toISOString().slice(2, 10).replace(/-/g, '') + '-L' + String(orders.length + 1).padStart(3, '0'),
          date: now.toLocaleString('pt-BR'),
          items: cart.map(({ name, qty }) => ({ name, qty })),
          total: pedido.total, pay, ship: { end: ship.end, cidade: ship.cidade },
        });
        save('techspy-orders', orders);
        cart = [];
        coupon = null;
        persist();
        view = 'done';
        render();
      };
      // grava no servidor para aparecer no painel; se falhar, o pedido vale só neste navegador
      fetch(CFG.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(pedido) })
        .then((r) => (r.ok ? r.json() : null))
        .then((j) => fim(j && j.ok ? j.num : null))
        .catch(() => fim(null));
    }
  });

  /* ---------- barra de busca e ordenação do catálogo ---------- */
  const grid = $('.blog-items, .com-content-category-blog__items');
  if (grid && $$('.blog-item', grid).length > 1) {
    const bar = document.createElement('div');
    bar.className = 'ts-toolbar';
    bar.innerHTML = `<label class="ts-find"><span class="ts-vh">Buscar produto</span><input type="search" placeholder="Buscar produto, marca ou peça" autocomplete="off"></label>
      <label class="ts-sort"><span class="ts-vh">Ordenar</span><select><option value="">Ordem padrão</option><option value="asc">Menor preço</option><option value="desc">Maior preço</option><option value="az">Nome A–Z</option></select></label>
      <output class="ts-found" aria-live="polite"></output>`;
    grid.before(bar);
    const items = $$('.blog-item', grid);
    const orig = items.slice();
    const priceOf = (it) => (products[$('[data-ts-add]', it)?.dataset.tsAdd] || {}).price || 0;
    const nameOf = (it) => (it.querySelector('h2')?.textContent || '').trim();
    const apply = () => {
      const q = $('input', bar).value.trim().toLowerCase();
      const s = $('select', bar).value;
      let list = orig.slice();
      if (s === 'asc') list.sort((a, b) => priceOf(a) - priceOf(b));
      if (s === 'desc') list.sort((a, b) => priceOf(b) - priceOf(a));
      if (s === 'az') list.sort((a, b) => nameOf(a).localeCompare(nameOf(b), 'pt-BR'));
      let n = 0;
      list.forEach((it) => {
        const hit = !q || it.textContent.toLowerCase().includes(q);
        it.hidden = !hit;
        n += hit ? 1 : 0;
        grid.append(it);
      });
      $('output', bar).textContent = n ? `${n} de ${orig.length} produtos` : 'Nenhum produto encontrado.';
    };
    bar.addEventListener('input', apply);
    apply();
  }

  persist();
})();
