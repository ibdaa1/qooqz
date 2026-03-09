<?php
/**
 * TORO Admin — pages/brands.php
 * CRUD for brands table via API  (POST /v1/brands, GET /v1/brands, etc.)
 */
declare(strict_types=1);

$ADMIN_PAGE  = 'brands';
$ADMIN_TITLE = 'العلامات التجارية';

require_once __DIR__ . '/../includes/header.php';

$apiBase = '/toro/api/v1';
?>

<div class="page-header">
  <h1><?= t('nav.brands') ?></h1>
  <button class="btn btn-primary" id="btnAddBrand">
    <svg data-feather="plus"></svg> <?= t('btn.add') ?>
  </button>
</div>

<div id="alertBox" style="display:none"></div>

<!-- Brands table -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= t('nav.brands') ?></span>
    <div class="d-flex gap-2">
      <input type="search" class="form-control" id="brandSearch" placeholder="<?= t('search_placeholder') ?>" style="width:220px">
      <button class="btn btn-outline btn-sm" id="btnRefresh"><svg data-feather="refresh-cw"></svg></button>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th><?= t('table.id') ?></th>
          <th>— </th>
          <th><?= t('table.name') ?></th>
          <th><?= t('table.slug') ?></th>
          <th><?= t('form.brand_website') ?></th>
          <th><?= t('form.brand_sort') ?></th>
          <th><?= t('table.status') ?></th>
          <th><?= t('table.actions') ?></th>
        </tr>
      </thead>
      <tbody id="brandsTbody">
        <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--clr-muted)"><span class="spinner"></span></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Brand Form Modal ───────────────────────────────────── -->
<div class="modal-backdrop" id="brandModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="brandModalTitle"><?= t('btn.add') ?></span>
      <button class="btn-icon" onclick="closeModal('brandModal')"><svg data-feather="x"></svg></button>
    </div>
    <input type="hidden" id="brandId">

    <div class="form-grid-2">
      <div class="form-group">
        <label class="form-label"><?= t('form.brand_slug') ?></label>
        <input type="text" class="form-control" id="brandSlug" placeholder="my-brand">
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('form.brand_website') ?></label>
        <input type="url" class="form-control" id="brandWebsite" placeholder="https://...">
      </div>
    </div>

    <div class="form-grid-2">
      <div class="form-group">
        <label class="form-label"><?= t('form.brand_sort') ?></label>
        <input type="number" class="form-control" id="brandSort" value="0" min="0">
      </div>
      <div class="form-group" style="display:flex;align-items:flex-end">
        <label class="form-check">
          <input type="checkbox" id="brandActive" checked>
          <?= t('form.brand_active') ?>
        </label>
      </div>
    </div>

    <hr style="border-color:var(--clr-border);margin:.75rem 0">
    <p class="form-label"><?= t('form.brand_translations') ?></p>

    <div id="transFields"></div>

    <div id="brandError" class="alert alert-danger" style="display:none"></div>
    <div class="d-flex gap-2" style="margin-top:1rem">
      <button class="btn btn-primary" id="btnSaveBrand"><?= t('btn.save') ?></button>
      <button class="btn btn-outline" onclick="closeModal('brandModal')"><?= t('btn.cancel') ?></button>
    </div>
  </div>
</div>

<!-- Delete confirm -->
<div class="modal-backdrop" id="delBrandModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <span class="modal-title"><?= t('btn.delete') ?></span>
      <button class="btn-icon" onclick="closeModal('delBrandModal')"><svg data-feather="x"></svg></button>
    </div>
    <p style="margin-bottom:1.25rem"><?= t('msg.confirm_delete') ?></p>
    <div class="d-flex gap-2">
      <button class="btn btn-danger" id="btnConfirmDelBrand"><?= t('btn.confirm') ?></button>
      <button class="btn btn-outline" onclick="closeModal('delBrandModal')"><?= t('btn.cancel') ?></button>
    </div>
  </div>
</div>

<?php
$ADMIN_EXTRA_JS = <<<JS
<script>
(function(){
const API = '<?= $apiBase ?>';
const token = sessionStorage.getItem('admin_token') || '';

function hdr(json=true){
  const h = {};
  if(json) h['Content-Type']='application/json';
  if(token) h['Authorization']='Bearer '+token;
  return h;
}
function openModal(id){ document.getElementById(id).classList.add('open'); feather.replace({width:16,height:16}); }
window.closeModal = window.closeModal || function(id){ document.getElementById(id).classList.remove('open'); };

// ── Load brands ──────────────────────────────────────
async function loadBrands(){
  const q = document.getElementById('brandSearch').value;
  let url = API+'/brands';
  if(q) url += '?search='+encodeURIComponent(q);
  const r = await fetch(url, {headers:hdr()});
  const d = await r.json();
  const brands = d.data || d.brands || [];
  renderBrands(brands);
}

function renderBrands(brands){
  const tb = document.getElementById('brandsTbody');
  if(!brands.length){
    tb.innerHTML='<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--clr-muted)"><?= t('label.no_data') ?></td></tr>';
    return;
  }
  tb.innerHTML = brands.map(function(b){
    const name = (b.translations && b.translations[0]) ? b.translations[0].name : (b.name||b.slug);
    return `<tr>
      <td>\${b.id}</td>
      <td><img src="\${b.logo||''}" class="img-thumb" onerror="this.style.display='none'"></td>
      <td>\${escHtml(name)}</td>
      <td><code style="font-size:.75rem">\${escHtml(b.slug||'')}</code></td>
      <td><a href="\${escHtml(b.website||'')}" target="_blank" style="color:var(--clr-primary)">\${escHtml(b.website||'—')}</a></td>
      <td>\${b.sort_order||0}</td>
      <td>\${b.is_active?'<span class="badge badge-success"><?= t('label.active') ?></span>':'<span class="badge badge-danger"><?= t('label.inactive') ?></span>'}</td>
      <td><div class="table-actions">
        <button class="btn btn-outline btn-xs" onclick="editBrand(\${b.id})"><svg data-feather="edit-2"></svg></button>
        <button class="btn btn-danger btn-xs" onclick="deleteBrand(\${b.id})"><svg data-feather="trash-2"></svg></button>
      </div></td>
    </tr>`;
  }).join('');
  feather.replace({width:13,height:13});
}

function escHtml(s){ const d=document.createElement('div');d.textContent=s;return d.innerHTML; }

// ── Available locales (loaded once) ──────────────────
const LOCALES = ['ar','en'];

function buildTransFields(trans){
  const c = document.getElementById('transFields');
  c.innerHTML = '';
  LOCALES.forEach(function(lang){
    const t = (trans||[]).find(function(x){return x.locale===lang;})||{};
    const row = document.createElement('div');
    row.className='form-group';
    row.innerHTML = `<label class="form-label"><?= t('form.brand_name') ?> [\${lang.toUpperCase()}]</label>
      <input type="text" class="form-control" data-lang="\${lang}" data-field="name" placeholder="Brand name (\${lang})" value="\${escHtml(t.name||'')}">
      <input type="text" class="form-control" style="margin-top:.4rem" data-lang="\${lang}" data-field="description" placeholder="Description (\${lang})" value="\${escHtml(t.description||'')}">`;
    c.appendChild(row);
  });
}

// ── Add brand ─────────────────────────────────────────
document.getElementById('btnAddBrand').addEventListener('click', function(){
  document.getElementById('brandId').value='';
  document.getElementById('brandSlug').value='';
  document.getElementById('brandWebsite').value='';
  document.getElementById('brandSort').value='0';
  document.getElementById('brandActive').checked=true;
  document.getElementById('brandModalTitle').textContent='<?= t('btn.add') ?>';
  document.getElementById('brandError').style.display='none';
  buildTransFields([]);
  openModal('brandModal');
});

// ── Edit brand ────────────────────────────────────────
window.editBrand = async function(id){
  const r = await fetch(API+'/brands/'+id, {headers:hdr()});
  const d = await r.json();
  const b = d.data || d.brand || d;
  document.getElementById('brandId').value=b.id;
  document.getElementById('brandSlug').value=b.slug||'';
  document.getElementById('brandWebsite').value=b.website||'';
  document.getElementById('brandSort').value=b.sort_order||0;
  document.getElementById('brandActive').checked=!!b.is_active;
  document.getElementById('brandModalTitle').textContent='<?= t('btn.edit') ?>';
  document.getElementById('brandError').style.display='none';
  buildTransFields(b.translations||[]);
  openModal('brandModal');
};

// ── Save brand ────────────────────────────────────────
document.getElementById('btnSaveBrand').addEventListener('click', async function(){
  const id = document.getElementById('brandId').value;
  const translations = [];
  document.querySelectorAll('#transFields input').forEach(function(el){
    const lang=el.dataset.lang, field=el.dataset.field, val=el.value;
    let obj = translations.find(function(t){return t.locale===lang;});
    if(!obj){ obj={locale:lang}; translations.push(obj); }
    obj[field]=val;
  });
  const body = {
    slug:       document.getElementById('brandSlug').value,
    website:    document.getElementById('brandWebsite').value||null,
    sort_order: parseInt(document.getElementById('brandSort').value)||0,
    is_active:  document.getElementById('brandActive').checked?1:0,
    translations,
  };
  const method = id ? 'PUT' : 'POST';
  const url    = id ? API+'/brands/'+id : API+'/brands';
  const r = await fetch(url, {method, headers:hdr(), body:JSON.stringify(body)});
  const d = await r.json();
  if(!r.ok){
    const e=document.getElementById('brandError');
    e.textContent=d.message||'<?= t('msg.error') ?>';
    e.style.display='block'; return;
  }
  closeModal('brandModal');
  showAlert('<?= t('msg.saved') ?>', 'success');
  loadBrands();
});

// ── Delete brand ──────────────────────────────────────
let _delId=null;
window.deleteBrand=function(id){ _delId=id; openModal('delBrandModal'); };
document.getElementById('btnConfirmDelBrand').addEventListener('click', async function(){
  await fetch(API+'/brands/'+_delId,{method:'DELETE',headers:hdr()});
  closeModal('delBrandModal');
  showAlert('<?= t('msg.deleted') ?>', 'success');
  loadBrands();
});

function showAlert(msg, type){
  const el=document.getElementById('alertBox');
  el.innerHTML='<div class="alert alert-'+type+'">'+msg+'</div>';
  el.style.display='block';
  setTimeout(function(){el.style.display='none';}, 3000);
}

document.getElementById('btnRefresh').addEventListener('click', loadBrands);
document.getElementById('brandSearch').addEventListener('input', loadBrands);

loadBrands();
})();
</script>
JS;
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
