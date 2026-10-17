"""Run against the isolated Tape Room fixture; never use a production base URL."""
import http.cookiejar
import json
import os
from pathlib import Path
import re
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ.get('TAPE_ROOM_TEST_BASE', 'http://127.0.0.1:8797')
assert urllib.parse.urlparse(BASE).hostname in ('127.0.0.1', 'localhost')
SCRATCH = Path(os.environ['TAPE_ROOM_TEST_ROOT']).resolve()
assert SCRATCH.is_relative_to(Path(__file__).resolve().parent / '.tmp')
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
checks = []

def request(path, body=None, headers=None):
    try:
        response = client.open(urllib.request.Request(BASE + path, body, headers or {}), timeout=20)
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()
    with response:
        return response.status, response.read().decode()

def check(label, passed):
    assert passed, label
    checks.append(label)

def marker(html):
    import html as html_module
    match = re.search(r'<meta name="tr-site-navigation" content="([^"]+)"', html)
    return json.loads(html_module.unescape(match[1])) if match else None

status, html = request('/test/sign-in/user')
check('Fixture account signs in', status == 200)
assert 'data-test-document=' in html, 'Use the isolated fixture, never the regular CMS installation.'
status, html = request('/profile')
check('Default profile has one replaceable page slot', html.count('data-tr-page ') == 1)
check('The global player is opt-in', 'data-audio ' not in html)
account = marker(html)
check('Theme response identifies its account and home path', account['account'] == 2 and account['home'] == BASE + '/')
jar.set_cookie(http.cookiejar.Cookie(0, 'tr_site_player', '1', None, False,
    urllib.parse.urlparse(BASE).hostname, False, False, '/', True, False, None, False, None, None, {}))
status, html = request('/profile/settings')
check('Preference mounts the player on a direct settings request', status == 200 and 'data-site-page="1"' in html)
check('Exactly one audio element is created', html.count('audio data-audio ') == 1)
check('Global settings response contains just one site navigation', html.count('id="tr-site-menu"') == 1)
check('The player preference is available in the sidebar', 'data-tr-site-player-toggle' in html)
check('Navigation code is loaded once', html.count('/js/site-player.js') == 1)
token = marker(html)['csrf']
check('Fetched form and player use the same CSRF token', token in re.findall(r'name="needCSRFToken" value="([^"]+)"', html))
body = urllib.parse.urlencode({'needCSRFToken':token, 'profile_action':'details',
    'name':'Слушатель — страницы', 'login':'listener', 'email':'listener@example.test', 'current_password':''}).encode()
status, html = request('/profile/settings', body, {'Accept':'text/html'})
check('Native profile controller saves and redirects within the theme', status == 200 and 'Слушатель — страницы' in html)
new_token = marker(html)['csrf']
check('Session rotation returns a fresh CSRF token', new_token != token)
_, current = request('/tape-room/api/playback')
previous = json.loads(current).get('playback') or {}
payload = {'device':'site-player-test-1234','seenDevice':previous.get('device',''),'revision':previous.get('revision',0),'claim':True,'playing':False,
    'state':{'current':1,'time':3.5,'queue':[1],'queueSource':{'type':'songs'},'queueName':'Моя музыка','shuffle':False,'repeat':'off'}}
def write_playback(csrf):
    return request('/tape-room/api/playback', json.dumps(payload).encode(),
        {'Content-Type':'application/json','X-CSRF-Token':csrf,'Accept':'application/json'})
check('The old token remains rejected after saving the profile', write_playback(token)[0] == 419)
status, result = write_playback(new_token)
check('The refreshed token can save playback after profile editing', status == 200 and json.loads(result)['accepted'])
status, html = request('/tape-room/ui-kit')
check('UI gallery is a supported page with the same account', status == 200 and marker(html)['account'] == 2 and 'data-tr-page ' in html)
status, html = request('/test/guest')
check('Sign-out response identifies the guest and removes private tracks', status == 200 and marker(html)['account'] == 0 and '"authenticated":false' in html and 'Слушатель — страницы' not in html)
(SCRATCH/'site-player-http.json').write_text(json.dumps(checks, ensure_ascii=False, indent=2)+'\n')
print(f'{len(checks)} site-player HTTP checks passed')
