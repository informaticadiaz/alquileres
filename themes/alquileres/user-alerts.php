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

    sigma_add_body_class('user user-profile');
    osc_add_hook('before-main','sidebar');
    function sidebar(){
        osc_current_web_theme_path('user-sidebar.php');
    }
    osc_add_filter('meta_title_filter','custom_meta_title');
    function custom_meta_title($data){
        return 'Alertas';;
    }
    osc_current_web_theme_path('header.php') ;
    $osc_user = osc_user();
?>
<h1><?php echo 'Alertas'; ?></h1>
<?php if(osc_count_alerts() == 0) { ?>
    <p class="empty"><?php echo 'Todavía no tenés alertas guardadas'; ?>.</p>
<?php } else { ?>
    <?php
    $i = 1;
    while(osc_has_alerts()) { ?>
        <div class="userItem user-alert" >
            <div class="title-has-actions">
              <h3>
                <?php 
                  if(osc_alert_name() != '') { 
                    echo osc_alert_name(); 
                  } else { 
                    echo sprintf('Alerta n.º %d', osc_alert_id()); 
                  } 
                ?>
              </h3>
              
              <a onclick="javascript:return confirm('<?php echo osc_esc_js('Esta acción no se puede deshacer. ¿Querés continuar?'); ?>');" href="<?php echo osc_user_unsubscribe_alert_url(); ?>">Eliminar esta alerta</a>
              <a href="<?php echo osc_search_alert_url(); ?>"><?php echo 'Ver en el buscador'; ?></a>

              <div class="clear">

              <?php echo osc_alert_change_frequency(osc_alert()); ?>
              
              <div class="clear">
              </div></div>
            <div>
            
            <?php osc_current_web_theme_path('loop.php'); ?>
            
            <?php if(osc_count_items() == 0) { ?>
              <div class="alerts-items-empty">
                0 <?php echo 'Alojamientos'; ?>
              </div>
            <?php } ?>
            </div>
        </div>
        <br />
    <?php
    $i++;
    }
    ?>
<?php  } ?>
<?php osc_current_web_theme_path('footer.php') ; ?>