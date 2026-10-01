<?php
if (!defined('ABS_PATH')) {
  exit('ABS_PATH is not loaded. Direct access is not allowed.');
}

// Admin removal-requests screen (Amendment U7, design.md "Admin page" architecture decision).
// Rendered inside oc-admin's plugins/view.php wrapper (own header/footer/flash messages already
// printed around this file by that wrapper -- see oc-admin/themes/omega/plugins/view.php), reached
// via the tourist-directory-admin route. The matching POST handler is
// tourist_directory_admin_requests_handle_post() in index.php, wired to renderplugin_controller,
// which fires before this view (oc-admin/plugins.php:456), so a submission never sees stale state.
// AdminSecBaseModel already enforced the admin session and moderator-access allowlist before this
// file is ever included.

// Retention housekeeping runs on every admin page load (task 7.10), never from a public request --
// see tourist_directory_removal_apply_retention()'s own comment in index.php.
tourist_directory_removal_apply_retention();

$tourist_directory_admin_locale = osc_current_admin_locale();
$tourist_directory_requests = tourist_directory_removal_requests_all();
$tourist_directory_admin_action_url = osc_route_admin_url(TOURIST_DIRECTORY_ADMIN_ROUTE);
?>
<div class="tourist-directory-admin-requests">
  <h2><?php echo osc_esc_html(tourist_directory_text('Solicitudes de baja de directorio', 'Directory removal requests', $tourist_directory_admin_locale)); ?></h2>

  <p class="tourist-directory-admin-channel-status">
    <?php echo osc_esc_html(tourist_directory_is_channel_ready()
      ? tourist_directory_text('Canal de solicitudes de baja: disponible.', 'Removal request channel: available.', $tourist_directory_admin_locale)
      : tourist_directory_text('Canal de solicitudes de baja: no disponible (falta completar la migración de esquema).', 'Removal request channel: unavailable (schema migration not complete).', $tourist_directory_admin_locale)
    ); ?>
  </p>

  <?php if (empty($tourist_directory_requests)): ?>

    <p><?php echo osc_esc_html(tourist_directory_text('No hay solicitudes de baja registradas.', 'No removal requests recorded.', $tourist_directory_admin_locale)); ?></p>

  <?php else: ?>

    <table class="tourist-directory-admin-requests-table">
      <thead>
        <tr>
          <th><?php echo osc_esc_html(tourist_directory_text('Ficha', 'Listing', $tourist_directory_admin_locale)); ?></th>
          <th><?php echo osc_esc_html(tourist_directory_text('Fecha', 'Date', $tourist_directory_admin_locale)); ?></th>
          <th><?php echo osc_esc_html(tourist_directory_text('Relación', 'Relation', $tourist_directory_admin_locale)); ?></th>
          <th><?php echo osc_esc_html(tourist_directory_text('Estado', 'Status', $tourist_directory_admin_locale)); ?></th>
          <th><?php echo osc_esc_html(tourist_directory_text('Contacto de respuesta', 'Reply contact', $tourist_directory_admin_locale)); ?></th>
          <th><?php echo osc_esc_html(tourist_directory_text('Motivo', 'Reason', $tourist_directory_admin_locale)); ?></th>
          <th><?php echo osc_esc_html(tourist_directory_text('Acciones', 'Actions', $tourist_directory_admin_locale)); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tourist_directory_requests as $tourist_directory_request_row): ?>
          <?php
            $tourist_directory_request_item = Item::newInstance()->findByPrimaryKey((int) $tourist_directory_request_row['fk_i_item_id']);
            $tourist_directory_request_item_title = (is_array($tourist_directory_request_item) && isset($tourist_directory_request_item['s_title']))
              ? $tourist_directory_request_item['s_title']
              : '';
          ?>
          <tr>
            <td>#<?php echo (int) $tourist_directory_request_row['fk_i_item_id']; ?><?php if ($tourist_directory_request_item_title !== ''): ?> &mdash; <?php echo osc_esc_html($tourist_directory_request_item_title); ?><?php endif; ?></td>
            <td><?php echo osc_esc_html($tourist_directory_request_row['dt_requested']); ?></td>
            <td><?php echo osc_esc_html($tourist_directory_request_row['s_relation']); ?></td>
            <td><?php echo osc_esc_html($tourist_directory_request_row['s_status']); ?></td>
            <td><?php echo osc_esc_html((string) $tourist_directory_request_row['s_reply_contact']); ?></td>
            <td><?php echo osc_esc_html((string) $tourist_directory_request_row['s_reason']); ?></td>
            <td class="tourist-directory-admin-actions">

              <?php if ($tourist_directory_request_row['s_status'] === 'pending'): ?>
              <form method="post" action="<?php echo osc_esc_html($tourist_directory_admin_action_url); ?>" class="tourist-directory-admin-inline-form">
                <?php echo osc_csrf_token_form(); ?>
                <input type="hidden" name="id" value="<?php echo (int) $tourist_directory_request_row['pk_i_id']; ?>" />
                <input type="hidden" name="<?php echo osc_esc_html(tourist_directory_admin_action_param()); ?>" value="mark_processed" />
                <button type="submit"><?php echo osc_esc_html(tourist_directory_text('Marcar procesada', 'Mark processed', $tourist_directory_admin_locale)); ?></button>
              </form>
              <?php endif; ?>

              <?php if (in_array($tourist_directory_request_row['s_status'], array('pending', 'processed'), true)): ?>
              <form method="post" action="<?php echo osc_esc_html($tourist_directory_admin_action_url); ?>" class="tourist-directory-admin-inline-form">
                <?php echo osc_csrf_token_form(); ?>
                <input type="hidden" name="id" value="<?php echo (int) $tourist_directory_request_row['pk_i_id']; ?>" />
                <input type="hidden" name="<?php echo osc_esc_html(tourist_directory_admin_action_param()); ?>" value="reactivate" />
                <label class="tourist-directory-admin-confirm">
                  <input type="checkbox" name="confirm" value="1" />
                  <?php echo osc_esc_html(tourist_directory_text('Confirmo la reactivación (ej. solicitud abusiva)', 'I confirm the reactivation (e.g. abusive request)', $tourist_directory_admin_locale)); ?>
                </label>
                <button type="submit"><?php echo osc_esc_html(tourist_directory_text('Reactivar ficha', 'Reactivate listing', $tourist_directory_admin_locale)); ?></button>
              </form>
              <?php endif; ?>

            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  <?php endif; ?>
</div>
