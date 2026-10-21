<?php
// Fixture visual isolada: nenhum acesso ao banco, nenhuma escrita operacional.
$source = file_get_contents(__DIR__ . '/../../Dashboard/obra.php');
$start = strpos($source, '<div id="infos-obra" class="history-section">');
$end = strpos($source, '<section id="secao-eventos-obra"', $start);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Histórico da Obra · Prévia de validação</title>
<link rel="stylesheet" href="../../Dashboard/styleObra.css"><link rel="stylesheet" href="../../assets/css/components/obra-history.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>body{margin:0;background:var(--bg);color:var(--text)}.preview-header{display:flex;gap:25px;align-items:center;padding:20px 2.5%;border-bottom:1px solid var(--border)}.preview-header strong{font-size:25px;margin-right:auto}.preview-header span{font-size:12px;color:var(--muted)}.preview-header a{padding:9px 13px;border-radius:7px;background:var(--accent);color:white;text-decoration:none}.preview-controls{padding:12px 2.5%;display:flex;gap:12px}.preview-controls button{padding:5px 10px;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:5px}@media(max-width:650px){.preview-header span{display:none}.preview-header strong{font-size:18px}}</style>
</head><body style="display:block;overflow:auto;width:100%;min-height:100vh">
<div class="preview-header"><strong>Raymundi Construção…</strong><span>Tabela</span><span>Mapa de pavimentos</span><span>Histórico</span><span>Mais</span><a href="#infos-obra">Flow Review</a></div>
<div class="preview-controls"><button id="preview-theme">Alternar tema</button><button id="preview-many">Carregar 200 eventos</button><span>Dados fictícios para validação visual</span></div>
<?= substr($source, $start, $end - $start) ?>
<script src="../../assets/js/components/obra-history.js"></script><script>
const samples = [
 {id:9,data:'2026-10-21 14:32:00',tipo:'arquivo',assunto:'Adicionado Referências em PDF para Fachada',metadata:{ambiente:'Fachada'},arquivos:[{nome_original:'Fachada_Ref01.pdf',url:'#'}]},
 {id:8,data:'2026-10-21 11:08:00',tipo:'arquivo',assunto:'Adicionado Referências em IMG para Fachada',metadata:{ambiente:'Fachada'},arquivos:[{nome_original:'Fachada_Ref01.jpg',url:'history-preview.svg',thumbnail_url:'history-preview.svg'}]},
 {id:7,data:'2026-10-21 09:41:00',tipo:'manual',assunto:'Recebido retorno dos toons, com aprovação do Luciano.',metadata:{observacoes:'Retorno recebido e aprovado.'}},
 {id:6,data:'2026-10-16 16:20:00',tipo:'entrega',assunto:'Entrega P00 concluída',entrega:{etapa:'P00',status:'Entregue no prazo',data_recebimento:'2026-10-15',data_conclusao:'2026-10-16',imagens:[{nome_original:'Fachada - Vista principal',url:'history-preview.svg',thumbnail_url:'history-preview.svg'}],total_previews:1}},
 {id:5,data:'2026-10-16 15:10:00',tipo:'entrega',assunto:'Nova entrega P00 registrada para a modelagem/toons da fachada.'},
 {id:4,data:'2026-09-04',tipo:'entrega',assunto:'Nova entrega registrada para o status R00, com 37 imagens.',entrega:{etapa:'R00',quantidade_imagens:37}},
 {id:3,data:'2026-09-01',tipo:'manual',assunto:'Recebemos a confirmação da Janaina, que devemos fazer a criação do paisagismo e luminotécnico.'},
 {id:2,data:'2026-08-24',tipo:'PROJECT_START',assunto:'Projeto iniciado em onboarding operacional.',usuario_nome:'Pedro Sabel',usuario_thumb:'history-avatar.svg',metadata:{pacotes:['Fachada','Interiores'],observacoes:'Alinhamento inicial com a equipe.'}},
 {id:1,data:'2026-07-24',tipo:'outro',assunto:'Obra cadastrada.'}
];
const list = document.getElementById('list_acomp');
const view = FlowObraHistory.mount(list,{
 edit(){document.body.dataset.edits=Number(document.body.dataset.edits||0)+1;},
 remove(){document.body.dataset.deletions=Number(document.body.dataset.deletions||0)+1;},
 files(){},delivery(){}
}); view.setRows(samples);
document.getElementById('preview-theme').onclick=()=>document.body.classList.toggle('light');
document.getElementById('preview-many').onclick=()=>view.setRows(Array.from({length:200},(_,i)=>({...samples[i%samples.length],id:i+1})));
</script></body></html>
