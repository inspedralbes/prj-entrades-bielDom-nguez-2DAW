/**
 * Opcions compartides del mapa TR3.
 *
 * Nota: el relleu topogràfic “3D” de referència no el pot reproduir l’API JS estàndard;
 * aquí només es canvien colors (mapa vectorial roadmap estilitzat).
 */

/**
 * Estil fosc monocrom (blanc/gris) per a la pàgina /search/map — vores fines clares, fons carbó.
 * Documentació: https://developers.google.com/maps/documentation/javascript/style-reference
 *
 * @returns {object[]}
 */
export function buildTr3SearchMapGrayscaleStyles () {
  return [
    /* Terra: carbó; el mar es defineix després amb més contrast (gris mig). */
    { elementType: 'geometry', stylers: [{ color: '#0e0e0e' }] },
    { elementType: 'labels.text.fill', stylers: [{ color: '#a3a3a3' }] },
    { elementType: 'labels.text.stroke', stylers: [{ color: '#0e0e0e' }] },
    { featureType: 'administrative', elementType: 'geometry', stylers: [{ color: '#1c1c1c' }] },
    {
      featureType: 'administrative.country',
      elementType: 'geometry.stroke',
      stylers: [{ color: '#d4d4d4' }, { weight: 0.9 }],
    },
    {
      featureType: 'administrative.province',
      elementType: 'geometry.stroke',
      stylers: [{ color: '#b8b8b8' }, { weight: 0.55 }],
    },
    {
      featureType: 'administrative.locality',
      elementType: 'labels.text.fill',
      stylers: [{ color: '#c8c8c8' }],
    },
    { featureType: 'landscape', stylers: [{ color: '#101010' }] },
    { featureType: 'landscape.natural', stylers: [{ color: '#121212' }] },
    { featureType: 'poi', stylers: [{ visibility: 'off' }] },
    {
      featureType: 'road',
      elementType: 'geometry',
      stylers: [{ color: '#2a2a2a' }],
    },
    {
      featureType: 'road',
      elementType: 'geometry.stroke',
      stylers: [{ color: '#505050' }],
    },
    {
      featureType: 'road.highway',
      elementType: 'geometry',
      stylers: [{ color: '#383838' }],
    },
    {
      featureType: 'road.highway',
      elementType: 'geometry.stroke',
      stylers: [{ color: '#6a6a6a' }],
    },
    { featureType: 'transit', stylers: [{ visibility: 'off' }] },
    {
      featureType: 'water',
      elementType: 'geometry',
      stylers: [{ color: '#3a3d44' }],
    },
    {
      featureType: 'water',
      elementType: 'geometry.stroke',
      stylers: [{ color: '#5c636d' }, { weight: 0.65 }],
    },
    {
      featureType: 'water',
      elementType: 'labels.text.fill',
      stylers: [{ color: '#8a9099' }],
    },
  ];
}

/**
 * @param {{ lat: number, lng: number }} center
 * @param {number} zoom
 * @param {{ variant?: 'default' | 'searchMonochrome' }} [options]
 */
export function buildTr3GoogleMapOptions (center, zoom, options) {
  let variant = 'default';
  if (options !== undefined && options !== null && options.variant === 'searchMonochrome') {
    variant = 'searchMonochrome';
  }

  const g = window.google;
  if (!g || !g.maps) {
    if (variant === 'searchMonochrome') {
      return {
        center,
        zoom,
        mapTypeId: 'roadmap',
        styles: buildTr3SearchMapGrayscaleStyles(),
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false,
        backgroundColor: '#0e0e0e',
      };
    }
    return {
      center,
      zoom,
      mapTypeId: 'hybrid',
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: false,
      backgroundColor: '#131313',
    };
  }

  /* Fallback si l’API encara no ha exposat enums (carrera amb loading=async). */
  let zoomPos = null;
  if (g.maps.ControlPosition && g.maps.ControlPosition.RIGHT_BOTTOM !== undefined) {
    zoomPos = g.maps.ControlPosition.RIGHT_BOTTOM;
  }

  let mapTypeRoadmap = 'roadmap';
  if (g.maps.MapTypeId && g.maps.MapTypeId.ROADMAP !== undefined) {
    mapTypeRoadmap = g.maps.MapTypeId.ROADMAP;
  }

  let mapTypeHybrid = 'hybrid';
  if (g.maps.MapTypeId && g.maps.MapTypeId.HYBRID !== undefined) {
    mapTypeHybrid = g.maps.MapTypeId.HYBRID;
  }

  if (variant === 'searchMonochrome') {
    const mono = {
      center,
      zoom,
      mapTypeId: mapTypeRoadmap,
      styles: buildTr3SearchMapGrayscaleStyles(),
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: false,
      backgroundColor: '#0e0e0e',
      zoomControl: true,
    };
    if (zoomPos !== null) {
      mono.zoomControlOptions = { position: zoomPos };
    }
    return mono;
  }

  const hybridOpts = {
    center,
    zoom,
    mapTypeId: mapTypeHybrid,
    mapTypeControl: false,
    streetViewControl: false,
    fullscreenControl: false,
    backgroundColor: '#131313',
    zoomControl: true,
  };
  if (zoomPos !== null) {
    hybridOpts.zoomControlOptions = { position: zoomPos };
  }
  return hybridOpts;
}
