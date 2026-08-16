const fs = require("fs");
const path = require("path");

const dir = path.join(__dirname, "..", "public");
fs.mkdirSync(dir, { recursive: true });
fs.mkdirSync(path.join(dir, "admin"), { recursive: true });

function page(title, body, extraScript = "") {
  return `<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>${title} | Reporta Évora</title>
<link href="/assets/img/favicon.png" rel="icon">
<link href="https://fonts.googleapis.com" rel="preconnect">
<link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&family=Poppins:wght@300;400;500;600&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="/assets/vendor/aos/aos.css" rel="stylesheet">
<link href="/assets/css/main.css" rel="stylesheet">
</head>
<body class="index-page">
<header id="header" class="header d-flex align-items-center fixed-top"><div id="site-header"></div></header>
<main class="main">${body}</main>
<div id="site-footer"></div>
<a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
<div id="preloader"></div>
<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/assets/vendor/aos/aos.js"></script>
<script src="/assets/js/main.js"></script>
<script src="/assets/js/site-layout.js"></script>
${extraScript}
</body></html>`;
}

function authShell(title, formId, fieldsHtml, submitLabel, extra = "") {
  return page(
    title,
    `<section class="section" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding-top:90px;padding-bottom:40px;background:radial-gradient(circle at top,#1f2933 0,#020617 45%,#000 100%)">
<div class="container" data-aos="fade-up"><div class="row justify-content-center"><div class="col-md-7 col-lg-5">
<div class="text-center mb-4"><h2 class="fw-semibold" style="color:#f9fafb;font-size:1.7rem">${title}</h2></div>
<div class="card border-0" style="border-radius:18px;box-shadow:0 20px 55px rgba(15,23,42,.7)"><div class="card-body p-4 p-md-5">
<div id="form-erro" class="alert alert-danger py-2 d-none"></div>
<div id="form-ok" class="alert alert-success py-2 d-none"></div>
<form id="${formId}">${fieldsHtml}
<button type="submit" class="btn btn-primary w-100 rounded-pill mt-2">${submitLabel}</button>
</form>${extra}
</div></div></div></div></div></section>`,
  );
}

function simple(title, html) {
  return page(
    title,
    `<section class="section" style="padding-top:120px;padding-bottom:60px"><div class="container" data-aos="fade-up">${html}</div></section>`,
  );
}

fs.writeFileSync(
  path.join(dir, "login.html"),
  authShell(
    "Iniciar sessão",
    "login-form",
    `<div class="mb-3"><label class="form-label">Utilizador ou email</label><input name="username" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Palavra-passe</label><input type="password" name="password" class="form-control" required></div>`,
    "Entrar",
    `<p class="text-center mt-3 mb-0 small"><a href="/forgot-password.html">Esqueceu a palavra-passe?</a> · <a href="/signup.html">Criar conta</a></p>`,
  ) +
    `<script>
document.getElementById('login-form').addEventListener('submit', async (e)=>{
 e.preventDefault();
 const fd=new FormData(e.target);
 const erro=document.getElementById('form-erro');
 erro.classList.add('d-none');
 try{
  const r=await fetch('/api/auth/login',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({username:fd.get('username'),password:fd.get('password')})});
  const d=await r.json();
  if(!d.ok){erro.textContent=d.message||'Erro';erro.classList.remove('d-none');return;}
  location.href=d.redirect||'/';
 }catch{erro.textContent='Erro de rede.';erro.classList.remove('d-none');}
});
</script>`,
);

fs.writeFileSync(
  path.join(dir, "signup.html"),
  authShell(
    "Criar Conta",
    "signup-form",
    `<div class="mb-3"><label class="form-label">Nome</label><input name="nome" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Utilizador</label><input name="username" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Telefone</label><input name="phone" class="form-control"></div>
<div class="mb-3"><label class="form-label">Data de nascimento</label><input type="date" name="birthday" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Género</label><select name="gender" class="form-select" required><option value="male">Masculino</option><option value="female">Feminino</option><option value="other">Outro</option></select></div>
<div class="mb-3"><label class="form-label">Palavra-passe</label><input type="password" name="password" class="form-control" required minlength="8"></div>
<div class="mb-3 d-none" id="verify-box"><label class="form-label">Código de verificação</label><input name="code" class="form-control" maxlength="6"></div>`,
    "Registar",
    `<p class="text-center mt-3 mb-0 small"><a href="/login.html">Já tem conta? Entrar</a></p>`,
  ) +
    `<script>
let step='form'; let emailPending='';
document.getElementById('signup-form').addEventListener('submit', async (e)=>{
 e.preventDefault();
 const fd=new FormData(e.target);
 const erro=document.getElementById('form-erro'); const ok=document.getElementById('form-ok');
 erro.classList.add('d-none'); ok.classList.add('d-none');
 try{
  if(step==='form'){
   const r=await fetch('/api/auth/signup',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'start',nome:fd.get('nome'),username:fd.get('username'),email:fd.get('email'),phone:fd.get('phone'),birthday:fd.get('birthday'),gender:fd.get('gender'),password:fd.get('password')})});
   const d=await r.json();
   if(!d.ok){erro.textContent=d.message;erro.classList.remove('d-none');return;}
   step='verify'; emailPending=d.email||fd.get('email');
   document.getElementById('verify-box').classList.remove('d-none');
   ok.textContent=d.message; ok.classList.remove('d-none');
  } else {
   const r=await fetch('/api/auth/signup',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'verify',email:emailPending,code:fd.get('code')})});
   const d=await r.json();
   if(!d.ok){erro.textContent=d.message;erro.classList.remove('d-none');return;}
   location.href=d.redirect||'/login.html';
  }
 }catch{erro.textContent='Erro de rede.';erro.classList.remove('d-none');}
});
</script>`,
);

const pages = {
  "verify-2fa.html":
    authShell(
      "Verificação em 2 passos",
      "v2fa-form",
      `<div class="mb-3"><label class="form-label">Código de 6 dígitos</label><input name="code" class="form-control" required maxlength="6"></div>`,
      "Verificar",
    ) +
    `<script>
document.getElementById('v2fa-form').addEventListener('submit', async (e)=>{
 e.preventDefault(); const fd=new FormData(e.target); const erro=document.getElementById('form-erro'); erro.classList.add('d-none');
 try{ const r=await fetch('/api/auth/verify-2fa',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({code:fd.get('code')})}); const d=await r.json(); if(!d.ok){erro.textContent=d.message;erro.classList.remove('d-none');return;} location.href=d.redirect||'/'; }catch{erro.textContent='Erro de rede.';erro.classList.remove('d-none');}
});
</script>`,
  "forgot-password.html":
    authShell(
      "Recuperar palavra-passe",
      "forgot-form",
      `<div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>`,
      "Enviar link",
    ) +
    `<script>
document.getElementById('forgot-form').addEventListener('submit', async (e)=>{
 e.preventDefault(); const fd=new FormData(e.target); const erro=document.getElementById('form-erro'); const ok=document.getElementById('form-ok');
 erro.classList.add('d-none'); ok.classList.add('d-none');
 const r=await fetch('/api/auth/forgot-password',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:fd.get('email')})});
 const d=await r.json(); if(!d.ok){erro.textContent=d.message;erro.classList.remove('d-none');} else {ok.textContent=d.message;ok.classList.remove('d-none');}
});
</script>`,
  "reset-password.html":
    authShell(
      "Nova palavra-passe",
      "reset-form",
      `<div class="mb-3"><label class="form-label">Nova palavra-passe</label><input type="password" name="password" class="form-control" required minlength="8"></div>`,
      "Guardar",
    ) +
    `<script>
document.getElementById('reset-form').addEventListener('submit', async (e)=>{
 e.preventDefault(); const fd=new FormData(e.target); const token=new URLSearchParams(location.search).get('token')||'';
 const erro=document.getElementById('form-erro'); erro.classList.add('d-none');
 const r=await fetch('/api/auth/reset-password',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({token,password:fd.get('password')})});
 const d=await r.json(); if(!d.ok){erro.textContent=d.message;erro.classList.remove('d-none');return;} location.href=d.redirect||'/login.html';
});
</script>`,
  "information.html": simple(
    "Informação útil",
    `<div class="section-title text-center mb-4"><h2>Informação útil</h2><p>Como usar a plataforma Reporta Évora.</p></div>
<div class="row gy-4"><div class="col-lg-8 mx-auto">
<p>Esta plataforma permite consultar notícias, ver ocorrências no mapa e registar problemas nos espaços verdes e nas estradas da cidade.</p>
<ul><li>Crie uma conta para acompanhar as suas ocorrências e mensagens.</li><li>Use o mapa para localizar pontos e ocorrências.</li><li>Contacte a equipa através do formulário de contacto.</li></ul>
<a class="btn btn-primary rounded-pill" href="/ocorrencias.html">Registar ocorrência</a>
</div></div>`,
  ),
};

for (const [name, content] of Object.entries(pages)) {
  fs.writeFileSync(path.join(dir, name), content);
}

fs.writeFileSync(
  path.join(dir, "contact.html"),
  simple(
    "Contacto",
    `<div class="section-title text-center mb-4"><h2>Contacto</h2><p>Envie-nos uma mensagem.</p></div>
<div class="row justify-content-center"><div class="col-lg-7">
<div id="c-erro" class="alert alert-danger d-none"></div><div id="c-ok" class="alert alert-success d-none"></div>
<form id="contact-form" class="card border-0 shadow-sm p-4" style="border-radius:18px">
<div class="mb-3" id="guest-fields"><label class="form-label">Nome</label><input name="nome" class="form-control"><label class="form-label mt-2">Email</label><input type="email" name="email" class="form-control"></div>
<div class="mb-3"><label class="form-label">Assunto</label><input name="assunto" class="form-control"></div>
<div class="mb-3"><label class="form-label">Mensagem</label><textarea name="mensagem" class="form-control" rows="5" required></textarea></div>
<button class="btn btn-primary rounded-pill">Enviar</button>
</form></div></div>`,
  ) +
    `<script>
(async()=>{ const me=await fetch('/api/auth/me',{credentials:'same-origin'}).then(r=>r.json()); if(me.loggedIn) document.getElementById('guest-fields').classList.add('d-none'); })();
document.getElementById('contact-form').addEventListener('submit', async (e)=>{
 e.preventDefault(); const fd=new FormData(e.target); const erro=document.getElementById('c-erro'); const ok=document.getElementById('c-ok');
 erro.classList.add('d-none'); ok.classList.add('d-none');
 const r=await fetch('/api/contact',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({nome:fd.get('nome'),email:fd.get('email'),assunto:fd.get('assunto'),mensagem:fd.get('mensagem')})});
 const d=await r.json(); if(!d.ok){erro.textContent=d.message;erro.classList.remove('d-none');} else {ok.textContent=d.message;ok.classList.remove('d-none'); e.target.reset();}
});
</script>`,
);

fs.writeFileSync(
  path.join(dir, "noticias.html"),
  simple(
    "Notícias",
    `<div class="section-title text-center mb-4"><h2>Notícias</h2><p>Atualizações sobre Évora.</p></div>
<div class="row gy-4" id="news-list"></div>`,
  ) +
    `<script>
(async()=>{
 const box=document.getElementById('news-list');
 const d=await fetch('/api/noticias').then(r=>r.json());
 if(!d.ok||!d.noticias.length){box.innerHTML='<p class="text-center text-muted">Sem notícias.</p>';return;}
 box.innerHTML=d.noticias.map(n=>'<div class="col-md-4"><div class="card h-100 border-0 shadow-sm" style="border-radius:18px;overflow:hidden"><img src="'+(n.imagem_lista||'/assets/img/blog/blog-post-1.webp')+'" style="height:200px;object-fit:cover" alt=""><div class="card-body"><small class="text-muted">'+(n.data_formatada||'')+'</small><h5>'+n.titulo+'</h5><p class="small text-muted">'+(n.resumo_curto||'')+'</p><a href="/noticia.html?id='+n.id+'">Ler</a></div></div></div>').join('');
})();
</script>`,
);

fs.writeFileSync(
  path.join(dir, "noticia.html"),
  simple(
    "Notícia",
    `<article id="noticia-box" class="col-lg-8 mx-auto"></article>
<div class="col-lg-8 mx-auto mt-4"><h4>Comentários</h4><div id="comments"></div>
<form id="comment-form" class="mt-3"><textarea name="texto" class="form-control mb-2" rows="3" required placeholder="O seu comentário"></textarea><button class="btn btn-primary btn-sm rounded-pill">Comentar</button></form></div>`,
  ) +
    `<script>
const id=new URLSearchParams(location.search).get('id');
(async()=>{
 const d=await fetch('/api/noticias?id='+id).then(r=>r.json());
 if(!d.ok){document.getElementById('noticia-box').innerHTML='<p>Notícia não encontrada.</p>';return;}
 const n=d.noticia; document.title=n.titulo+' | Reporta Évora';
 document.getElementById('noticia-box').innerHTML='<h1>'+n.titulo+'</h1><p class="text-muted">'+(n.data_formatada||'')+'</p>'+(n.imagem_detalhe||n.imagem_lista?'<img class="img-fluid rounded mb-3" src="'+(n.imagem_detalhe||n.imagem_lista)+'">':'')+'<div>'+String(n.conteudo||n.resumo||'').replace(/\\n/g,'<br>')+'</div>';
 document.getElementById('comments').innerHTML=(d.comments||[]).map(c=>'<div class="border rounded p-2 mb-2"><strong>'+c.nome+'</strong><div class="small text-muted">'+(c.criado_em||'')+'</div><div>'+c.texto+'</div></div>').join('')||'<p class="text-muted">Sem comentários.</p>';
})();
document.getElementById('comment-form').addEventListener('submit', async (e)=>{
 e.preventDefault(); const texto=new FormData(e.target).get('texto');
 const r=await fetch('/api/noticias/comments',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({noticia_id:Number(id),texto})});
 const d=await r.json(); if(!d.ok){alert(d.message);return;} location.reload();
});
</script>`,
);

function listPage(title, tipo) {
  return (
    simple(
      title,
      `<div class="section-title text-center mb-4"><h2>${title}</h2></div><div id="list" class="table-responsive"></div>`,
    ) +
    `<script>
(async()=>{
 const d=await fetch('/api/ocorrencias?tipo=${tipo}').then(r=>r.json());
 const el=document.getElementById('list');
 if(!d.ok||!d.data.length){el.innerHTML='<p class="text-center text-muted">Sem registos.</p>';return;}
 el.innerHTML='<table class="table table-striped"><thead><tr><th>ID</th><th>Descrição</th><th>Local</th><th>Data</th></tr></thead><tbody>'+d.data.map(o=>'<tr><td>'+o.id+'</td><td>'+(o.descricao||'')+'</td><td>'+(o.place_name||'')+'</td><td>'+(o.data_ocorrencia||'')+'</td></tr>').join('')+'</tbody></table>';
})();
</script>`
  );
}

fs.writeFileSync(
  path.join(dir, "listar-ocorrencias.html"),
  listPage("Lista de ocorrências (espaço verde)", "verde"),
);
fs.writeFileSync(
  path.join(dir, "listar-ocorrencias-estrada.html"),
  listPage("Lista de ocorrências (estrada)", "estrada"),
);

function occForm(title, endpoint) {
  return (
    simple(
      title,
      `<div class="section-title text-center mb-4"><h2>${title}</h2></div>
<div class="row justify-content-center"><div class="col-lg-8">
<div id="o-erro" class="alert alert-danger d-none"></div><div id="o-ok" class="alert alert-success d-none"></div>
<form id="occ-form" class="card border-0 shadow-sm p-4" style="border-radius:18px" enctype="multipart/form-data">
<div class="mb-3"><label class="form-label">Descrição</label><textarea name="descricao" class="form-control" required rows="4"></textarea></div>
<div class="mb-3"><label class="form-label">Local (nome)</label><input name="place_name" class="form-control"></div>
<div class="row"><div class="col-md-6 mb-3"><label class="form-label">Latitude</label><input name="latitude" class="form-control" required></div>
<div class="col-md-6 mb-3"><label class="form-label">Longitude</label><input name="longitude" class="form-control" required></div></div>
<div class="mb-3"><label class="form-label">Data</label><input type="date" name="data_ocorrencia" class="form-control"></div>
<div class="mb-3"><label class="form-label">Foto (JPG/PNG)</label><input type="file" name="imagem" class="form-control" accept="image/jpeg,image/png"></div>
<button class="btn btn-primary rounded-pill">Submeter</button>
</form></div></div>`,
    ) +
    `<script>
document.getElementById('occ-form').addEventListener('submit', async (e)=>{
 e.preventDefault(); const fd=new FormData(e.target); const erro=document.getElementById('o-erro'); const ok=document.getElementById('o-ok');
 erro.classList.add('d-none'); ok.classList.add('d-none');
 const r=await fetch('${endpoint}',{method:'POST',credentials:'same-origin',body:fd});
 const d=await r.json(); if(!d.ok){erro.textContent=d.message;erro.classList.remove('d-none');} else {ok.textContent=d.message;ok.classList.remove('d-none'); e.target.reset();}
});
</script>`
  );
}

fs.writeFileSync(
  path.join(dir, "ocorrencias.html"),
  occForm("Registar ocorrência (espaço verde)", "/api/ocorrencias"),
);
fs.writeFileSync(
  path.join(dir, "ocorrencias-estrada.html"),
  occForm("Registar ocorrência (estrada)", "/api/ocorrencias-estrada"),
);

fs.writeFileSync(
  path.join(dir, "mapa.html"),
  page(
    "Mapa",
    `<section class="section" style="padding-top:100px;padding-bottom:40px"><div class="container">
<div class="section-title text-center mb-3"><h2>Mapa 2D</h2><p>Ocorrências e espaços verdes em Évora.</p></div>
<div id="map" style="height:70vh;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.15)"></div>
</div></section>`,
    `<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const map=L.map('map').setView([38.5719,-7.9097],14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap'}).addTo(map);
async function load(url, color, label){
 const d=await fetch(url).then(r=>r.json());
 (d.data||[]).forEach(o=>{
  if(!o.latitude||!o.longitude) return;
  L.circleMarker([Number(o.latitude),Number(o.longitude)],{radius:7,color,fillColor:color,fillOpacity:.7})
   .bindPopup('<strong>'+label+'</strong><br>'+(o.descricao||o.nome||o.especie||'')+'<br>'+(o.place_name||'')).addTo(map);
 });
}
load('/api/map/ocorrencias','#dc2626','Ocorrência');
load('/api/map/ocorrencias-estrada','#ea580c','Estrada');
load('/api/map/arvores','#16a34a','Árvore');
</script>`,
  ),
);

fs.writeFileSync(
  path.join(dir, "profile.html"),
  simple(
    "Perfil",
    `<div class="section-title text-center mb-4"><h2>Perfil</h2></div><div id="profile-box" class="col-lg-6 mx-auto"></div>`,
  ) +
    `<script>
(async()=>{
 const me=await fetch('/api/auth/me',{credentials:'same-origin'}).then(r=>r.json());
 if(!me.loggedIn){location.href='/login.html?next=/profile.html';return;}
 document.getElementById('profile-box').innerHTML='<div class="card border-0 shadow-sm p-4" style="border-radius:18px"><h4>'+me.user.nome+'</h4><p class="text-muted mb-0">ID: '+me.user.id+'</p><a class="btn btn-outline-primary btn-sm rounded-pill mt-3" href="/seguranca.html">Segurança</a></div>';
})();
</script>`,
);

fs.writeFileSync(
  path.join(dir, "seguranca.html"),
  simple(
    "Segurança",
    `<div class="section-title text-center mb-4"><h2>Segurança</h2></div><p class="text-center text-muted">Gestão de palavra-passe e 2FA.</p><p class="text-center"><a href="/profile.html">Voltar ao perfil</a></p>`,
  ),
);

fs.writeFileSync(
  path.join(dir, "myocorrencias.html"),
  simple("Minhas ocorrências", `<div id="mine"></div>`) +
    `<script>
(async()=>{
 const me=await fetch('/api/auth/me',{credentials:'same-origin'}).then(r=>r.json());
 if(!me.loggedIn){location.href='/login.html';return;}
 const [a,b]=await Promise.all([fetch('/api/ocorrencias?mine=1&tipo=verde',{credentials:'same-origin'}).then(r=>r.json()), fetch('/api/ocorrencias?mine=1&tipo=estrada',{credentials:'same-origin'}).then(r=>r.json())]);
 const rows=[...(a.data||[]).map(x=>({...x,tipo:'Verde'})),...(b.data||[]).map(x=>({...x,tipo:'Estrada'}))];
 document.getElementById('mine').innerHTML=rows.length?('<table class="table"><thead><tr><th>Tipo</th><th>Descrição</th><th>Local</th></tr></thead><tbody>'+rows.map(o=>'<tr><td>'+o.tipo+'</td><td>'+(o.descricao||'')+'</td><td>'+(o.place_name||'')+'</td></tr>').join('')+'</tbody></table>'):'<p class="text-center text-muted">Sem ocorrências.</p>';
})();
</script>`,
);

fs.writeFileSync(
  path.join(dir, "mymensagens.html"),
  simple("Minhas mensagens", `<div id="msgs"></div>`) +
    `<script>
(async()=>{
 const me=await fetch('/api/auth/me',{credentials:'same-origin'}).then(r=>r.json());
 if(!me.loggedIn){location.href='/login.html';return;}
 const d=await fetch('/api/mymensagens',{credentials:'same-origin'}).then(r=>r.json());
 document.getElementById('msgs').innerHTML=(d.data&&d.data.length)?d.data.map(m=>'<div class="card mb-2 p-3"><strong>'+(m.assunto||'(sem assunto)')+'</strong><div class="small text-muted">'+(m.criado_em||'')+'</div><div>'+(m.mensagem||'')+'</div></div>').join(''):'<p class="text-center text-muted">Sem mensagens.</p>';
})();
</script>`,
);

fs.writeFileSync(
  path.join(dir, "unsubscribe.html"),
  simple(
    "Cancelar newsletter",
    `<p class="text-center">Para cancelar a newsletter use o link enviado no email, ou contacte-nos.</p>`,
  ),
);

fs.writeFileSync(
  path.join(dir, "newsletter-confirm.html"),
  simple("Confirmar newsletter", `<p class="text-center" id="nl">A confirmar...</p>`) +
    `<script>
(async()=>{
 const token=new URLSearchParams(location.search).get('token')||'';
 const d=await fetch('/api/newsletter/confirm?token='+encodeURIComponent(token)).then(r=>r.json());
 document.getElementById('nl').textContent=d.message|| (d.ok?'Confirmado.':'Erro');
})();
</script>`,
);

fs.writeFileSync(
  path.join(dir, "admin", "login.html"),
  `<!DOCTYPE html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login</title>
<link href="/Admin/assets/compiled/css/app.css" rel="stylesheet">
<link href="/Admin/assets/compiled/css/auth.css" rel="stylesheet">
</head><body><div id="auth"><div class="row h-100"><div class="col-lg-5 col-12"><div id="auth-left" class="p-5">
<h1 class="auth-title">Admin</h1><p class="auth-subtitle mb-4">Reporta Évora</p>
<div id="erro" class="alert alert-danger d-none"></div>
<form id="f1"><div class="form-group position-relative has-icon-left mb-4"><input type="text" name="username" class="form-control form-control-xl" placeholder="Utilizador" required></div>
<div class="form-group position-relative has-icon-left mb-4"><input type="password" name="password" class="form-control form-control-xl" placeholder="Palavra-passe" required></div>
<button class="btn btn-primary btn-block btn-lg shadow-lg mt-2 w-100">Entrar</button></form>
<form id="f2" class="d-none mt-3"><div class="form-group mb-3"><input name="code" class="form-control form-control-xl" placeholder="Código 2FA" maxlength="6" required></div><button class="btn btn-primary w-100">Verificar</button></form>
</div></div><div class="col-lg-7 d-none d-lg-block"><div id="auth-right"></div></div></div></div>
<script>
document.getElementById('f1').onsubmit=async(e)=>{e.preventDefault();const fd=new FormData(e.target);const erro=document.getElementById('erro');erro.classList.add('d-none');
const r=await fetch('/api/admin/login',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({username:fd.get('username'),password:fd.get('password')})});
const d=await r.json(); if(!d.ok){erro.textContent=d.message;erro.classList.remove('d-none');return;} if(d.require2fa){document.getElementById('f1').classList.add('d-none');document.getElementById('f2').classList.remove('d-none');return;} location.href=d.redirect||'/admin/';};
document.getElementById('f2').onsubmit=async(e)=>{e.preventDefault();const code=new FormData(e.target).get('code');
const r=await fetch('/api/admin/verify-2fa',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({code})});
const d=await r.json(); if(!d.ok){alert(d.message);return;} location.href=d.redirect||'/admin/';};
</script></body></html>`,
);

fs.writeFileSync(
  path.join(dir, "admin", "index.html"),
  `<!DOCTYPE html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard Admin</title>
<link href="/Admin/assets/compiled/css/app.css" rel="stylesheet">
</head><body><div id="app"><div id="main" class="layout-horizontal"><header class="mb-3"><div class="container"><nav class="navbar navbar-expand navbar-light navbar-top"><a href="/admin/" class="navbar-brand">Reporta Évora Admin</a>
<div class="ms-auto"><span id="who" class="me-3"></span><button id="logout" class="btn btn-sm btn-outline-danger">Sair</button></div></nav></div></header>
<div class="container"><div class="page-heading"><h3>Dashboard</h3></div>
<div class="row" id="stats"></div>
<div class="row mt-4"><div class="col-12"><div class="card"><div class="card-header"><h4>Listagens rápidas</h4></div><div class="card-body">
<select id="entity" class="form-select mb-3" style="max-width:280px"><option value="ocorrencias">Ocorrências</option><option value="ocorrencias-estrada">Ocorrências estrada</option><option value="arvores">Árvores</option><option value="noticias">Notícias</option><option value="contact">Contactos</option><option value="users-public">Utilizadores públicos</option><option value="users">Utilizadores internos</option></select>
<div class="table-responsive"><table class="table table-striped" id="tbl"><thead></thead><tbody></tbody></table></div>
</div></div></div></div></div></div></div>
<script>
async function boot(){
 const me=await fetch('/api/admin/me',{credentials:'same-origin'}).then(r=>r.json());
 if(!me.loggedIn){location.href='/admin/login.html';return;}
 document.getElementById('who').textContent=me.user.username+(me.user.isAdmin?' (admin)':'');
 const dash=await fetch('/api/admin/dashboard',{credentials:'same-origin'}).then(r=>r.json());
 const s=dash.stats||{};
 document.getElementById('stats').innerHTML=Object.entries(s).map(([k,v])=>'<div class="col-6 col-lg-2"><div class="card"><div class="card-body"><h6>'+k+'</h6><h3>'+v+'</h3></div></div></div>').join('');
 await loadList();
}
async function loadList(){
 const ent=document.getElementById('entity').value;
 const d=await fetch('/api/admin/list/'+ent,{credentials:'same-origin'}).then(r=>r.json());
 const rows=d.data||[];
 const keys=rows[0]?Object.keys(rows[0]).slice(0,6):[];
 document.querySelector('#tbl thead').innerHTML='<tr>'+keys.map(k=>'<th>'+k+'</th>').join('')+'<th></th></tr>';
 document.querySelector('#tbl tbody').innerHTML=rows.map(r=>'<tr>'+keys.map(k=>'<td>'+String(r[k]??'').slice(0,80)+'</td>').join('')+'<td><button class="btn btn-sm btn-danger" data-id="'+r.id+'">Apagar</button></td></tr>').join('');
 document.querySelectorAll('#tbl button').forEach(btn=>btn.onclick=async()=>{
  if(!confirm('Apagar #'+btn.dataset.id+'?')) return;
  await fetch('/api/admin/item/'+ent+'/'+btn.dataset.id,{method:'DELETE',credentials:'same-origin'});
  loadList();
 });
}
document.getElementById('entity').onchange=loadList;
document.getElementById('logout').onclick=async()=>{await fetch('/api/admin/logout',{method:'POST',credentials:'same-origin'});location.href='/admin/login.html';};
boot();
</script></body></html>`,
);

console.log("OK html files:", fs.readdirSync(dir).filter((f) => f.endsWith(".html")).length);
console.log("OK admin:", fs.readdirSync(path.join(dir, "admin")));
