#!/usr/bin/env bash
# Runs the suite and prints a short summary (the agent-friendly JSON output
# of `php artisan test` is long).
cd "$(dirname "$0")/.." && php artisan test "$@" 2>&1 | python3 -c "
import sys, json
for line in sys.stdin:
    try:
        d = json.loads(line)
    except Exception:
        print(line.rstrip()); continue
    print(d.get('result'), d.get('tests'), 'tests,', d.get('passed'), 'passed', ('risky %s' % d['risky']) if d.get('risky') else '')
    for f in d.get('error_details', []) or []:
        m = str(f.get('message', f)); print('ERROR', f.get('test', ''), m[:600])
    for f in d.get('failures', []):
        m = f['message']; print('FAIL', f['test'], m[:300] + (' ... ' + m[-400:] if len(m) > 700 else m[300:]))
"
