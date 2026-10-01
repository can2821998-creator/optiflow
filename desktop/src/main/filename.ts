/** Safe file names for downloads (spec §23). Pure → unit-tested. */

const RESERVED = /^(con|prn|aux|nul|com[0-9]|lpt[0-9])(\..*)?$/i;

export function sanitizeFilename(raw: string | undefined | null, fallback = 'indirme'): string {
  let name = String(raw ?? '');
  // keep only the last path segment, whatever separator the server used
  name = name.split(/[\\/]/).pop() ?? '';
  // eslint-disable-next-line no-control-regex
  name = name.replace(/[\u0000-\u001F\u007F<>:"|?*]/g, '_');
  name = name.replace(/^[.\s]+/, '').replace(/[.\s]+$/, '');
  if (RESERVED.test(name)) name = `_${name}`;
  if (name.length > 150) {
    const dot = name.lastIndexOf('.');
    const ext = dot > 0 && name.length - dot <= 10 ? name.slice(dot) : '';
    name = name.slice(0, 150 - ext.length) + ext;
  }
  return name || fallback;
}
