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
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" dir="<?php echo sigma_default_direction()=='0' ? 'ltr': 'rtl'; ?>" lang="<?php echo str_replace('_', '-', osc_current_user_locale()); ?>">
  <head>
    <?php osc_current_web_theme_path('head.php') ; ?>
  </head>
<body <?php sigma_body_class(); ?>>
<header class="at-header">
  <?php osc_run_hook('header_top'); ?>
  <div class="at-container">
    <a class="at-wordmark" href="<?php echo osc_base_url(); ?>"><?php echo osc_esc_html(osc_page_title()); ?></a>
  </div>
  <?php osc_run_hook('header_bottom'); ?>
</header>

<?php osc_run_hook('header_after'); ?>

<?php if(osc_get_preference('header-728x90', 'sigma') <> '') { ?>
  <section class="header-ad">
    <div class="wrapper">
      <div class="ads_header"><?php echo osc_get_preference('header-728x90', 'sigma'); ?></div>
    </div>
  </section>
<?php } ?>

<section>
<?php osc_show_widgets('header'); ?>
  <?php
    // Osclass's generic breadcrumb (English labels, "Home > Contact") is not used anywhere: the
    // results and listing pages draw their own, and forms and account pages do not need one.
    $breadcrumb = '';
  ?>

  <?php if( $breadcrumb !== '') { ?>
    <div class="wrapper wrapper-flash">
      <div class="breadcrumb">
        <?php echo $breadcrumb; ?>
        <div class="clear"></div>
      </div>
    </div>
  <?php } ?>

  <div class="wrapper wrapper-flash flash2"><?php osc_show_flash_message(); ?></div>

  <?php osc_run_hook('before-content'); ?>

  <div class="wrapper" id="content">
    <?php osc_run_hook('before-main'); ?>
    <div id="main">
      <?php osc_run_hook('inside-main'); ?>
