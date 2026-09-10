import { compose } from './docker';

export default async function globalTeardown() {
  if (process.env.PW_KEEP_E2E === '1' || process.env.PW_EXTERNAL_E2E === '1') return;
  compose('down', '--volumes', '--remove-orphans');
}
