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
  if( osc_item_is_spam() || osc_premium_is_spam() ) {
    osc_add_hook('header','sigma_nofollow_construct');
  } else {
    osc_add_hook('header','sigma_follow_construct');
  }

  osc_enqueue_script('jquery-validate');
  sigma_add_body_class('item');

  // Everything the template needs from the current item, read before related listings replace the view.
  $at_is_directory = at_item_is_directory_entry();
  $at_path = at_category_path(osc_item_category_id());
  $at_region = isset($at_path[0]) ? $at_path[0]['s_name'] : '';
  $at_token = $at_region !== '' ? at_region_token($at_region) : 'ink';
  $at_destination = count($at_path) > 1 ? at_short_name($at_path[count($at_path) - 1]['s_name']) : '';
  $at_location = at_item_location();

  $at_type = '';
  $at_facts = array();
  while (osc_has_item_meta()) {
    $value = trim((string) osc_item_meta_value());
    if ($value === '') {
      continue;
    }
    if (osc_item_meta_slug() === 'tourist_accommodation_type') {
      $at_type = $value;
    }
    $at_facts[] = array('label' => osc_item_meta_name(), 'value' => osc_item_meta_value());
  }
  if ($at_location !== '') {
    $at_facts[] = array('label' => 'Ubicación', 'value' => osc_esc_html($at_location));
  }

  osc_current_web_theme_path('header.php');
?>
<?php osc_run_hook('item_top'); ?>

<div class="at-container at-item">
  <nav class="at-breadcrumb at-breadcrumb-quiet" aria-label="Ubicación">
    <ol>
      <li><a href="<?php echo osc_base_url(); ?>#destinos">Destinos</a></li>
      <?php foreach ($at_path as $at_cat) { ?>
        <li aria-hidden="true">/</li>
        <li><a href="<?php echo osc_esc_html(at_category_search_url($at_cat['pk_i_id'])); ?>"><?php echo osc_esc_html(at_short_name($at_cat['s_name'])); ?></a></li>
      <?php } ?>
      <li aria-hidden="true">/</li>
      <li aria-current="page"><?php echo osc_item_title(); ?></li>
    </ol>
  </nav>

  <div class="at-item-grid">
    <article class="at-item-main">
      <?php if ($at_region !== '') { ?>
        <span class="at-item-kicker at-region-tag" style="color: var(--<?php echo $at_token; ?>);"><?php
          echo osc_esc_html(trim(($at_type !== '' ? $at_type : 'Alojamiento') . ($at_destination !== '' ? ' en ' . $at_destination : '')));
        ?></span>
      <?php } ?>
      <h1><?php echo osc_item_title(); ?></h1>

      <?php if (count($at_facts) > 0) { ?>
        <dl class="at-facts">
          <?php foreach ($at_facts as $fact) { ?>
            <div><dt><?php echo $fact['label']; ?></dt><dd><?php echo $fact['value']; ?></dd></div>
          <?php } ?>
        </dl>
      <?php } ?>

      <?php if (osc_is_web_user_logged_in() && osc_logged_user_id() == osc_item_user_id()) { ?>
        <p><a href="<?php echo osc_item_edit_url(); ?>" rel="nofollow">Editar alojamiento</a></p>
      <?php } ?>

      <?php if (osc_images_enabled_at_items() && osc_count_item_resources() > 0) { ?>
        <div class="at-item-photos">
          <?php for ($i = 0; osc_has_item_resources(); $i++) { ?>
            <a href="<?php echo osc_resource_url(); ?>"><img src="<?php echo osc_resource_thumbnail_url(); ?>" alt="<?php echo osc_esc_html(osc_item_title() . ', foto ' . ($i + 1)); ?>" loading="lazy"></a>
          <?php } ?>
        </div>
      <?php } ?>
      <?php osc_run_hook('item_images'); ?>

      <?php if (!$at_is_directory && trim(strip_tags(osc_item_description())) !== '') { ?>
        <section class="at-item-section" aria-labelledby="t-sobre">
          <h2 id="t-sobre">Sobre el alojamiento</h2>
          <div class="at-item-desc"><?php echo osc_item_description(); ?></div>
        </section>
      <?php } ?>

      <?php osc_run_hook('item_description_after'); ?>
      <?php osc_run_hook('item_meta'); ?>
      <div class="item-hook"><?php osc_run_hook('item_detail', osc_item()); ?></div>
      <?php osc_run_hook('location'); ?>
    </article>

    <aside class="at-item-aside">
      <?php if ($at_is_directory) { ?>
        <section class="at-panel at-panel-action" aria-labelledby="t-consulta">
          <h2 id="t-consulta">Consultá y reservá en su sitio</h2>
          <?php osc_run_hook('item_title'); ?>
        </section>
        <section class="at-panel at-panel-notice" aria-labelledby="t-ficha">
          <h2 id="t-ficha">¿Qué es una ficha informativa?</h2>
          <p>La armamos con datos públicos del sitio oficial del alojamiento. El alojamiento no la gestiona: para consultar disponibilidad, tarifas o reservar, usá su sitio oficial.</p>
        </section>
      <?php } else { ?>
        <section class="at-panel at-panel-action at-contact" aria-labelledby="t-contacto">
          <h2 id="t-contacto">Consultá al alojamiento</h2>
          <?php osc_run_hook('item_title'); ?>
          <p class="at-contact-line"><strong>Publica:</strong> <?php echo osc_item_contact_name(); ?></p>
          <?php if (osc_item_show_email()) { ?>
            <p class="at-contact-line"><strong>Correo:</strong> <?php echo osc_item_contact_email(); ?></p>
          <?php } ?>
          <?php if (osc_item_contact_phone() != '' && osc_item_show_phone()) { ?>
            <p class="at-contact-line"><strong>Teléfono:</strong> <?php echo osc_item_contact_phone(false); ?></p>
          <?php } ?>

          <?php if (osc_item_contact_form_disabled()) { ?>
            <!-- Contact form disabled -->
          <?php } else if (osc_item_is_expired()) { ?>
            <p>Este alojamiento ya no recibe consultas.</p>
          <?php } else if ((osc_logged_user_id() == osc_item_user_id()) && osc_logged_user_id() != 0) { ?>
            <p>Es tu alojamiento: no podés enviarte consultas.</p>
          <?php } else if (osc_reg_user_can_contact() && !osc_is_web_user_logged_in()) { ?>
            <p>Para enviar una consulta tenés que ingresar con tu cuenta.</p>
            <a class="at-btn at-btn-secondary" href="<?php echo osc_user_login_url(); ?>">Ingresar</a>
          <?php } else { ?>
            <ul id="error_list"></ul>
            <form action="<?php echo osc_base_url(true); ?>" method="post" name="contact_form" id="contact_form"<?php if (osc_item_attachment()) { ?> enctype="multipart/form-data"<?php } ?>>
              <?php osc_prepare_user_info(); ?>
              <input type="hidden" name="action" value="contact_post" />
              <input type="hidden" name="page" value="item" />
              <input type="hidden" name="id" value="<?php echo osc_item_id(); ?>" />
              <div class="control-group"><label for="yourName">Tu nombre</label><?php ContactForm::your_name(); ?></div>
              <div class="control-group"><label for="yourEmail">Tu correo</label><?php ContactForm::your_email(); ?></div>
              <div class="control-group"><label for="phoneNumber">Teléfono (opcional)</label><?php ContactForm::your_phone_number(); ?></div>
              <div class="control-group"><label for="message">Consulta</label><?php ContactForm::your_message(); ?></div>
              <?php osc_run_hook('item_contact_form', osc_item_id()); ?>
              <?php osc_show_recaptcha(); ?>
              <button type="submit" class="at-btn at-btn-primary">Enviar consulta</button>
            </form>
            <?php ContactForm::js_validation(); ?>
          <?php } ?>
        </section>
        <?php osc_run_hook('item_contact'); ?>
      <?php } ?>
    </aside>
  </div>

  <?php related_listings(); ?>
  <?php if (osc_count_items() > 0) { ?>
    <section class="at-item-section" aria-labelledby="t-mas">
      <h2 id="t-mas"><?php echo $at_destination !== '' ? 'Más alojamientos en ' . osc_esc_html($at_destination) : 'Más alojamientos'; ?></h2>
      <ul class="at-listing-list">
        <?php while (osc_has_items()) { at_listing_row(false); } ?>
      </ul>
    </section>
  <?php } ?>
</div>

<?php osc_run_hook('item_bottom'); ?>
<?php osc_current_web_theme_path('footer.php') ; ?>
