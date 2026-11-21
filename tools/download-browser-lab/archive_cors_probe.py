import sys, subprocess, re, urllib.parse, json, collections
# Normalise exactly like ArchiveOrgLink::normalize (download/items → https://archive.org/download/{folder}/{file}).
def norm(u):
    p = urllib.parse.urlsplit(u.strip())
    segs = [s for s in p.path.split('/') if s]
    for i, s in enumerate(segs):
        if s in ('items', 'download') and i + 2 <= len(segs) - 1 + 1 and len(segs) > i + 2:
            return 'https://archive.org/download/' + '/'.join(segs[i+1:]) + (('?' + p.query) if p.query else '')
    return u.strip()
rows = []
for line in open(sys.argv[1], encoding='utf-8'):
    mod, link = line.rstrip('\n').split('\t', 1)
    u = norm(link)
    u = urllib.parse.quote(u, safe=':/?=&%')
    out = subprocess.run(['curl', '-s', '-m', '30', '-o', '/dev/null', '-D', '-', '-r', '0-0', '-H', 'Origin: http://127.0.0.1:8000', '-L', u], capture_output=True, text=True).stdout.replace('\r', '')
    blocks = [b for b in out.split('\n\n') if b.strip()]
    final = blocks[-1] if blocks else ''
    status = (re.search(r'^HTTP/\S+ (\d+)', final, re.M) or [None, 'none'])[1]
    acao = bool(re.search(r'^access-control-allow-origin:', final, re.I | re.M))
    hosts = re.findall(r'^location: https?://([^/]+)', out, re.I | re.M)
    node = hosts[-1] if hosts else 'archive.org'
    kind = 'dn*.ca' if node.startswith('dn') else ('ia*.us' if node.startswith('ia') else node)
    rows.append({'module': mod, 'ext': u.rsplit('.', 1)[-1].lower(), 'status': status, 'final_cors': acao, 'node_kind': kind})
c = collections.Counter((r['status'], r['node_kind'], r['final_cors']) for r in rows)
print(json.dumps({'n': len(rows), 'by_status_node_cors': {f'{k[0]} {k[1]} cors={k[2]}': v for k, v in sorted(c.items())},
                  'ok_with_cors': sum(1 for r in rows if r['final_cors'] and r['status'] in ('200', '206')),
                  'ok_total': sum(1 for r in rows if r['status'] in ('200', '206'))}, indent=1))
json.dump(rows, open(sys.argv[2], 'w'))
