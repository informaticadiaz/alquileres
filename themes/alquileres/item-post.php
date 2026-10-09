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

  osc_enqueue_script('jquery-validate');

  sigma_add_body_class('item item-post');
  $action = 'item_add_post';
  $edit = false;
  if(Params::getParam('action') == 'item_edit') {
    $action = 'item_edit_post';
    $edit = true;
  }

  $at_locale = osc_current_user_locale();
  $at_title_id = 'title' . $at_locale;
  $at_desc_id = 'description' . $at_locale;
  // Privacy by default: a new listing starts with email and phone hidden.
  $at_privacy_item = $edit ? null : array('b_show_email' => false, 'b_show_phone' => false);
?>

<?php osc_current_web_theme_path('header.php') ; ?>

<?php
  if (sigma_default_location_show_as() == 'dropdown') {
    ItemForm::location_javascript();
  } else {
    ItemForm::location_javascript_new();
  }

  if(osc_images_enabled_at_items())  {
    ItemForm::photos_javascript();
  }
?>

<div class="at-container at-form-page">
  <h1 class="at-form-title"><?php echo $edit ? 'Editá tu alojamiento' : 'Publicá tu alojamiento'; ?></h1>
  <?php if (!$edit) { ?>
    <p class="at-form-intro">Tu alojamiento aparece en el destino que elijas. Los huéspedes te escriben directamente; no cobramos comisión ni intervenimos en la reserva.</p>
  <?php } ?>

  <ul id="error_list"></ul>
  <form name="item" action="<?php echo osc_base_url(true);?>" method="post" enctype="multipart/form-data" id="item-post" class="at-form">
    <input type="hidden" name="action" value="<?php echo $action; ?>" />
    <input type="hidden" name="page" value="item" />
    <?php if($edit){ ?>
      <input type="hidden" name="id" value="<?php echo osc_item_id();?>" />
      <input type="hidden" name="secret" value="<?php echo osc_item_secret();?>" />
    <?php } ?>
    <?php osc_run_hook('item_publish_top'); ?>

    <section class="at-form-section" aria-labelledby="t-aloj">
      <h2 id="t-aloj">El alojamiento</h2>

      <div class="at-field">
        <label for="catId">Destino</label>
        <?php ItemForm::category_select(null, null, 'Elegí el destino'); ?>
        <p class="at-help">Elegí la ciudad, el valle o la costa donde está el alojamiento.</p>
      </div>

      <?php osc_run_hook('item_publish_category'); ?>

      <div class="at-field">
        <label for="<?php echo $at_title_id; ?>">Nombre del alojamiento</label>
        <?php ItemForm::title_input('title', $at_locale, osc_esc_html( sigma_item_title() )); ?>
        <p class="at-help">Como figura en tu cartel o en tu sitio oficial.</p>
      </div>

      <div class="at-field">
        <label for="<?php echo $at_desc_id; ?>">Descripción</label>
        <?php ItemForm::description_textarea('description', $at_locale, osc_esc_html( sigma_item_description() )); ?>
        <p class="at-help">Contá qué ofrecés: unidades, capacidad, servicios y cómo se llega.</p>
      </div>

      <?php osc_run_hook('item_publish_description'); ?>

      <div class="at-plugin-fields"><?php if($edit) { ItemForm::plugin_edit_item(); } else { ItemForm::plugin_post_item(); } ?></div>
    </section>

    <?php if( osc_images_enabled_at_items() ) { ?>
      <section class="at-form-section" aria-labelledby="t-fotos">
        <h2 id="t-fotos">Fotos</h2>
        <p class="at-help">Opcional. Sumá fotos propias del alojamiento.</p>
        <div class="at-upload"><?php ItemForm::ajax_photos(); ?></div>
        <?php osc_run_hook('item_publish_images'); ?>
      </section>
    <?php } ?>

    <section class="at-form-section" aria-labelledby="t-ubic">
      <h2 id="t-ubic">Ubicación</h2>
      <?php if(count(osc_get_countries()) > 1) { ?>
        <div class="at-field">
          <label for="countryId">País</label>
          <?php ItemForm::country_select(osc_get_countries(), osc_user()); ?>
        </div>
      <?php } else {
        // The instance may have no country configured; only send countryId when one exists.
        $aCountries = osc_get_countries();
        if (isset($aCountries[0]['pk_c_code'])) { ?>
          <input type="hidden" id="countryId" name="countryId" value="<?php echo osc_esc_html($aCountries[0]['pk_c_code']); ?>"/>
        <?php }
      } ?>

      <div class="at-field">
        <?php
          // region_select() falls back to a text input (#region) when the country has no regions.
          $at_countries = osc_get_countries();
          $at_region_select = sigma_default_location_show_as() == 'dropdown' && isset($at_countries[0]['pk_c_code']) && count(osc_get_regions($at_countries[0]['pk_c_code'])) > 0;
        ?>
        <label for="<?php echo $at_region_select ? 'regionId' : 'region'; ?>">Provincia</label>
        <?php
          $at_loc_item = $edit ? osc_item() : osc_user();
          if (sigma_default_location_show_as() == 'dropdown') {
            ItemForm::region_select(null, $at_loc_item);
          } else {
            ItemForm::region_text($at_loc_item);
          }
        ?>
      </div>

      <div class="at-field">
        <label for="<?php echo sigma_default_location_show_as() == 'dropdown' ? 'cityId' : 'city'; ?>">Localidad</label>
        <?php
          if (sigma_default_location_show_as() == 'dropdown') {
            ItemForm::city_select(null, $at_loc_item);
          } else {
            ItemForm::city_text(osc_user());
          }
        ?>
      </div>

      <div class="at-field">
        <label for="address">Dirección <span class="at-optional">(opcional)</span></label>
        <?php ItemForm::address_text(osc_user()); ?>
      </div>
      <?php osc_run_hook('item_publish_location'); ?>
    </section>

    <section class="at-form-section" aria-labelledby="t-contacto">
      <h2 id="t-contacto">Contacto</h2>
      <p class="at-help">Los huéspedes te escriben con el formulario de la ficha. Mostrar tu correo o teléfono es opcional.</p>

      <?php if(!osc_is_web_user_logged_in() ) { ?>
        <div class="at-field">
          <label for="contactName">Tu nombre</label>
          <?php ItemForm::contact_name_text(); ?>
        </div>
        <div class="at-field">
          <label for="contactEmail">Tu correo</label>
          <?php ItemForm::contact_email_text(); ?>
        </div>
        <div class="at-check">
          <?php ItemForm::show_email_checkbox($at_privacy_item); ?> <label for="showEmail">Mostrar mi correo en la ficha</label>
        </div>
      <?php } ?>

      <div class="at-field">
        <label for="contactPhone">Teléfono <span class="at-optional">(opcional)</span></label>
        <?php ItemForm::contact_phone_text(); ?>
      </div>
      <div class="at-check">
        <?php ItemForm::show_phone_checkbox($at_privacy_item); ?> <label for="showPhone">Mostrar mi teléfono en la ficha</label>
      </div>

      <div class="at-field">
        <label for="contactOther">Sitio web u otro contacto <span class="at-optional">(opcional)</span></label>
        <?php ItemForm::contact_other_text(); ?>
      </div>
      <?php osc_run_hook('item_publish_seller'); ?>
    </section>

    <?php osc_run_hook('item_publish_hook'); ?>
    <?php osc_run_hook('item_publish_bottom'); ?>

    <div class="at-form-actions">
      <?php if( osc_recaptcha_items_enabled() ) { ?><div class="at-recaptcha"><?php osc_show_recaptcha(); ?></div><?php } ?>
      <button type="submit" class="at-btn at-btn-primary"><?php echo $edit ? 'Guardar cambios' : 'Publicar alojamiento'; ?></button>
      <?php osc_run_hook('item_publish_buttons'); ?>
    </div>

    <?php osc_run_hook('item_publish_after'); ?>
  </form>
</div>
<script>
  // Uppy (core photo uploader) renders unlabeled file inputs; give them an accessible name.
  (function () {
    function nameInputs() {
      document.querySelectorAll('.uppy-Dashboard-input:not([aria-label]), .at-upload input[type=file]:not([aria-label])').forEach(function (el) {
        el.setAttribute('aria-label', 'Elegir fotos del alojamiento');
      });
    }
    nameInputs();
    new MutationObserver(nameInputs).observe(document.body, { childList: true, subtree: true });
  })();
</script>
<?php osc_current_web_theme_path('footer.php'); ?>
