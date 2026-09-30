<?php

// Pure destination-tree data for the tourist-identity plugin.
//
// This file is loadable standalone (no Osclass calls at load time) and
// side-effect free, so tests/test_tourist_showcase.php can exercise
// tourist_identity_tree() directly without bootstrapping Osclass.
//
// Region and leaf names (es_ES / en_US) come verbatim from
// openspec/changes/tourist-destination-categories/research.md. Each region
// is listed in the confirmed order (Buenos Aires, Córdoba, Cuyo, Litoral,
// Norte, Patagonia); each region's leaves follow research.md relevance
// order with the "Otros destinos" catch-all last. Every region and leaf key
// is an explicit ASCII slug (matches ^[a-z0-9-]+$) and is unique across the
// whole tree, since keys double as `tourist_identity.category_map` ids.
//
// The Buenos Aires region alone carries an `anchor` id: category 47 is
// repurposed in place as that region, so it is the only region reusing a
// pre-existing row instead of being newly inserted.

function tourist_identity_tree() {
  return array(
    array(
      'key' => 'buenos-aires',
      'anchor' => 47,
      'names' => array('es_ES' => 'Buenos Aires', 'en_US' => 'Buenos Aires'),
      'leaves' => array(
        array('caba', 'Ciudad Autónoma de Buenos Aires (CABA)', 'Buenos Aires City'),
        array('costa-atlantica-mar-del-plata', 'Costa Atlántica – Mar del Plata', 'Atlantic Coast – Mar del Plata'),
        array('costa-atlantica-pinamar-carilo', 'Costa Atlántica – Pinamar y Cariló', 'Atlantic Coast – Pinamar & Cariló'),
        array('costa-atlantica-villa-gesell-mar-de-las-pampas', 'Costa Atlántica – Villa Gesell y Mar de las Pampas', 'Atlantic Coast – Villa Gesell & Mar de las Pampas'),
        array('costa-atlantica-san-bernardo-costa-esmeralda', 'Costa Atlántica – San Bernardo y Costa Esmeralda', 'Atlantic Coast – San Bernardo & Costa Esmeralda'),
        array('tandil', 'Tandil', 'Tandil'),
        array('sierra-de-la-ventana', 'Sierra de la Ventana', 'Sierra de la Ventana'),
        array('san-antonio-de-areco', 'San Antonio de Areco', 'San Antonio de Areco'),
        array('la-plata', 'La Plata', 'La Plata'),
        array('otros-destinos-buenos-aires', 'Otros destinos de Buenos Aires', 'Other Buenos Aires destinations'),
      ),
    ),
    array(
      'key' => 'cordoba',
      'names' => array('es_ES' => 'Córdoba', 'en_US' => 'Córdoba'),
      'leaves' => array(
        array('villa-carlos-paz', 'Villa Carlos Paz', 'Villa Carlos Paz'),
        array('la-cumbre-la-falda', 'La Cumbre y La Falda (Valle de Punilla)', 'La Cumbre & La Falda (Punilla Valley)'),
        array('mina-clavero', 'Mina Clavero (Valle de Traslasierra)', 'Mina Clavero (Traslasierra Valley)'),
        array('villa-general-belgrano', 'Villa General Belgrano (Valle de Calamuchita)', 'Villa General Belgrano (Calamuchita Valley)'),
        array('la-cumbrecita', 'La Cumbrecita', 'La Cumbrecita'),
        array('ciudad-de-cordoba', 'Ciudad de Córdoba', 'Córdoba City'),
        array('alta-gracia', 'Alta Gracia', 'Alta Gracia'),
        array('otros-destinos-cordoba', 'Otros destinos de Córdoba', 'Other Córdoba destinations'),
      ),
    ),
    array(
      'key' => 'cuyo',
      'names' => array('es_ES' => 'Cuyo', 'en_US' => 'Cuyo'),
      'leaves' => array(
        array('ciudad-de-mendoza', 'Ciudad de Mendoza', 'Mendoza City'),
        array('valle-de-uco', 'Valle de Uco', 'Uco Valley'),
        array('san-rafael', 'San Rafael', 'San Rafael'),
        array('malargue', 'Malargüe', 'Malargüe'),
        array('san-juan', 'San Juan (ciudad y Valle de Calingasta)', 'San Juan (city & Calingasta Valley)'),
        array('san-luis', 'San Luis (ciudad y sierras)', 'San Luis (city & sierras)'),
        array('otros-destinos-cuyo', 'Otros destinos de Cuyo', 'Other Cuyo destinations'),
      ),
    ),
    array(
      'key' => 'litoral',
      'names' => array('es_ES' => 'Litoral', 'en_US' => 'Litoral'),
      'leaves' => array(
        array('puerto-iguazu', 'Puerto Iguazú (Cataratas)', 'Puerto Iguazú (Iguazú Falls)'),
        array('esteros-del-ibera', 'Esteros del Iberá', 'Iberá Wetlands'),
        array('colon-concepcion-del-uruguay', 'Colón y Concepción del Uruguay', 'Colón & Concepción del Uruguay'),
        array('gualeguaychu', 'Gualeguaychú', 'Gualeguaychú'),
        array('federacion', 'Federación (Termas)', 'Federación (Hot Springs)'),
        array('rosario', 'Rosario', 'Rosario'),
        array('corrientes-ciudad', 'Corrientes (ciudad)', 'Corrientes City'),
        array('otros-destinos-litoral', 'Otros destinos del Litoral', 'Other Litoral destinations'),
      ),
    ),
    array(
      'key' => 'norte',
      'names' => array('es_ES' => 'Norte', 'en_US' => 'Norte'),
      'leaves' => array(
        array('salta', 'Salta (ciudad y Valles Calchaquíes)', 'Salta (city & Calchaquí Valleys)'),
        array('cafayate', 'Cafayate', 'Cafayate'),
        array('purmamarca', 'Purmamarca', 'Purmamarca'),
        array('tilcara', 'Tilcara', 'Tilcara'),
        array('humahuaca', 'Humahuaca', 'Humahuaca'),
        array('tucuman-ciudad', 'Tucumán (ciudad)', 'Tucumán City'),
        array('tafi-del-valle', 'Tafí del Valle', 'Tafí del Valle'),
        array('termas-de-rio-hondo', 'Termas de Río Hondo', 'Termas de Río Hondo'),
        array('otros-destinos-norte', 'Otros destinos del Norte', 'Other Norte destinations'),
      ),
    ),
    array(
      'key' => 'patagonia',
      'names' => array('es_ES' => 'Patagonia', 'en_US' => 'Patagonia'),
      'leaves' => array(
        array('san-carlos-de-bariloche', 'San Carlos de Bariloche', 'San Carlos de Bariloche'),
        array('villa-la-angostura', 'Villa La Angostura', 'Villa La Angostura'),
        array('san-martin-de-los-andes', 'San Martín de los Andes', 'San Martín de los Andes'),
        array('el-calafate', 'El Calafate', 'El Calafate'),
        array('el-chalten', 'El Chaltén', 'El Chaltén'),
        array('ushuaia', 'Ushuaia', 'Ushuaia'),
        array('puerto-madryn', 'Puerto Madryn', 'Puerto Madryn'),
        array('las-grutas', 'Las Grutas', 'Las Grutas'),
        array('otros-destinos-patagonia', 'Otros destinos de Patagonia', 'Other Patagonia destinations'),
      ),
    ),
  );
}
