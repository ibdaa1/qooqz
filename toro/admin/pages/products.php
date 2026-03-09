<?php
/**
 * TORO Admin — pages/products.php
 * Full CRUD for products via API
 */
declare(strict_types=1);

$ADMIN_PAGE  = 'products';
$ADMIN_TITLE = 'المنتجات';

require_once __DIR__ . '/../includes/header.php';

$apiBase = '/toro/api/v1';
?>

<div class="page-header">
  <h1><?= t('nav.products') ?></h1>
  <button class="btn btn-primary" id="btnAddProduct">
    <svg data-feather="plus"></svg> <?= t('btn.add') ?>
  </button>
</div>

<div id="alertBox" style="display:none"></div>

<!-- Filters -->
<div class="card" style="padding:1rem 1.25rem">
  <div class="d-flex gap-3 align-center" style="flex-wrap:wrap">
    <input type="search" class="form-control" id="prodSearch" placeholder="<?= t('search_placeholder') ?>" style="width:220px">
    <select class="form-control" id="prodCategory" style="width:180px">
      <option value=""><?= t('label.all') ?> — <?= t('table.category') ?></option>
    </select>
    <select class="form-control" id="prodBrand" style="width:180px">
      <option value=""><?= t('label.all') ?> — <?= t('table.brand') ?></option>
    </select>
    <select class="form-control" id="prodStatus" style="width:150px">
      <option value=""><?= t('label.all') ?></option>
      <option value="1"><?= t('label.active') ?></option>
      <option value="0"><?= t('label.inactive') ?></option>
    </select>
    <button class="btn btn-outline btn-sm" id="btnRefresh"><svg data-feather="refresh-cw"></svg></button>
  </div>
</div>

<!-- Products table -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= t('nav.products') ?></span>
    <span class="text-muted" id="prodCount">—</span>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th><?= t('table.id') ?></th>
          <th>—</th>
          <th><?= t('table.name') ?></th>
          <th>SKU</th>
          <th><?= t('table.type') ?></th>
          <th><?= t('table.brand') ?></th>
          <th><?= t('table.category') ?></th>
          <th><?= t('table.price') ?></th>
          <th><?= t('table.stock') ?></th>
          <th><?= t('table.status') ?></th>
          <th><?= t('table.actions') ?></th>
        </tr>
      </thead>
      <tbody id="prodTbody">
        <tr><td colspan="11" style="text-align:center;padding:2rem;color:var(--clr-muted)"><span class="spinner"></span></td></tr>
      </tbody>
    </table>
  </div>
  <!-- Pagination -->
  <div class="d-flex gap-2 align-center" style="padding:1rem 1.25rem;border-top:1px solid var(--clr-border)">
    <button class="btn btn-outline btn-sm" id="btnPrev"><?= t('pagination.previous') ?></button>
    <span class="text-muted" id="pageInfo"></span>
    <button class="btn btn-outline btn-sm" id="btnNext"><?= t('pagination.next') ?></button>
  </div>
</div>

<!-- ── Product Modal ───────────────────────────────────────── -->
<div class="modal-backdrop" id="productModal">
  <div class="modal" style="max-width:740px">
    <div class="modal-header">
      <span class="modal-title" id="productModalTitle"><?= t('btn.add') ?></span>
      <button class="btn-icon" onclick="closeModal('productModal')"><svg data-feather="x"></svg></button>
    </div>
    <input type="hidden" id="productId">

    <div class="form-grid-2">
      <div class="form-group">
        <label class="form-label"><?= t('form.product_sku') ?></label>
        <input type="text" class="form-control" id="pSku" placeholder="SKU-001">
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('form.product_type') ?></label>
        <select class="form-control" id="pType">
          <option value="simple">simple</option>
          <option value="variable">variable</option>
          <option value="digital">digital</option>
        </select>
      </div>
    </div>

    <div class="form-grid-2">
      <div class="form-group">
        <label class="form-label"><?= t('form.product_brand') ?></label>
        <select class="form-control" id="pBrand"></select>
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('form.product_category') ?></label>
        <select class="form-control" id="pCategory"></select>
      </div>
    </div>

    <div class="form-grid-3">
      <div class="form-group">
        <label class="form-label"><?= t('form.product_base_price') ?></label>
        <input type="number" class="form-control" id="pBasePrice" step="0.01" min="0" value="0">
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('form.product_sale_price') ?></label>
        <input type="number" class="form-control" id="pSalePrice" step="0.01" min="0" value="0">
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('form.product_stock') ?></label>
        <input type="number" class="form-control" id="pStock" min="0" value="0">
      </div>
    </div>

    <div class="form-group">
      <label class="form-check">
        <input type="checkbox" id="pActive" checked>
        <?= t('form.product_active') ?>
      </label>
    </div>

    <hr style="border-color:var(--clr-border);margin:.75rem 0">
    <p class="form-label"><?= t('form.product_translations') ?></p>
    <div id="prodTransFields"></div>

    <div id="productError" class="alert alert-danger" style="display:none"></div>
    <div class="d-flex gap-2" style="margin-top:1rem">
      <button class="btn btn-primary" id="btnSaveProduct"><?= t('btn.save') ?></button>
      <button class="btn btn-outline" onclick="closeModal('productModal')"><?= t('btn.cancel') ?></button>
    </div>
  </div>
</div>

<!-- Delete confirm -->
<div class="modal-backdrop" id="delProductModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <span class="modal-title"><?= t('btn.delete') ?></span>
      <button class="btn-icon" onclick="closeModal('delProductModal')"><svg data-feather="x"></svg></button>
    </div>
    <p style="margin-bottom:1.25rem"><?= t('msg.confirm_delete') ?></p>
    <div class="d-flex gap-2">
      <button class="btn btn-danger" id="btnConfirmDelProd"><?= t('btn.confirm') ?></button>
      <button class="btn btn-outline" onclick="closeModal('delProductModal')"><?= t('btn.cancel') ?></button>
    </div>
  </div>
</div>

<?php
$ADMIN_EXTRA_JS = <<<JS
<script>
(function(){
const API = '<?= $apiBase ?>';
const token = sessionStorage.getItem('admin_token') || '';
function hdr(){const h={'Content-Type':'application/json'};if(token)h['Authorization']='Bearer '+token;return h;}
window.closeModal = window.closeModal || function(id){document.getElementById(id).classList.remove('open');};
function openModal(id){document.getElementById(id).classList.add('open');feather.replace({width:16,height:16});}

// ── Pagination state ──────────────────────────────────
let page=1, totalPages=1;
const PER_PAGE=20;

// ── Load helpers ──────────────────────────────────────
async function loadBrandOptions(){
  const r = await fetch(API+'/brands?per_page=200',{headers:hdr()});
  const d = await r.json();
  const brands = d.data||d.brands||[];
  ['pBrand','prodBrand'].forEach(function(sel){
    const el=document.getElementById(sel);
    if(!el) return;
    el.innerHTML='<option value="">—</option>';
    brands.forEach(function(b){
      const name=(b.translations&&b.translations[0])?b.translations[0].name:b.slug;
      el.innerHTML+=`<option value="\${b.id}">\${name}</option>`;
    });
  });
}

async function loadCategoryOptions(){
  const r = await fetch(API+'/categories?per_page=200',{headers:hdr()});
  const d = await r.json();
  const cats = d.data||d.categories||[];
  ['pCategory','prodCategory'].forEach(function(sel){
    const el=document.getElementById(sel);
    if(!el) return;
    el.innerHTML='<option value="">—</option>';
    cats.forEach(function(c){
      const name=(c.translations&&c.translations[0])?c.translations[0].name:c.slug;
      el.innerHTML+=`<option value="\${c.id}">\${name}</option>`;
    });
  });
}

// ── Load products ─────────────────────────────────────
async function loadProducts(){
  document.getElementById('prodTbody').innerHTML='<tr><td colspan="11" style="text-align:center;padding:2rem;color:var(--clr-muted)"><span class="spinner"></span></td></tr>';
  const params=new URLSearchParams({page,per_page:PER_PAGE});
  const q=document.getElementById('prodSearch').value;
  const cat=document.getElementById('prodCategory').value;
  const brand=document.getElementById('prodBrand').value;
  const status=document.getElementById('prodStatus').value;
  if(q) params.set('search',q);
  if(cat) params.set('category_id',cat);
  if(brand) params.set('brand_id',brand);
  if(status!=='') params.set('is_active',status);
  try {
    const r=await fetch(API+'/products?'+params,{headers:hdr()});
    const d=await r.json();
    const prods=d.data||d.products||[];
    totalPages=d.last_page||d.pages||1;
    document.getElementById('prodCount').textContent=prods.length+' منتج';
    document.getElementById('pageInfo').textContent='صفحة '+page+' / '+totalPages;
    document.getElementById('btnPrev').disabled=page<=1;
    document.getElementById('btnNext').disabled=page>=totalPages;
    renderProducts(prods);
  } catch(e){
    document.getElementById('prodTbody').innerHTML='<tr><td colspan="11" style="text-align:center;padding:2rem;color:var(--clr-danger)"><?= t('msg.error') ?></td></tr>';
  }
}

function renderProducts(prods){
  const tb=document.getElementById('prodTbody');
  if(!prods.length){
    tb.innerHTML='<tr><td colspan="11" style="text-align:center;padding:2rem;color:var(--clr-muted)"><?= t('label.no_data') ?></td></tr>';
    return;
  }
  tb.innerHTML=prods.map(function(p){
    const name=(p.translations&&p.translations[0])?p.translations[0].name:(p.sku||p.id);
    const mainImg=(p.images&&p.images[0])?p.images[0].url:'';
    return `<tr>
      <td>\${p.id}</td>
      <td>\${mainImg?'<img src="'+mainImg+'" class="img-thumb">':''}</td>
      <td>\${escHtml(name)}</td>
      <td><code style="font-size:.75rem">\${escHtml(p.sku||'')}</code></td>
      <td>\${escHtml(p.type||'')}</td>
      <td>\${p.brand_id||'—'}</td>
      <td>\${p.category_id||'—'}</td>
      <td>\${p.base_price||0}</td>
      <td>\${p.stock_qty||0}</td>
      <td>\${p.is_active?'<span class="badge badge-success"><?= t('label.active') ?></span>':'<span class="badge badge-danger"><?= t('label.inactive') ?></span>'}</td>
      <td><div class="table-actions">
        <button class="btn btn-outline btn-xs" onclick="editProduct(\${p.id})"><svg data-feather="edit-2"></svg></button>
        <button class="btn btn-danger btn-xs" onclick="deleteProduct(\${p.id})"><svg data-feather="trash-2"></svg></button>
      </div></td>
    </tr>`;
  }).join('');
  feather.replace({width:13,height:13});
}

function escHtml(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

// ── Translation fields ────────────────────────────────
const LOCALES=['ar','en'];
function buildProdTransFields(trans){
  const c=document.getElementById('prodTransFields');c.innerHTML='';
  LOCALES.forEach(function(lang){
    const t=(trans||[]).find(function(x){return x.locale===lang;})||{};
    const div=document.createElement('div');div.className='form-group';
    div.innerHTML=`<label class="form-label"><?= t('form.product_name') ?> [\${lang.toUpperCase()}]</label>
      <input type="text" class="form-control" data-lang="\${lang}" data-field="name" placeholder="\${lang}" value="\${escHtml(t.name||'')}">
      <textarea class="form-control" style="margin-top:.4rem" data-lang="\${lang}" data-field="description" placeholder="Description (\${lang})">\${escHtml(t.description||'')}</textarea>`;
    c.appendChild(div);
  });
}

// ── Add ───────────────────────────────────────────────
document.getElementById('btnAddProduct').addEventListener('click', function(){
  document.getElementById('productId').value='';
  document.getElementById('pSku').value='';
  document.getElementById('pType').value='simple';
  document.getElementById('pBasePrice').value='0';
  document.getElementById('pSalePrice').value='0';
  document.getElementById('pStock').value='0';
  document.getElementById('pActive').checked=true;
  document.getElementById('productModalTitle').textContent='<?= t('btn.add') ?>';
  document.getElementById('productError').style.display='none';
  buildProdTransFields([]);
  openModal('productModal');
});

// ── Edit ──────────────────────────────────────────────
window.editProduct=async function(id){
  const r=await fetch(API+'/products/'+id,{headers:hdr()});
  const d=await r.json();
  const p=d.data||d.product||d;
  document.getElementById('productId').value=p.id;
  document.getElementById('pSku').value=p.sku||'';
  document.getElementById('pType').value=p.type||'simple';
  document.getElementById('pBasePrice').value=p.base_price||0;
  document.getElementById('pSalePrice').value=p.sale_price||0;
  document.getElementById('pStock').value=p.stock_qty||0;
  document.getElementById('pActive').checked=!!p.is_active;
  document.getElementById('pBrand').value=p.brand_id||'';
  document.getElementById('pCategory').value=p.category_id||'';
  document.getElementById('productModalTitle').textContent='<?= t('btn.edit') ?>';
  document.getElementById('productError').style.display='none';
  buildProdTransFields(p.translations||[]);
  openModal('productModal');
};

// ── Save ──────────────────────────────────────────────
document.getElementById('btnSaveProduct').addEventListener('click', async function(){
  const id=document.getElementById('productId').value;
  const translations=[];
  document.querySelectorAll('#prodTransFields [data-lang]').forEach(function(el){
    const lang=el.dataset.lang,field=el.dataset.field,val=el.value;
    let obj=translations.find(function(t){return t.locale===lang;});
    if(!obj){obj={locale:lang};translations.push(obj);}
    obj[field]=val;
  });
  const body={
    sku:document.getElementById('pSku').value,
    type:document.getElementById('pType').value,
    brand_id:parseInt(document.getElementById('pBrand').value)||null,
    category_id:parseInt(document.getElementById('pCategory').value)||null,
    base_price:parseFloat(document.getElementById('pBasePrice').value)||0,
    sale_price:parseFloat(document.getElementById('pSalePrice').value)||0,
    stock_qty:parseInt(document.getElementById('pStock').value)||0,
    is_active:document.getElementById('pActive').checked?1:0,
    translations,
  };
  const method=id?'PUT':'POST';
  const url=id?API+'/products/'+id:API+'/products';
  const r=await fetch(url,{method,headers:hdr(),body:JSON.stringify(body)});
  const d=await r.json();
  if(!r.ok){
    const e=document.getElementById('productError');
    e.textContent=d.message||'<?= t('msg.error') ?>';e.style.display='block';return;
  }
  closeModal('productModal');showAlert('<?= t('msg.saved') ?>','success');loadProducts();
});

// ── Delete ────────────────────────────────────────────
let _delId=null;
window.deleteProduct=function(id){_delId=id;document.getElementById('delProductModal').classList.add('open');feather.replace({width:16,height:16});};
document.getElementById('btnConfirmDelProd').addEventListener('click',async function(){
  await fetch(API+'/products/'+_delId,{method:'DELETE',headers:hdr()});
  closeModal('delProductModal');showAlert('<?= t('msg.deleted') ?>','success');loadProducts();
});

// Pagination
document.getElementById('btnPrev').addEventListener('click',function(){if(page>1){page--;loadProducts();}});
document.getElementById('btnNext').addEventListener('click',function(){if(page<totalPages){page++;loadProducts();}});

function showAlert(msg,type){
  const el=document.getElementById('alertBox');
  el.innerHTML='<div class="alert alert-'+type+'">'+msg+'</div>';
  el.style.display='block';
  setTimeout(function(){el.style.display='none';},3000);
}

// Search & filters
['prodSearch','prodCategory','prodBrand','prodStatus'].forEach(function(id){
  const el=document.getElementById(id);
  if(el) el.addEventListener('change',function(){page=1;loadProducts();});
});
document.getElementById('prodSearch').addEventListener('input',function(){page=1;loadProducts();});
document.getElementById('btnRefresh').addEventListener('click',function(){page=1;loadProducts();});

// Init
Promise.all([loadBrandOptions(), loadCategoryOptions()]).then(loadProducts);
})();
</script>
JS;
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
