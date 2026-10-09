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
sigma_add_body_class('home');

$at_regions = at_regions();
$at_total = (int) osc_total_active_items();
?>

<?php osc_current_web_theme_path('header.php'); ?>

<?php osc_run_hook('home_top'); ?>

<section class="at-hero">
  <div class="at-container">
    <h1 class="at-display-xl">¿A dónde viajás esta temporada?</h1>
    <p class="at-lead"><?php echo $at_total; ?> alojamientos en <?php echo count($at_regions); ?> regiones de Argentina. Mirá cada ficha y consultá directo con el lugar.</p>
  </div>
</section>

<section id="destinos" class="at-container" aria-labelledby="t-regiones">
  <h2 id="t-regiones" class="at-h2">Elegí una región</h2>
  <div class="at-region-grid">
    <?php foreach ($at_regions as $region) { ?>
      <article class="at-region-tile" style="background: var(--<?php echo $region['token']; ?>);">
        <h3><?php echo osc_esc_html($region['name']); ?></h3>
        <?php if (count($region['destinations']) > 0) { ?>
          <ul>
            <?php foreach ($region['destinations'] as $destination) { ?>
              <li><a href="<?php echo osc_esc_html(at_category_search_url($destination['id'])); ?>"><?php echo osc_esc_html($destination['name']); ?></a></li>
            <?php } ?>
          </ul>
        <?php } ?>
        <a class="at-region-all" href="<?php echo osc_esc_html(at_category_search_url($region['id'])); ?>">Ver toda la región<span class="at-visually-hidden"> <?php echo osc_esc_html($region['name']); ?></span></a>
      </article>
    <?php } ?>
  </div>
</section>

<?php osc_reset_latest_items(); ?>
<?php if (osc_count_latest_items() > 0) { ?>
<section class="at-section at-section-alt" aria-labelledby="t-nuevos" style="margin-top: var(--space-7);">
  <div class="at-container">
    <div class="at-section-head">
      <h2 id="t-nuevos" class="at-h2">Recién sumados</h2>
      <a href="<?php echo osc_esc_html(osc_search_url(array('page' => 'search'))); ?>">Ver todos los alojamientos</a>
    </div>
    <ul class="at-listing-list at-boxed">
      <?php
        osc_reset_latest_items();
        $at_count = 0;
        while (osc_has_latest_items() && $at_count < 6) {
          at_listing_row(true);
          $at_count++;
        }
      ?>
    </ul>
  </div>
</section>
<?php } ?>

<section id="como-funciona" class="at-section" aria-labelledby="t-como">
  <div class="at-container">
    <h2 id="t-como" class="at-h2">Cómo funciona</h2>
    <p class="at-muted">Somos una vitrina: reunimos alojamientos de todo el país para que los compares en un solo lugar. La reserva y el pago los acordás directamente con cada alojamiento.</p>
    <ol class="at-steps">
      <li class="at-step"><span class="at-step-num" aria-hidden="true">1</span><h3>Elegí un destino</h3><p>Recorré las regiones y elegí la ciudad, el valle o la costa que te interesa.</p></li>
      <li class="at-step"><span class="at-step-num" aria-hidden="true">2</span><h3>Revisá la ficha</h3><p>Cada ficha indica quién publicó la información y cómo contactar al alojamiento.</p></li>
      <li class="at-step"><span class="at-step-num" aria-hidden="true">3</span><h3>Consultá directo</h3><p>Escribile al alojamiento o entrá a su sitio oficial. No cobramos comisión ni intervenimos en la reserva.</p></li>
    </ol>
  </div>
</section>

<section class="at-container" aria-labelledby="t-publicar">
  <div class="at-owner-band">
    <div>
      <h2 id="t-publicar">¿Tenés cabañas, un apart o un complejo?</h2>
      <p>Publicá tu alojamiento y recibí consultas de huéspedes directamente, sin intermediarios. Si tu complejo ya figura en una ficha informativa, podés pedir su baja desde la misma ficha.</p>
    </div>
    <a class="at-btn at-btn-inverse" href="<?php echo osc_item_post_url_in_category(); ?>">Publicar alojamiento</a>
  </div>
</section>

<?php osc_run_hook('home_bottom'); ?>

<?php osc_current_web_theme_path('footer.php') ; ?>
