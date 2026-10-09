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


// meta tag robots
osc_add_hook('header','sigma_nofollow_construct');

sigma_add_body_class('contact');
osc_enqueue_script('jquery-validate');
osc_current_web_theme_path('header.php');
?>
<div class="at-container at-form-page at-form-narrow">
  <h1 class="at-form-title">Escribinos</h1>
  <p class="at-form-intro">Para consultas sobre el sitio, pedidos de baja o errores en una ficha. Para reservar o consultar disponibilidad, escribile directamente al alojamiento.</p>

  <ul id="error_list"></ul>
  <form name="contact_form" class="at-form" action="<?php echo osc_base_url(true); ?>" method="post" <?php if(osc_contact_attachment()) { ?>enctype="multipart/form-data"<?php } ?>>
    <input type="hidden" name="page" value="contact" />
    <input type="hidden" name="action" value="contact_post" />

    <div class="at-field">
      <label for="yourName">Tu nombre <span class="at-optional">(opcional)</span></label>
      <?php ContactForm::your_name(); ?>
    </div>
    <div class="at-field">
      <label for="yourEmail">Tu correo</label>
      <?php ContactForm::your_email(); ?>
      <p class="at-help">Te respondemos a esta dirección.</p>
    </div>
    <div class="at-field">
      <label for="subject">Asunto <span class="at-optional">(opcional)</span></label>
      <?php ContactForm::the_subject(); ?>
    </div>
    <div class="at-field">
      <label for="message">Mensaje</label>
      <?php ContactForm::your_message(); ?>
    </div>
    <?php if(osc_contact_attachment()) { ?>
      <div class="at-field">
        <label for="attachment">Adjunto <span class="at-optional">(opcional)</span></label>
        <?php ContactForm::your_attachment(); ?>
      </div>
    <?php } ?>

    <div class="at-form-actions">
      <?php osc_run_hook('contact_form'); ?>
      <?php osc_show_recaptcha(); ?>
      <button type="submit" class="at-btn at-btn-primary">Enviar mensaje</button>
      <?php osc_run_hook('admin_contact_form'); ?>
    </div>
  </form>
  <?php ContactForm::js_validation(); ?>
</div>

<?php osc_current_web_theme_path('footer.php') ; ?>
