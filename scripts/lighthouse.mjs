#!/usr/bin/env node
/**
 * Lighthouse CI against a running site (defaults to the Playground URL in
 * lighthouserc.json). Budgets are advisory: the accessibility score is the only
 * hard assertion.
 *
 *   npm run lighthouse [-- --url http://site.local]
 */
import { parseArgs, run } from './lib.mjs';

const options = parseArgs(process.argv.slice(2));
const args = ['--yes', '@lhci/cli@0.15', 'autorun', '--config=lighthouserc.json'];

if (options.url) {
  const base = String(options.url).replace(/\/$/, '');
  args.push(`--collect.url=${base}/`);
}

process.exit(run('npx', args, { inherit: true }).status);
