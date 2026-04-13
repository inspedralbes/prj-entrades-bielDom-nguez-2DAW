/**
 * URLs d’imatge des de l’API (`image_url` des de Ticketmaster CDN, HTTPS).
 * Només es retorna string vàlida per a <img src>; la resta → null (placeholder).
 */
export function useEventImage () {
  /**
   * @param {{ image_url?: string|null, name?: string|null }} ev
   * @returns {string|null}
   */
  function imageSrc (ev) {
    const u = ev?.image_url;
    if (typeof u !== 'string') {
      return null;
    }
    const t = u.trim();
    if (t === '') {
      return null;
    }
    if (!/^https?:\/\//i.test(t)) {
      return null;
    }
    return t;
  }

  /**
   * @param {{ name?: string|null }} ev
   * @returns {string}
   */
  function imageAlt (ev) {
    const n = ev?.name;
    if (typeof n === 'string' && n.trim() !== '') {
      return `Cartell: ${n.trim()}`;
    }
    return 'Cartell de l’esdeveniment';
  }

  return { imageSrc, imageAlt };
}
