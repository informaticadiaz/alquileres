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
osc_add_hook('header','sigma_follow_construct');
sigma_add_body_class('search');

// Category context: the first selected category drives the band color, title and breadcrumb.
$at_category_ids = osc_search_category_id();
$at_category_id = is_array($at_category_ids) && count($at_category_ids) > 0 ? (int) reset($at_category_ids) : 0;
$at_path = at_category_path($at_category_id);
$at_region = isset($at_path[0]) ? $at_path[0]['s_name'] : '';
$at_token = $at_region !== '' ? at_region_token($at_region) : 'ink';
$at_current = count($at_path) > 0 ? $at_path[count($at_path) - 1] : null;
$at_title = $at_current ? at_short_name($at_current['s_name']) : 'Todos los alojamientos';
$at_total = (int) osc_search_total_items();
?>
<?php osc_current_web_theme_path('header.php'); ?>

<section class="at-band" style="background: var(--<?php echo $at_token; ?>);">
  <div class="at-container">
    <?php if (count($at_path) > 0) { ?>
      <nav class="at-breadcrumb at-breadcrumb-on-band" aria-label="Ubicación">
        <ol>
          <li><a href="<?php echo osc_base_url(); ?>#destinos">Destinos</a></li>
          <?php foreach ($at_path as $i => $at_cat) { ?>
            <li aria-hidden="true">/</li>
            <?php if ($i < count($at_path) - 1) { ?>
              <li><a href="<?php echo osc_esc_html(at_category_search_url($at_cat['pk_i_id'])); ?>"><?php echo osc_esc_html(at_short_name($at_cat['s_name'])); ?></a></li>
            <?php } else { ?>
              <li aria-current="page"><?php echo osc_esc_html(at_short_name($at_cat['s_name'])); ?></li>
            <?php } ?>
          <?php } ?>
        </ol>
      </nav>
    <?php } ?>
    <h1><?php echo osc_esc_html($at_title); ?></h1>
    <p><?php
      $at_noun = $at_total === 1 ? 'alojamiento' : 'alojamientos';
      if ($at_current && count($at_path) > 1) {
        echo $at_total . ' ' . $at_noun . ' en ' . osc_esc_html(at_short_name($at_current['s_name'])) . ', ' . osc_esc_html($at_region) . '.';
      } else if ($at_current) {
        echo $at_total . ' ' . $at_noun . ' en la región.';
      } else {
        echo $at_total . ' ' . $at_noun . '.';
      }
    ?></p>
  </div>
</section>

<div class="at-container at-results">
  <?php osc_run_hook('search_items_top'); ?>

  <div class="at-filters"><?php osc_run_hook('search_items_filter'); ?></div>

  <?php if (osc_count_items() == 0) { ?>
    <div class="at-empty">
      <h2>No hay alojamientos con estos criterios</h2>
      <p>Probá quitar algún filtro o elegí otro destino.</p>
      <a class="at-btn at-btn-secondary" href="<?php echo osc_base_url(); ?>#destinos">Ver destinos</a>
    </div>
  <?php } else { ?>
    <?php $at_numbers = sigma_search_number(); ?>
    <p class="at-results-count">Mostrando <?php echo (int) $at_numbers['from']; ?> a <?php echo (int) $at_numbers['to']; ?> de <?php echo (int) $at_numbers['of']; ?></p>

    <ul class="at-listing-list">
      <?php while (osc_has_items()) { at_listing_row(count($at_path) < 2); } ?>
    </ul>

    <nav class="at-pagination" aria-label="Páginas"><?php echo osc_search_pagination(); ?></nav>
  <?php } ?>

  <?php osc_run_hook('search_items_bottom'); ?>
</div>
<?php osc_current_web_theme_path('footer.php') ; ?>
