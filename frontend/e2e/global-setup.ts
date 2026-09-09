import { compose } from './docker';

export default async function globalSetup() {
  compose('up', '--build', '-d', '--wait');
  compose('exec', '-T', 'app', 'php', 'artisan', 'migrate:fresh', '--seed', '--force');
  // Compose may recreate PHP-FPM after nginx has resolved its upstream address.
  compose('restart', 'nginx');
  for (let attempt = 0; attempt < 30; attempt += 1) {
    try {
      const response = await fetch('http://127.0.0.1:18081/api/v1/health');
      const frontend = await fetch('http://127.0.0.1:5174');
      if (response.ok && frontend.ok) return;
    } catch {
      // Retry while the dedicated runtime becomes reachable.
    }
    await new Promise((resolve) => setTimeout(resolve, 1000));
  }
  throw new Error('The isolated MòVi API did not become ready.');
}
