#!/usr/bin/env python3
"""One bounded HTTP request; no concurrency, retries, redirects or auth bypass."""
import argparse
import json
import os
import re
import subprocess
import tempfile
from urllib.parse import urlsplit

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('base_url')
parser.add_argument('path')
parser.add_argument('--method', choices=['GET', 'POST'], default='GET')
parser.add_argument('--data-file', help='Explicit POST body file; writes only when requested')
parser.add_argument('--cookie-file', help='Existing curl cookie jar for authenticated routes')
args = parser.parse_args()
if urlsplit(args.base_url).scheme not in ('http', 'https') or not args.path.startswith('/'):
    parser.error('Use an http(s) base URL and an absolute route path')
if args.data_file and args.method != 'POST':
    parser.error('--data-file requires --method POST')
with tempfile.NamedTemporaryFile() as headers:
    command = ['curl', '--silent', '--show-error', '--max-time', '15', '--max-redirs', '0',
               '--output', os.devnull, '--dump-header', headers.name, '--write-out', '%{json}',
               '--request', args.method, '--url', args.base_url.rstrip('/') + args.path]
    if args.cookie_file:
        command += ['--cookie', args.cookie_file]
    if args.data_file:
        command += ['--header', 'Content-Type: application/json', '--data-binary', '@' + args.data_file]
    result = subprocess.run(command, capture_output=True, text=True, timeout=20)
    if result.returncode:
        raise SystemExit(result.stderr.strip())
    timing = json.loads(result.stdout)
    raw_headers = headers.read().decode('iso-8859-1')
    server_timing = ','.join(re.findall(r'^Server-Timing:\s*(.*)$', raw_headers, re.I | re.M))
    counts = dict((key, int(value)) for key, value in re.findall(r'(\w+);desc="(\d+)"', server_timing))
    print(json.dumps({'route': args.path, 'method': args.method, 'status': timing['http_code'],
                     'ttfb_ms': timing['time_starttransfer'] * 1000,
                     'total_ms': timing['time_total'] * 1000, 'response_bytes': timing['size_download'],
                     'profiler': counts if server_timing else None}, ensure_ascii=False))
