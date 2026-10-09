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

if(osc_user_id() <= 0) { 
  $user = User::newInstance()->findByPrimaryKey(Session::newInstance()->_get('userId'));
  View::newInstance()->_exportVariableToView('user', $user);
}
?>
<div class="actions">
  <a href="#" data-bclass-toggle="display-filters" class="resp-toogle show-menu-btn btn btn-secondary"><?php echo 'Ver menú'; ?></a>
</div>

<div id="sidebar" class="fixed-layout">
  <div class="fixed-close"><i class="fas fa-times"></i></div>
  <nav aria-label="Mi cuenta"><?php echo osc_private_user_menu( at_user_menu() ); ?></nav>
</div>

<div id="dialog-delete-account" title="<?php echo osc_esc_html('Eliminar cuenta'); ?>" style="display:none;"><?php echo '¿Seguro que querés eliminar tu cuenta? No se puede deshacer.'; ?></div>