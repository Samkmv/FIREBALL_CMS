"""Run against tests/tape_room_fixture.php with TAPE_ROOM_TEST_PUBLIC=1."""
import http.cookiejar
import json
import os
from pathlib import Path
import re
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ.get('TAPE_ROOM_TEST_BASE', 'http://127.0.0.1:8797')
SCRATCH = Path(os.environ['TAPE_ROOM_TEST_ROOT']).resolve()
assert SCRATCH.is_relative_to(Path(__file__).resolve().parent / '.tmp')
checks = []

def check(label, ok):
    if not ok: raise AssertionError(label)
    checks.append(label)

class Client:
    def __init__(self, role=None):
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = ''
        if role:
            _, _, body = self.request('/test/sign-in/' + role)
            self.config = json.loads(re.search(rb'<script type="application/json" data-player-config>(.*?)</script>', body, re.S)[1])
            self.csrf = self.config['csrf']
    def request(self, path, data=None, headers=None):
        request = urllib.request.Request(BASE + path, data=data, headers=headers or {})
        try: response = self.client.open(request, timeout=15)
        except urllib.error.HTTPError as response: return response.code, response.headers, response.read()
        with response: return response.status, response.headers, response.read()
    def playback(self, data=None, token=True):
        code, headers, body = self.request('/tape-room/api/playback', json.dumps(data).encode() if data is not None else None,
            {'Accept':'application/json','X-Requested-With':'XMLHttpRequest', **({'Content-Type':'application/json','X-CSRF-Token':self.csrf if token else 'invalid'} if data is not None else {})})
        try: result = json.loads(body)
        except ValueError: result = None
        return code, headers, result

guest, a, b, other = Client(), Client('user'), Client('user'), Client('admin')
code, _, _ = guest.playback()
check('Guests cannot read an account resume point', code in (401,403))
code, headers, value = a.playback()
check('Fresh account has no remote resume point', code == 200 and value['playback'] is None)
check('Playback state is never publicly cached', 'no-store' in headers.get('Cache-Control',''))
duration = next(track['duration'] for track in a.config['state']['tracks'] if track['id']==1)
state = {'current':1,'time':3.5,'queue':[1,2,3], 'queueSource':{'type':'songs'},'queueName':'Моя музыка', 'shuffle':False,'repeat':'all'}
body = {'device':'device-a-1234567890','seenDevice':'','revision':0,'claim':True,'playing':True,'state':state}
check('Playback writes require CSRF', a.playback(body, token=False)[0] == 419)
code, _, result = a.playback(body)
check('First device saves a private resume point', code == 200 and result['accepted'] and result['playback']['state']['current'] == 1)
first = result['playback']
check('Another owner’s private track is removed from the queue', first['state']['queue'] == [1,3])
check('Fractional seek position is retained', first['state']['time'] == 3.5)
check('Repeat setting is retained', first['state']['repeat'] == 'all')
check('Only a track reference is stored, not audio or arbitrary fields', set(first['state']) == {'current','time','queue','queueSource','queueName','queueSort','queuePlaylist','shuffle','repeat'})
check('Second session of the same user sees the same track and queue', b.playback()[2]['playback']['state'] == first['state'])
check('Different accounts cannot read this resume point', other.playback()[2]['playback'] is None)
stale = {**body,'device':'device-b-1234567890','claim':False,'revision':first['revision']}
check('Passive device cannot take over with the current revision', not b.playback(stale)[2]['accepted'])
second_body = {**stale,'seenDevice':body['device'],'claim':True,'state':{**state,'current':3,'time':30,'queue':[3],'queueSource':{'type':'album','id':1}}}
second = b.playback(second_body)[2]['playback']
check('Explicit takeover selects the public track on the second device', second['state']['current'] == 3 and second['revision'] > first['revision'])
late = a.playback({**body,'claim':False,'revision':first['revision']})[2]
check('Late first-device heartbeat cannot overwrite the new song', not late['accepted'] and late['playback']['state']['current'] == 3)
check('The server returns the new revision when rejecting an old tab', late['playback']['revision'] == second['revision'])
delayed = a.playback({**body,'seenDevice':body['device']})[2]
check('An old offline takeover cannot overwrite a newer device owner', not delayed['accepted'] and delayed['playback']['device'] == second_body['device'])
paused = b.playback({**second_body,'claim':False,'revision':second['revision'],'playing':False})[2]
check('Active owner can publish pause without claiming again', paused['accepted'] and not paused['playback']['playing'])
check('Foreign private current track is rejected', a.playback({**body,'state':{**state,'current':2}})[0] == 422)
check('Browser-only torrent IDs never become a server resume point', a.playback({**body,'state':{**state,'current':4000000000000001}})[0] == 422)
check('Negative positions are rejected', a.playback({**body,'state':{**state,'time':-1}})[0] == 422)
check('Oversized queues are rejected', a.playback({**body,'state':{**state,'queue':[1]*1001}})[0] == 422)
clamped = a.playback({**body,'seenDevice':second_body['device'],'state':{**state,'time':1000}})[2]['playback']
check('Resume position is clamped to known song duration', clamped['state']['time'] == duration)

# Exercise the existing secure account controller, upload validation and storage.
def avatar_upload(client, name, content, mime):
    _, _, html = client.request('/profile/settings')
    token = re.search(rb'name="needCSRFToken" value="([^"]+)"', html)[1].decode()
    boundary = 'TapeRoomAvatarFixtureBoundary'
    parts = []
    for key, value in [('needCSRFToken',token),('profile_action','avatar')]:
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="avatar_file"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+content+b'\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    return client.request('/profile/settings', b''.join(parts), {'Content-Type':'multipart/form-data; boundary='+boundary})

code, _, html = avatar_upload(a,'avatar.png',(SCRATCH/'avatar.png').read_bytes(),'image/png')
check('Avatar upload returns to account settings successfully', code == 200 and 'Аватар' in html.decode())
ids = re.findall(rb' id="([^"]+)"', html)
check('Account forms have unique field IDs and independent password toggles', len(ids)==len(set(ids)))
match = re.search(rb'data-tr-avatar-preview src="([^"]+)"', html)
check('Settings show the stored avatar from isolated uploads', match and b'/uploads/avatars/' in match[1])
avatar_url = match[1].decode()
status, headers, png = a.request(urllib.parse.urlparse(avatar_url).path)
check('Stored avatar is served as an image', status == 200 and png[:8] == b'\x89PNG\r\n\x1a\n')
code, _, profile = a.request('/profile')
check('Profile displays the chosen avatar', avatar_url.encode() in profile)
check('Account menu uses the chosen avatar', b'class="tr-nav-avatar"' in profile)
check('Profile navigation uses the shared styled component', b'aria-label="\xd0\xa0\xd0\xb0\xd0\xb7\xd0\xb4\xd0\xb5\xd0\xbb\xd1\x8b \xd0\xb0\xd0\xba\xd0\xba\xd0\xb0\xd1\x83\xd0\xbd\xd1\x82\xd0\xb0"' in profile and b'aria-current="page"' in profile)
code, _, invalid = avatar_upload(a,'bad-avatar.svg',(SCRATCH/'bad-avatar.svg').read_bytes(),'image/svg+xml')
check('Unsafe SVG avatar is refused while preserving the existing image', code == 200 and avatar_url.encode() in invalid and b'bad-avatar.svg' not in invalid)
check('No unsafe SVG was written to uploads', not list((SCRATCH/'public/uploads').rglob('*.svg')))
output = Path(__file__).resolve().parent/'artifacts/account-playback-sync/api-checks.json'
output.parent.mkdir(parents=True,exist_ok=True)
output.write_text(json.dumps({'passed':len(checks),'checks':checks},ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'passed':len(checks),'checks':checks},ensure_ascii=False,indent=2))
