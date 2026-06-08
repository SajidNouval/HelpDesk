#!/usr/bin/env python3
import os, re, glob, json, pathlib

root = os.path.abspath(os.getcwd())
output_path = os.path.join(root, 'tools', 'ref_analysis_output.json')

categories = {
    'controllers': glob.glob(os.path.join(root, 'app', 'Http', 'Controllers', '**', '*.php'), recursive=True),
    'services': glob.glob(os.path.join(root, 'app', 'Services', '**', '*.php'), recursive=True),
    'middleware': glob.glob(os.path.join(root, 'app', 'Http', 'Middleware', '**', '*.php'), recursive=True),
    'events': glob.glob(os.path.join(root, 'app', 'Events', '**', '*.php'), recursive=True),
    'commands': glob.glob(os.path.join(root, 'app', 'Console', 'Commands', '**', '*.php'), recursive=True),
    'models': glob.glob(os.path.join(root, 'app', 'Models', '**', '*.php'), recursive=True),
    'seeders': glob.glob(os.path.join(root, 'database', 'seeders', '**', '*.php'), recursive=True),
    'migrations': glob.glob(os.path.join(root, 'database', 'migrations', '**', '*.php'), recursive=True),
    'views': glob.glob(os.path.join(root, 'resources', 'views', '**', '*.blade.php'), recursive=True),
    'js': glob.glob(os.path.join(root, 'resources', 'js', '**', '*.js'), recursive=True),
    'css': glob.glob(os.path.join(root, 'resources', 'css', '**', '*.css'), recursive=True),
    'routes': glob.glob(os.path.join(root, 'routes', '*.php'))
}

# Collect readable files (for reference scanning)
files = []
for dirpath, dirnames, filenames in os.walk(root):
    if any(x in dirpath for x in [os.sep + 'vendor', os.sep + 'node_modules', os.sep + 'storage', os.sep + '.git']):
        continue
    for fn in filenames:
        if fn.endswith(('.php', '.blade.php', '.js', '.css', '.json')):
            files.append(os.path.join(dirpath, fn))

text = {}
for f in files:
    try:
        with open(f, 'r', encoding='utf-8') as fh:
            text[f] = fh.read()
    except Exception:
        text[f] = ''


def get_class(p):
    if not p.endswith('.php'):
        return None
    try:
        c = open(p, 'r', encoding='utf-8').read()
    except Exception:
        return None
    ns = re.search(r'namespace\s+([^;]+);', c)
    cls = re.search(r'class\s+([A-Za-z0-9_]+)', c)
    if ns and cls:
        return ns.group(1) + '\\' + cls.group(1)
    return cls.group(1) if cls else None


def find_usages(name, paths_to_ignore=set()):
    patterns = []
    # match as word
    patterns.append(r'\b' + re.escape(name) + r'\b')
    # ::class usage
    patterns.append(re.escape(name) + r"::class")
    # typical Laravel controller@method pattern
    patterns.append(re.escape(name) + r'@')
    usages = []
    for f, cont in text.items():
        if f in paths_to_ignore:
            continue
        for pat in patterns:
            if re.search(pat, cont):
                usages.append(f)
                break
    return usages

out = {}
for cat, paths in categories.items():
    arr = []
    for p in sorted(set(paths)):
        name = os.path.splitext(os.path.basename(p))[0]
        cls = get_class(p)
        search_keys = set()
        search_keys.add(name)
        if cls:
            search_keys.add(cls)
            search_keys.add(cls.split('\\')[-1])
        usages = []
        for key in search_keys:
            usages.extend(find_usages(key, paths_to_ignore={p}))
        usages = sorted(set(usages))
        arr.append({'path': p, 'class': cls, 'name': name, 'refs': len(usages), 'sample_refs': usages[:12]})
    out[cat] = arr

# Additional analysis: routes -> controllers; kernel middleware map; JS import graph; view usages
# Parse routes for controller references and view() calls
routes_controllers = {}
for r in categories.get('routes', []):
    try:
        c = open(r, 'r', encoding='utf-8').read()
    except Exception:
        continue
    controllers = re.findall(r"\b([A-Za-z0-9_\\]+)::class", c)
    at_controllers = re.findall(r"\b([A-Za-z0-9_\\]+)/?([A-Za-z0-9_]+)@([A-Za-z0-9_]+)", c)
    at_simple = re.findall(r"['\"]([A-Za-z0-9_/\\]+@[A-Za-z0-9_]+)['\"]", c)
    ctrl_list = []
    for ctrl in controllers:
        ctrl_list.append(ctrl)
    for m in at_controllers:
        ctrl_list.append(''.join(m))
    for s in at_simple:
        ctrl_list.append(s)
    routes_controllers[r] = sorted(set(ctrl_list))

# Kernel middleware mapping
kernel_path = os.path.join(root, 'app', 'Http', 'Kernel.php')
middleware_map = {}
if os.path.exists(kernel_path):
    try:
        ktxt = open(kernel_path, 'r', encoding='utf-8').read()
        # naive parse of protected $routeMiddleware = [ 'name' => Class::class ]
        matches = re.findall(r"['\"]([A-Za-z0-9_]+)['\"]\s*=>\s*([A-Za-z0-9_\\:]+)::class", ktxt)
        for name, cls in matches:
            middleware_map[name] = cls
    except Exception:
        pass

# JS import graph (basic): for each js file, collect import/require strings
js_imports = {}
for p in out.get('js', []):
    path = p['path']
    try:
        content = open(path, 'r', encoding='utf-8').read()
    except Exception:
        content = ''
    imps = re.findall(r"import\s+[^'\"]+['\"]([^'\"]+)['\"]", content)
    reqs = re.findall(r"require\(['\"]([^'\"]+)['\"]\)", content)
    js_imports[path] = {'imports': imps, 'requires': reqs}

# View usages: search for view('name') and convert dot notation to path
view_calls = {}
view_call_re = re.compile(r"view\s*\(\s*['\"]([A-Za-z0-9_\.\-/]+)['\"]")
for f, cont in text.items():
    for m in view_call_re.findall(cont):
        # map m like 'admin.index' -> resources/views/admin/index.blade.php
        candidate = m.replace('.', os.sep) + '.blade.php'
        possible = []
        # try direct and with 'resources/views/'
        possible.append(os.path.join(root, 'resources', 'views', candidate))
        # also consider if m contains slashes already
        if os.sep in m:
            possible.append(os.path.join(root, 'resources', 'views', m + '.blade.php'))
        view_calls.setdefault(m, []).append({'from': f, 'resolved': [p for p in possible if os.path.exists(p)]})

report = {'root': root, 'summary': {k: len(v) for k, v in out.items()}, 'details': out,
          'routes_controllers': routes_controllers, 'kernel_middleware': middleware_map,
          'js_imports': js_imports, 'view_calls': view_calls}

# write output
try:
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    with open(output_path, 'w', encoding='utf-8') as fh:
        json.dump(report, fh, indent=2)
    print(output_path)
except Exception as e:
    print('ERROR', str(e))
