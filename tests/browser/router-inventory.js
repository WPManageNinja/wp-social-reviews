'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const repoRoot = path.resolve(__dirname, '..', '..');
const routerFile = path.join(repoRoot, 'resources/admin/router/routes.js');

function normalizePath(parent, child) {
    if (child.startsWith('/')) {
        return child === '/' ? '/' : child.replace(/\/$/, '') || '/';
    }
    const joined = `${parent.replace(/\/$/, '')}/${child}`.replace(/\/{2,}/g, '/');
    return joined === '' ? '/' : joined.replace(/\/$/, '') || '/';
}

function collectRouterScreens() {
    let source = fs.readFileSync(routerFile, 'utf8');
    const imports = {};

    source = source.replace(
        /^import\s+(\w+)\s+from\s+['"]([^'"]+)['"];?\s*$/gm,
        (match, component, request) => {
            imports[component] = request;
            return `const ${component} = ${JSON.stringify(component)};`;
        }
    );
    source = source.replace(/export\s+const\s+routes\s*=/, 'const routes =');

    const sandbox = {};
    vm.runInNewContext(
        `${source}\n;globalThis.__wpsrRoutes = routes;`,
        sandbox,
        { filename: routerFile, timeout: 1000 }
    );

    const screens = [];
    const walk = (routes, parentPath = '') => {
        for (const route of routes) {
            const fullPath = normalizePath(parentPath, route.path);
            if (Array.isArray(route.children) && route.children.length) {
                walk(route.children, fullPath);
                continue;
            }
            const component = String(route.component || '');
            const request = imports[component] || '';
            let componentFile = '';
            if (request) {
                componentFile = path.resolve(path.dirname(routerFile), request);
                if (!path.extname(componentFile)) {
                    componentFile += '.vue';
                }
                componentFile = path.relative(repoRoot, componentFile).replace(/\\/g, '/');
            }
            screens.push({
                name: String(route.name || ''),
                path: fullPath,
                component,
                componentFile,
                title: route.meta && route.meta.title ? String(route.meta.title) : '',
            });
        }
    };

    walk(sandbox.__wpsrRoutes);
    return screens;
}

module.exports = { collectRouterScreens, repoRoot, routerFile };
