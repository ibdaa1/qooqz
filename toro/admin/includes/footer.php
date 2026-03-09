<?php
/**
 * TORO Admin — includes/footer.php
 * Closes <main> + <div.main-wrapper> opened by header.php
 */
declare(strict_types=1);

// Skip if CLI or API request
if (php_sapi_name() === 'cli') return;
$_uri = $_SERVER['REQUEST_URI'] ?? '';
if (str_starts_with($_uri, '/toro/api/')) return;
?>
  </main><!-- /.page-content -->

  <!-- Footer bar -->
  <footer style="padding:.875rem 1.75rem;border-top:1px solid var(--clr-border);display:flex;align-items:center;justify-content:space-between;font-size:.75rem;color:var(--clr-muted);">
    <span><?= htmlspecialchars($GLOBALS['_brandName'] ?? 'TORO') ?> Admin &copy; <?= date('Y') ?></span>
    <span>v1.0</span>
  </footer>

</div><!-- /.main-wrapper -->
</div><!-- /.admin-layout -->

<!-- ── Scripts ──────────────────────────────────────────────── -->
<script src="https://unpkg.com/feather-icons/dist/feather.min.js"></script>
<script>
(function(){
  // Feather icons
  if(window.feather) feather.replace({width:16,height:16});

  // Sidebar toggle
  var sidebar = document.getElementById('sidebar');
  var mainWrap = document.getElementById('mainWrapper');
  var toggle = document.getElementById('sidebarToggle');
  if(toggle && sidebar && mainWrap){
    toggle.addEventListener('click', function(){
      var w = window.innerWidth;
      if(w <= 1024){
        sidebar.classList.toggle('open');
      } else {
        sidebar.classList.toggle('collapsed');
        mainWrap.classList.toggle('sidebar-hidden');
      }
    });
  }

  // Close sidebar on outside click (mobile)
  document.addEventListener('click', function(e){
    if(window.innerWidth <= 1024 && sidebar && !sidebar.contains(e.target) && e.target !== toggle){
      sidebar.classList.remove('open');
    }
  });
})();
</script>
<?php if (isset($ADMIN_EXTRA_JS)) echo $ADMIN_EXTRA_JS; ?>
</body>
</html>
