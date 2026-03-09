<?php
/**
 * TORO Admin — pages/images.php
 * Media Studio — browse, upload, delete, set-main images via API
 */
declare(strict_types=1);

$ADMIN_PAGE  = 'images';
$ADMIN_TITLE = 'الصور والوسائط';

require_once __DIR__ . '/../includes/header.php';

// API base (same origin)
$apiBase = '/toro/api/v1';
?>

<div class="page-header">
  <h1><?= t('nav.images') ?></h1>
  <button class="btn btn-primary" id="btnOpenUpload">
    <svg data-feather="upload-cloud"></svg>
    <?= t('btn.upload') ?>
  </button>
</div>

<!-- Filter bar -->
<div class="card" style="padding:1rem 1.25rem">
  <div class="d-flex gap-3 align-center" style="flex-wrap:wrap">
    <select class="form-control" id="filterOwnerType" style="width:180px">
      <option value=""><?= t('label.all') ?> — <?= t('label.owner_type') ?></option>
      <option value="product">product</option>
      <option value="brand">brand</option>
      <option value="category">category</option>
      <option value="banner">banner</option>
    </select>
    <input type="number" class="form-control" id="filterOwnerId" placeholder="<?= t('label.owner_id') ?>" style="width:140px">
    <button class="btn btn-outline" id="btnFilter">
      <svg data-feather="filter"></svg> <?= t('btn.filter') ?>
    </button>
    <button class="btn btn-outline" id="btnRefresh">
      <svg data-feather="refresh-cw"></svg> <?= t('btn.refresh') ?>
    </button>
  </div>
</div>

<!-- Image Grid -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= t('nav.images') ?></span>
    <span class="text-muted" id="imgCount">—</span>
  </div>
  <div id="imgGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem"></div>
  <div id="imgEmpty" class="text-muted" style="display:none;padding:2rem;text-align:center"><?= t('label.no_data') ?></div>
  <div id="imgLoading" style="padding:2rem;text-align:center;color:var(--clr-muted)">
    <span class="spinner"></span>
  </div>
</div>

<!-- ── Upload Modal ──────────────────────────────────────── -->
<div class="modal-backdrop" id="uploadModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><?= t('btn.upload') ?></span>
      <button class="btn-icon" onclick="closeModal('uploadModal')"><svg data-feather="x"></svg></button>
    </div>

    <div class="form-group">
      <label class="form-label"><?= t('label.owner_type') ?></label>
      <select class="form-control" id="upOwnerType">
        <option value="product">product</option>
        <option value="brand">brand</option>
        <option value="category">category</option>
        <option value="banner">banner</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label"><?= t('label.owner_id') ?></label>
      <input type="number" class="form-control" id="upOwnerId" min="1">
    </div>
    <div class="form-group">
      <label class="form-label"><?= t('label.image_alt') ?></label>
      <input type="text" class="form-control" id="upAlt" placeholder="alt text">
    </div>
    <div class="form-group">
      <label class="form-label"><?= t('label.image_type') ?></label>
      <select class="form-control" id="upType">
        <option value="main">main</option>
        <option value="gallery">gallery</option>
        <option value="thumbnail">thumbnail</option>
      </select>
    </div>

    <!-- Drop zone -->
    <div class="drop-zone" id="dropZone">
      <svg data-feather="upload-cloud" style="width:36px;height:36px;margin-bottom:.5rem"></svg>
      <p><?= t('btn.upload') ?></p>
      <p class="text-muted" style="font-size:.8125rem">اسحب الملف هنا أو انقر للاختيار</p>
      <input type="file" id="fileInput" accept="image/*" style="display:none" multiple>
    </div>
    <div id="upPreview" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem"></div>

    <div id="upError" class="alert alert-danger" style="display:none"></div>
    <div id="upSuccess" class="alert alert-success" style="display:none"></div>

    <div class="d-flex gap-2" style="margin-top:1rem">
      <button class="btn btn-primary" id="btnDoUpload"><?= t('btn.upload') ?></button>
      <button class="btn btn-outline" onclick="closeModal('uploadModal')"><?= t('btn.cancel') ?></button>
    </div>
  </div>
</div>

<!-- ── Delete confirm modal ──────────────────────────────── -->
<div class="modal-backdrop" id="deleteModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <span class="modal-title"><?= t('btn.delete') ?></span>
      <button class="btn-icon" onclick="closeModal('deleteModal')"><svg data-feather="x"></svg></button>
    </div>
    <p style="margin-bottom:1.25rem"><?= t('msg.confirm_delete') ?></p>
    <div class="d-flex gap-2">
      <button class="btn btn-danger" id="btnConfirmDelete"><?= t('btn.confirm') ?></button>
      <button class="btn btn-outline" onclick="closeModal('deleteModal')"><?= t('btn.cancel') ?></button>
    </div>
  </div>
</div>

<?php
$ADMIN_EXTRA_JS = <<<JS
<script>
(function(){
  const API = '<?= $apiBase ?>';
  const token = sessionStorage.getItem('admin_token') || '';

  // helpers
  function openModal(id){ document.getElementById(id).classList.add('open'); feather.replace({width:16,height:16}); }
  function closeModal(id){ document.getElementById(id).classList.remove('open'); }
  window.closeModal = closeModal;

  function authHdr(){
    const h = {'Content-Type':'application/json'};
    if(token) h['Authorization']='Bearer '+token;
    return h;
  }

  // ── Load images ─────────────────────────────────────
  async function loadImages(){
    document.getElementById('imgLoading').style.display='block';
    document.getElementById('imgGrid').innerHTML='';
    document.getElementById('imgEmpty').style.display='none';

    let url = API+'/images';
    const ot = document.getElementById('filterOwnerType').value;
    const oid = document.getElementById('filterOwnerId').value;
    const params = new URLSearchParams();
    if(ot) params.set('owner_type', ot);
    if(oid) params.set('owner_id', oid);
    if(params.toString()) url += '?'+params.toString();

    try {
      const r = await fetch(url, {headers: authHdr()});
      const d = await r.json();
      document.getElementById('imgLoading').style.display='none';
      const imgs = d.data || d.images || d || [];
      document.getElementById('imgCount').textContent = imgs.length+' صورة';
      if(!imgs.length){ document.getElementById('imgEmpty').style.display='block'; return; }
      renderGrid(imgs);
    } catch(e){
      document.getElementById('imgLoading').style.display='none';
      document.getElementById('imgEmpty').style.display='block';
      console.error(e);
    }
  }

  function renderGrid(imgs){
    const grid = document.getElementById('imgGrid');
    grid.innerHTML = '';
    imgs.forEach(function(img){
      const card = document.createElement('div');
      card.style.cssText='border:1px solid var(--clr-border);border-radius:var(--radius);overflow:hidden;background:var(--clr-bg);';
      const src = img.url || img.image_url || img.path || '';
      const isMain = img.is_main == 1 || img.is_main === true;
      card.innerHTML = `
        <div style="position:relative">
          <img src="\${src}" alt="\${img.alt_text||''}" loading="lazy" style="width:100%;height:130px;object-fit:cover;display:block">
          \${isMain?'<span class="badge badge-success" style="position:absolute;top:6px;\${document.documentElement.dir==="rtl"?"right":"left"}:6px">رئيسية</span>':''}
        </div>
        <div style="padding:.625rem .75rem">
          <p style="font-size:.75rem;color:var(--clr-muted);margin-bottom:.5rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">\${img.alt_text||img.id}</p>
          <div style="display:flex;gap:.35rem;flex-wrap:wrap">
            \${!isMain?`<button class="btn btn-outline btn-xs" onclick="setMain(\${img.id})"><?= t('btn.set_main') ?></button>`:''}
            <button class="btn btn-danger btn-xs" onclick="confirmDelete(\${img.id})"><svg data-feather="trash-2"></svg></button>
          </div>
        </div>`;
      grid.appendChild(card);
    });
    feather.replace({width:12,height:12});
  }

  // ── Set main ─────────────────────────────────────────
  window.setMain = async function(id){
    await fetch(API+'/images/'+id+'/set-main', {method:'PATCH', headers: authHdr()});
    loadImages();
  };

  // ── Delete ────────────────────────────────────────────
  let _delId = null;
  window.confirmDelete = function(id){ _delId=id; openModal('deleteModal'); };
  document.getElementById('btnConfirmDelete').addEventListener('click', async function(){
    if(!_delId) return;
    await fetch(API+'/images/'+_delId, {method:'DELETE', headers: authHdr()});
    closeModal('deleteModal');
    loadImages();
  });

  // ── Upload ────────────────────────────────────────────
  document.getElementById('btnOpenUpload').addEventListener('click', function(){ openModal('uploadModal'); });

  const dropZone = document.getElementById('dropZone');
  const fileInput = document.getElementById('fileInput');
  let _files = [];

  dropZone.addEventListener('click', function(){ fileInput.click(); });
  dropZone.addEventListener('dragover', function(e){ e.preventDefault(); dropZone.classList.add('over'); });
  dropZone.addEventListener('dragleave', function(){ dropZone.classList.remove('over'); });
  dropZone.addEventListener('drop', function(e){ e.preventDefault(); dropZone.classList.remove('over'); handleFiles(e.dataTransfer.files); });
  fileInput.addEventListener('change', function(){ handleFiles(fileInput.files); });

  function handleFiles(files){
    _files = Array.from(files);
    const prev = document.getElementById('upPreview');
    prev.innerHTML = '';
    _files.forEach(function(f){
      const url = URL.createObjectURL(f);
      const img = document.createElement('img');
      img.src=url; img.style.cssText='width:72px;height:72px;object-fit:cover;border-radius:6px;border:1px solid var(--clr-border)';
      prev.appendChild(img);
    });
  }

  document.getElementById('btnDoUpload').addEventListener('click', async function(){
    if(!_files.length){ showErr('اختر ملفاً'); return; }
    const ownType = document.getElementById('upOwnerType').value;
    const ownId   = document.getElementById('upOwnerId').value;
    const alt     = document.getElementById('upAlt').value;
    const imgType = document.getElementById('upType').value;

    document.getElementById('btnDoUpload').disabled=true;
    document.getElementById('upError').style.display='none';
    document.getElementById('upSuccess').style.display='none';

    let ok=0, fail=0;
    for(const file of _files){
      const fd = new FormData();
      fd.append('file', file);
      if(ownType) fd.append('owner_type', ownType);
      if(ownId)   fd.append('owner_id', ownId);
      if(alt)     fd.append('alt_text', alt);
      if(imgType) fd.append('image_type', imgType);
      const h = {};
      if(token) h['Authorization']='Bearer '+token;
      try {
        const r = await fetch(API+'/images/upload', {method:'POST', headers:h, body:fd});
        r.ok ? ok++ : fail++;
      } catch(e){ fail++; }
    }
    document.getElementById('btnDoUpload').disabled=false;
    if(ok){
      document.getElementById('upSuccess').textContent='تم رفع '+ok+' صورة';
      document.getElementById('upSuccess').style.display='block';
      setTimeout(function(){ closeModal('uploadModal'); loadImages(); },1200);
    }
    if(fail){ showErr('فشل رفع '+fail+' ملف'); }
  });

  function showErr(msg){ const el=document.getElementById('upError'); el.textContent=msg; el.style.display='block'; }

  // filters
  document.getElementById('btnFilter').addEventListener('click', loadImages);
  document.getElementById('btnRefresh').addEventListener('click', loadImages);

  loadImages();
})();
</script>
JS;
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
