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

    sigma_add_body_class('register');
    osc_enqueue_script('jquery-validate');
    osc_current_web_theme_path('header.php') ;
?>
<div class="at-container at-form-page at-form-narrow">
  <h1 class="at-form-title">Creá tu cuenta</h1>
  <p class="at-form-intro">Con una cuenta publicás y editás tus alojamientos. Es gratis.</p>

  <form name="register" class="at-form" action="<?php echo osc_base_url(true); ?>" method="post">
    <input type="hidden" name="page" value="register" />
    <input type="hidden" name="action" value="register_post" />

    <?php osc_run_hook('user_pre_register_form'); ?>

    <ul id="error_list"></ul>
    <div class="at-field">
      <label for="s_name">Nombre</label>
      <?php UserForm::name_text(); ?>
    </div>
    <div class="at-field">
      <label for="s_email">Correo</label>
      <?php UserForm::email_text(); ?>
    </div>
    <div class="at-field">
      <label for="s_password">Contraseña</label>
      <?php UserForm::password_text(); ?>
    </div>
    <div class="at-field">
      <label for="s_password2">Repetí la contraseña</label>
      <?php UserForm::check_password_text(); ?>
      <p id="password-error" class="at-error" style="display:none;">Las contraseñas no coinciden.</p>
    </div>

    <?php osc_run_hook('user_register_form'); ?>
    <div class="at-recaptcha"><?php osc_show_recaptcha('register'); ?></div>

    <div class="at-form-actions">
      <button type="submit" class="at-btn at-btn-primary">Crear cuenta</button>
      <p class="at-form-alt">¿Ya tenés cuenta? <a href="<?php echo osc_user_login_url(); ?>">Ingresá</a></p>
    </div>
  </form>
</div>
<?php UserForm::js_validation(); ?>
<?php osc_current_web_theme_path('footer.php') ; ?>
