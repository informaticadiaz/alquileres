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

    sigma_add_body_class('login');
    osc_current_web_theme_path('header.php');
?>
<div class="at-container at-form-page at-form-narrow">
  <h1 class="at-form-title">Ingresá a tu cuenta</h1>

  <form class="at-form" action="<?php echo osc_base_url(true); ?>" method="post">
    <input type="hidden" name="page" value="login" />
    <input type="hidden" name="action" value="login_post" />

    <?php osc_run_hook('user_pre_login_form'); ?>

    <div class="at-field">
      <label for="email">Correo</label>
      <?php UserForm::email_login_text(); ?>
    </div>
    <div class="at-field">
      <label for="password">Contraseña</label>
      <?php UserForm::password_login_text(); ?>
    </div>
    <div class="at-check">
      <?php UserForm::rememberme_login_checkbox(); ?> <label for="remember">Mantener la sesión iniciada</label>
    </div>

    <?php osc_run_hook('user_login_form'); ?>
    <div class="at-recaptcha"><?php osc_show_recaptcha('login'); ?></div>

    <div class="at-form-actions">
      <button type="submit" class="at-btn at-btn-primary">Ingresar</button>
      <p class="at-form-alt"><a href="<?php echo osc_recover_user_password_url(); ?>">¿Olvidaste tu contraseña?</a></p>
      <p class="at-form-alt">¿No tenés cuenta? <a href="<?php echo osc_register_account_url(); ?>">Creá una</a></p>
    </div>
  </form>
</div>
<?php osc_current_web_theme_path('footer.php') ; ?>
