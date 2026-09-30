<?php
if (!defined('ABS_PATH')) {
  exit('ABS_PATH is not loaded. Direct access is not allowed.');
}

// Public removal request form (Amendment U7, design.md "Public page"/"Decision" architecture
// decisions). Rendered by CWebCustom (oc-includes/osclass/controller/custom.php) for the
// tourist-directory-removal route; the matching POST handler is
// tourist_directory_init_custom_removal_post() in index.php, wired to the init_custom hook (fires
// before this view, so a submission never sees stale flash state).
//
// The entry id comes ONLY from the route param -- never from a form field, so nothing here lets a
// visitor edit which entry the form targets.
$tourist_directory_entry_id = (int) Params::getParam('entry');
$tourist_directory_locale = osc_current_user_locale();
$tourist_directory_channel_ready = tourist_directory_is_channel_ready();

$tourist_directory_entry_title = '';
if ($tourist_directory_channel_ready && $tourist_directory_entry_id > 0) {
  $tourist_directory_item = Item::newInstance()->findByPrimaryKey($tourist_directory_entry_id);
  if (is_array($tourist_directory_item) && isset($tourist_directory_item['s_title'])) {
    $tourist_directory_entry_title = $tourist_directory_item['s_title'];
  }
}
?>
<div class="tourist-directory-removal-form">
  <h1><?php echo osc_esc_html(tourist_directory_text('Solicitar baja de ficha de directorio', 'Request directory listing removal', $tourist_directory_locale)); ?></h1>

  <?php if (!$tourist_directory_channel_ready): ?>

    <p class="tourist-directory-removal-unavailable"><?php echo osc_esc_html(tourist_directory_removal_channel_unavailable_message($tourist_directory_locale)); ?></p>

  <?php elseif ($tourist_directory_entry_id <= 0): ?>

    <p class="tourist-directory-removal-unavailable"><?php echo osc_esc_html(tourist_directory_text('Ficha no encontrada.', 'Listing not found.', $tourist_directory_locale)); ?></p>

  <?php else: ?>

    <p class="tourist-directory-removal-entry">
      #<?php echo (int) $tourist_directory_entry_id; ?>
      <?php if ($tourist_directory_entry_title !== ''): ?>
        &mdash; <?php echo osc_esc_html($tourist_directory_entry_title); ?>
      <?php endif; ?>
    </p>

    <p class="tourist-directory-removal-privacy-note">
      <?php echo osc_esc_html(tourist_directory_text(
        'Los datos de este formulario se usan únicamente para procesar tu solicitud de baja de esta ficha de directorio, conforme a la Ley 25.326 de Protección de Datos Personales. El campo de contacto de respuesta y el motivo son opcionales.',
        'The data in this form is used only to process your removal request for this directory listing, in accordance with Argentine Law 25.326 on Personal Data Protection. The reply contact and reason fields are optional.',
        $tourist_directory_locale
      )); ?>
    </p>

    <form method="post" action="<?php echo osc_esc_html(osc_route_url(TOURIST_DIRECTORY_REMOVAL_ROUTE, array('entry' => $tourist_directory_entry_id))); ?>" class="tourist-directory-removal-form-fields">
      <?php echo osc_csrf_token_form(); ?>

      <div class="tourist-directory-hp-field" aria-hidden="true">
        <label for="tourist-directory-website"><?php echo osc_esc_html(tourist_directory_text('Dejá este campo vacío', 'Leave this field empty', $tourist_directory_locale)); ?></label>
        <input type="text" name="website" id="tourist-directory-website" value="" autocomplete="off" tabindex="-1" />
      </div>

      <fieldset class="tourist-directory-removal-relation">
        <legend><?php echo osc_esc_html(tourist_directory_text('¿Cuál es tu relación con el complejo?', 'What is your relation to the property?', $tourist_directory_locale)); ?></legend>
        <label><input type="radio" name="relation" value="propietario" required /> <?php echo osc_esc_html(tourist_directory_text('Propietario/a', 'Owner', $tourist_directory_locale)); ?></label>
        <label><input type="radio" name="relation" value="administrador" required /> <?php echo osc_esc_html(tourist_directory_text('Administrador/a', 'Administrator', $tourist_directory_locale)); ?></label>
        <label><input type="radio" name="relation" value="otro" required /> <?php echo osc_esc_html(tourist_directory_text('Otro', 'Other', $tourist_directory_locale)); ?></label>
      </fieldset>

      <div class="tourist-directory-removal-field">
        <label for="tourist-directory-reply-contact"><?php echo osc_esc_html(tourist_directory_text('Contacto de respuesta (opcional)', 'Reply contact (optional)', $tourist_directory_locale)); ?></label>
        <input type="text" name="reply_contact" id="tourist-directory-reply-contact" maxlength="190" />
      </div>

      <div class="tourist-directory-removal-field">
        <label for="tourist-directory-reason"><?php echo osc_esc_html(tourist_directory_text('Motivo (opcional)', 'Reason (optional)', $tourist_directory_locale)); ?></label>
        <textarea name="reason" id="tourist-directory-reason" maxlength="1000"></textarea>
      </div>

      <button type="submit" class="tourist-directory-removal-submit"><?php echo osc_esc_html(tourist_directory_text('Enviar solicitud de baja', 'Submit removal request', $tourist_directory_locale)); ?></button>
    </form>

  <?php endif; ?>
</div>
