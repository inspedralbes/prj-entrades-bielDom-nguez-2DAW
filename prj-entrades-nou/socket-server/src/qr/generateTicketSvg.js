/**
 * Generació SVG del QR amb node-qrcode 1.5 (Speckit T026).
 * El text sol ser el JWT de l’entrada o una referència segura amb public_uuid.
 *
 * Nota: el pla citava generateTicketSvg.ts; aquest mòdul és l’equivalent ESM sense build TS.
 */
import QRCode from 'qrcode';

/**
 * @param {string} text Contingut a codificar (p. ex. JWT de ticket)
 * @param {{ width?: number, margin?: number }} [options]
 * @returns {Promise<string>} Marcatge SVG (image/svg+xml)
 */
export async function generateTicketSvg (text, options = {}) {
  if (typeof text !== 'string' || text.length === 0) {
    throw new TypeError('generateTicketSvg: text no vàlid');
  }

  const width = options.width !== undefined ? Number(options.width) : 256;
  const margin = options.margin !== undefined ? Number(options.margin) : 2;

  return QRCode.toString(text, {
    type: 'svg',
    width: Number.isFinite(width) && width > 0 ? width : 256,
    margin: Number.isFinite(margin) && margin >= 0 ? margin : 2,
    errorCorrectionLevel: 'M',
    color: {
      dark: '#000000ff',
      light: '#ffffffff',
    },
  });
}
