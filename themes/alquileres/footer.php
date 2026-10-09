<?php
/*
 * Copyright 2014 Osclass
 * Copyright 2026 Osclass by OsclassPoint.com
 *
 * Osclass maintained & developed by OsclassPoint.com
 * You may not use this file except in compliance with the License.
 * You may download copy of Osclass at
 *
 *     https://osclass-classifieds.com/download
 *
 * Do not edit or add to this file if you wish to upgrade Osclass to newer
 * versions in the future. Software is distributed on an "AS IS" basis, without
 * warranties or conditions of any kind, either express or implied. Do not remove
 * this NOTICE section as it contains license information and copyrights.
 */

?>
</div>
<?php osc_run_hook('after-main'); ?>
</div>
</section>

<?php osc_show_widgets('footer');?>

<?php osc_run_hook('footer_pre'); ?>

<footer class="at-footer">
  <?php osc_run_hook('footer_top'); ?>
  <div class="at-container">
    <div class="at-footer-notice">
      <?php
        // tourist-identity prints the showcase notice on the `footer` hook; other footer scripts
        // (structured data, plugin JS) share the hook and render nothing visible.
        osc_run_hook('footer');
      ?>
    </div>
    <nav aria-label="Principal">
      <a href="<?php echo osc_base_url(); ?>#destinos">Destinos</a>
      <a href="<?php echo osc_base_url(); ?>#como-funciona">Cómo funciona</a>
      <?php if(osc_users_enabled()) { ?>
        <?php if(osc_is_web_user_logged_in()) { ?>
          <a href="<?php echo osc_user_dashboard_url(); ?>">Mi cuenta</a>
          <a href="<?php echo osc_user_logout_url(); ?>">Salir</a>
        <?php } else { ?>
          <a href="<?php echo osc_user_login_url(); ?>">Ingresar</a>
        <?php } ?>
      <?php } ?>
      <a class="at-btn at-btn-secondary" href="<?php echo osc_item_post_url_in_category(); ?>">Publicar alojamiento</a>
    </nav>
  </div>
</footer>

<?php osc_run_hook('footer_after'); ?>

<script>
  // jQuery UI's default close label is English; the site speaks Spanish.
  if (window.jQuery && jQuery.ui && jQuery.ui.dialog) { jQuery.ui.dialog.prototype.options.closeText = 'Cerrar'; }

  // Uppy (core uploader for listing and profile photos) renders unlabeled file inputs.
  (function () {
    function nameUploadInputs() {
      document.querySelectorAll('.uppy-Dashboard-input:not([aria-label])').forEach(function (el) {
        el.setAttribute('aria-label', 'Elegir fotos');
      });
    }
    nameUploadInputs();
    new MutationObserver(nameUploadInputs).observe(document.body, { childList: true, subtree: true });
  })();
</script>

<link href="<?php echo osc_assets_url('css/jquery-ui/jquery-ui.css'); ?>" rel="stylesheet" type="text/css" />

</body>
</html>