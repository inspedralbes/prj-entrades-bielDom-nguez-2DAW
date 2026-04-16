/**
 * Generació SVG del QR amb node-qrcode 1.5 (Speckit T026).
 * El text sol ser el JWT de l’entrada o una referència segura amb public_uuid.
 *
 * Nota: el pla citava generateTicketSvg.ts; aquest mòdul és l’equivalent ESM sense build TS.
 */

//================================ NAMESPACES / IMPORTS ============

import QRCode from 'qrcode';

//================================ FUNCIONS PÚBLIQUES ============

/**
 * @param {string} text Contingut a codificar (p. ex. JWT de ticket)
 * @param {{ width?: number, margin?: number }} [options]
 * @returns {Promise<string>} Marcatge SVG (image/svg+xml)
 */
export async function generateTicketSvg (text, options = {}) {
  if (typeof text !== 'string' || text.length === 0) {
    throw new TypeError('generateTicketSvg: text no vàlid');
  }

  let width = 256;
  if (options.width !== undefined) {
    width = Number(options.width);
  }
  let margin = 2;
  if (options.margin !== undefined) {
    margin = Number(options.margin);
  }

  let w = 256;
  if (Number.isFinite(width) && width > 0) {
    w = width;
  }
  let m = 2;
  if (Number.isFinite(margin) && margin >= 0) {
    m = margin;
  }

  return QRCode.toString(text, {
    type: 'svg',
    width: w,
    margin: m,
    errorCorrectionLevel: 'M',
    color: {
      dark: '#000000ff',
      light: '#ffffffff',
    },
  });
}
