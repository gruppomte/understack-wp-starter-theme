import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { existsSync, globSync, mkdirSync, unlinkSync, writeFileSync } from 'node:fs';
import { dirname, relative, resolve } from 'node:path';

const devServer = { host: 'localhost', port: 5173 };
const devOrigin = `http://${devServer.host}:${devServer.port}`;
const hotFile = resolve('public/hot');

/**
 * While `vite` runs, public/hot holds the dev server origin so WordPress can load
 * assets from it. The file is removed when the server stops and is never part of
 * a build or release.
 */
function hotFilePlugin() {
  const remove = () => {
    if (existsSync(hotFile)) {
      unlinkSync(hotFile);
    }
  };

  return {
    name: 'theme:hot-file',
    apply: 'serve',
    configureServer(server) {
      server.httpServer?.once('listening', () => {
        mkdirSync(dirname(hotFile), { recursive: true });
        writeFileSync(hotFile, devOrigin);
      });
      server.httpServer?.once('close', remove);
      // Vite does not run exit handlers on Ctrl+C by default: make sure the hot
      // file never outlives the server.
      process.once('exit', remove);
      for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP']) {
        process.once(signal, () => process.exit());
      }
    },
  };
}

/**
 * Twig templates and PHP files are not part of Vite's module graph, so editing
 * them does not refresh the page on its own: reload the browser when they change.
 */
function reloadOnTemplateChange() {
  const watched = ['views', 'app', 'blocks', 'functions.php', 'index.php'].map((path) =>
    resolve(path),
  );
  const isTemplate = (file) =>
    /\.(twig|php|json)$/.test(file) && watched.some((path) => file.startsWith(path));

  return {
    name: 'theme:reload-templates',
    apply: 'serve',
    configureServer(server) {
      server.watcher.add(watched);

      for (const event of ['add', 'change', 'unlink']) {
        server.watcher.on(event, (file) => {
          if (isTemplate(file)) {
            server.config.logger.info(`page reload ${relative(process.cwd(), file)}`, {
              timestamp: true,
            });
            server.ws.send({ type: 'full-reload', path: '*' });
          }
        });
      }
    },
  };
}

/**
 * Every block in blocks/<name>/ may ship style.css, editor.css and view.js; they
 * are built as separate entries and attached to the block by app/Blocks/Blocks.php.
 */
function blockEntries() {
  return Object.fromEntries(
    globSync('blocks/*/{style.css,editor.css,view.js}').map((file) => [
      file.replace(/\//g, '-').replace(/\.(css|js)$/, ''),
      file,
    ]),
  );
}

export default defineConfig({
  base: './',
  publicDir: false,
  plugins: [tailwindcss(), hotFilePlugin(), reloadOnTemplateChange()],
  server: {
    ...devServer,
    strictPort: true,
    origin: devOrigin,
    // WordPress is served from another origin (for example LocalWP).
    cors: true,
  },
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    manifest: true,
    sourcemap: false,
    rolldownOptions: {
      input: {
        app: 'src/js/app.js',
        styles: 'src/css/app.css',
        editor: 'src/css/editor.css',
        woocommerce: 'src/css/woocommerce.css',
        ...blockEntries(),
      },
    },
  },
});
