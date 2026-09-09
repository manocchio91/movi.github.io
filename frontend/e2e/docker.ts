import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

export const root = fileURLToPath(new URL('../../', import.meta.url));
export const composeArgs = ['compose', '-p', 'movi-e2e', '-f', 'docker-compose.yml', '-f', 'frontend/e2e/docker-compose.e2e.yml'];

export function compose(...args: string[]) {
  execFileSync('docker', [...composeArgs, ...args], { cwd: root, stdio: 'inherit' });
}
