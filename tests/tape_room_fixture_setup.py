"""Prepare an isolated fixture. Pass an existing unique directory inside tests/.tmp/."""
from pathlib import Path
import json,sqlite3,re,wave,math,struct,subprocess,sys,hashlib,base64,time
root=Path(__file__).resolve().parent.parent
scratch=Path(sys.argv[1]).resolve()
if not scratch.is_relative_to(root/'tests/.tmp') or scratch==root/'tests/.tmp':raise ValueError('Use a task directory under tests/.tmp/.')
if (scratch/'fixture.sqlite').exists():raise ValueError('Fixture database already exists.')
scratch.mkdir(parents=True,exist_ok=True)
conn=sqlite3.connect(scratch/'fixture.sqlite')
conn.executescript('''
CREATE TABLE site_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT, updated_at TEXT);
CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, login TEXT, email TEXT, password TEXT, role TEXT, avatar TEXT, locale TEXT, admin_locale TEXT, session_version INTEGER DEFAULT 1, two_factor_secret TEXT, two_factor_enabled INTEGER DEFAULT 0, two_factor_recovery_codes TEXT, two_factor_reset_notice_at TEXT);
CREATE TABLE user_roles (id INTEGER PRIMARY KEY,slug TEXT,rank INTEGER,name TEXT,is_admin INTEGER);
CREATE TABLE security_logs (id INTEGER PRIMARY KEY,actor_user_id INTEGER,target_user_id INTEGER,event TEXT,result TEXT,reason TEXT,ip_address TEXT,user_agent TEXT,created_at TEXT);
CREATE TABLE pages (id INTEGER PRIMARY KEY,title TEXT,menu_title TEXT,slug TEXT,menu_order INTEGER,is_published INTEGER,show_in_legal_information INTEGER);
''')
for file in sorted((root/'plugins/reel-player/migrations').glob('*.sql')):
    for table,ddl in re.findall(r'CREATE TABLE IF NOT EXISTS (\w+) \(\n(.*?)\n\) ENGINE',file.read_text(),re.S):
        columns=[]
        for line in ddl.splitlines():
            line=line.strip().rstrip(',')
            if line.startswith(('KEY ','UNIQUE KEY ','CONSTRAINT ','PRIMARY KEY ')):continue
            name=line.split()[0];kind='REAL' if 'DECIMAL' in line else 'INTEGER' if re.search(r'\b(?:BIGINT|INT|TINYINT)',line) else 'TEXT'
            definition=f'"{name}" {kind}'
            if name=='id':definition+=' PRIMARY KEY'
            default=re.search(r"DEFAULT ('[^']*'|[0-9]+)",line)
            if default:definition+=' DEFAULT '+default[1]
            columns.append(definition)
        conn.execute(f'CREATE TABLE {table} ({", ".join(columns)})')
for definition in ["source TEXT DEFAULT 'local'",'source_id TEXT','favorite INTEGER DEFAULT 0','chapters_revision INTEGER DEFAULT 0']:conn.execute('ALTER TABLE reel_tracks ADD COLUMN '+definition)
for file in sorted((root/'plugins/reel-player/migrations').glob('*.sql')):
    for table,name,definition in re.findall(r'ALTER TABLE (\w+) ADD COLUMN (\w+) ([^;]+);',file.read_text()):
        if name in [row[1] for row in conn.execute(f'PRAGMA table_info({table})')]:continue
        kind='REAL' if 'DECIMAL' in definition else 'INTEGER' if re.search(r'\b(?:BIGINT|INT|TINYINT)',definition) else 'TEXT'
        default=re.search(r"DEFAULT ('[^']*'|[0-9]+)",definition)
        conn.execute(f'ALTER TABLE {table} ADD COLUMN {name} {kind}'+(' DEFAULT '+default[1] if default else ''))
seed=(root/'plugins/reel-player/migrations/009_genre_library.sql').read_text()
conn.executemany('INSERT INTO reel_genres(name,name_key,slug,created_at,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)',re.findall(r"\('([^']+)','([a-f0-9]{64})','([^']+)',NOW\(\),NOW\(\)\)",seed))
conn.execute('CREATE UNIQUE INDEX fixture_track_stats ON reel_track_stats(track_id)')
conn.execute('CREATE UNIQUE INDEX fixture_playback_owner ON reel_playback_state(owner_id)')
settings={'active_theme':'tape-room','homepage_type':'posts','site_title':'Tape Room','site_locale':'ru','pwa_enabled':'0','cookie_enabled':'0','session_lifetime':'43200'}
conn.executemany('INSERT INTO site_settings VALUES (?, ?, CURRENT_TIMESTAMP)',settings.items())
hashed=subprocess.check_output(['/Applications/MAMP/bin/php/php8.2.0/bin/php','-r','echo password_hash("TapeRoomFixture42!",PASSWORD_DEFAULT);']).decode()
conn.executemany('INSERT INTO users (id,name,login,email,role,locale,admin_locale,password) VALUES (?,?,?,?,?,?,?,?)',[(1,'Создатель','creator','creator@example.test','creator','ru','ru',hashed),(2,'Слушатель','listener','listener@example.test','user','ru','ru',hashed),(3,'Администратор','admin','admin@example.test','admin','ru','ru',hashed)])
conn.executemany('INSERT INTO user_roles VALUES (?,?,?,?,?)',[(1,'creator',100,'Создатель',1),(2,'user',0,'Пользователь',0),(3,'admin',90,'Администратор',1)])
(scratch/'storage/reel-player/2').mkdir(parents=True,exist_ok=True)
audio=scratch/'storage/reel-player/2'/('a'*40+'.wav')
with wave.open(str(audio),'wb') as wav:
    wav.setparams((1,2,8000,0,'NONE','not compressed'))
    wav.writeframes(b''.join(struct.pack('<h',int(1200*math.sin(2*math.pi*440*i/8000))) for i in range(8000*12)))
conn.executemany('INSERT INTO reel_tracks (id,owner_id,title,artist,filename,file_path,mime,file_size,duration,created_at,cover_path,source) VALUES (?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,?,?)',[(1,2,'Проверка воспроизведения','Tape Room','test.wav','2/'+'a'*40+'.wav','audio/wav',audio.stat().st_size,12,None,'local'),(2,1,'Закрытая запись','Tape Room','private.wav','1/'+'b'*40+'.wav','audio/wav',100,12,None,'local'),(3,1,'Общий альбом','Tape Room','shared.wav','1/'+'c'*40+'.wav','audio/wav',100,12,None,'local')])
conn.execute("INSERT INTO reel_albums (id,owner_id,title,artist,description,published,created_at,updated_at) VALUES (1,1,'Общий альбом','Tape Room','',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
conn.execute("UPDATE reel_albums SET moderation_status='approved',genre_id=2 WHERE id=1")
(scratch/'storage/reel-player/1').mkdir(parents=True,exist_ok=True)
(scratch/'storage/reel-player/1'/('c'*40+'.wav')).write_bytes(audio.read_bytes())
conn.execute('INSERT INTO reel_album_tracks VALUES (1,3,0)')
conn.execute("INSERT INTO reel_playlists (id,owner_id,name,created_at) VALUES (1,1,'Чужая лента',CURRENT_TIMESTAMP)")
# A Drive metadata fixture uses the real versioned prefix cache, without network calls.
app={'client_id':'fixture.apps.googleusercontent.com','client_secret':'fixture-secret'}
(scratch/'storage/reel-player/drive-app.json').write_text(json.dumps(app))
(scratch/'storage/reel-player/2/drive.json').write_text(json.dumps({'access_token':'fixture-token','expires_at':time.time()+3600}))
def frame(name,value):return name.encode()+struct.pack('>I',len(value))+b'\0\0'+value
tag=b''.join(frame(name,b'\x03'+value.encode()) for name,value in [('TIT2','Embedded title'),('TPE1','Embedded artist'),('TALB','Tagged album'),('TPE2','Album artist'),('TCON','Rock'),('TLEN','12000')])
cover=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jr9sAAAAASUVORK5CYII=')
tag+=frame('APIC',b'\x03image/png\0\x03\0'+cover)
length=len(tag);sync=bytes([(length>>21)&127,(length>>14)&127,(length>>7)&127,length&127])
media=b'ID3\x03\0\0'+sync+tag+b'\xff\xfb\x90\0'+b'\0'*830
file={'id':'fixture-drive-01','name':'Filename artist - Filename title.mp3','mimeType':'audio/mpeg','size':len(media),'headRevisionId':'fixture-revision','capabilities':{'canDownload':True}}
binding=hashlib.sha256((app['client_id']+'\0'+app['client_secret']).encode()).hexdigest()
(scratch/'storage/reel-player/2/drive.json').write_text(json.dumps({'access_token':'fixture-token','expires_at':time.time()+3600,'app_binding':binding}))
account=hashlib.sha256((binding+':fixture-token').encode()).hexdigest();key=hashlib.sha256((account+':'+file['id']).encode()).hexdigest()
cache=scratch/'storage/reel-player/2/drive-cache';cache.mkdir(parents=True,exist_ok=True)
(cache/(key+'.meta')).write_text(json.dumps(file));(cache/(key+'.bin')).write_bytes(media)
version=hashlib.sha256(json.dumps([file['id'],file['size'],file['headRevisionId'],'',''],separators=(',',':')).encode()).hexdigest()
(cache/(key+'.info')).write_text(json.dumps({'version':version}))
conn.execute("INSERT INTO reel_tracks(id,owner_id,title,artist,filename,mime,file_size,duration,source,source_id,created_at) VALUES(4,2,?,?,?,?,?,0,'drive',?,CURRENT_TIMESTAMP)",['Filename title','Filename artist',file['name'],file['mimeType'],file['size'],file['id']])
conn.commit();conn.close()
for folder in ['views/layouts','views/themes/default/admin','sessions','uploads','cache']:(scratch/folder).mkdir(parents=True,exist_ok=True)
(scratch/'views/layouts/default.php').write_text('<?php echo $this->content;')
(scratch/'views/themes/default/admin/shell_open.php').write_text('<main><h1><?= htmlSC($title) ?></h1><p><?= htmlSC($subtitle) ?></p>')
(scratch/'views/themes/default/admin/shell_close.php').write_text('</main>')
print(scratch)
(scratch/'avatar.png').write_bytes((root/'themes/tape-room/assets/images/apple-touch-icon.png').read_bytes())
(scratch/'bad-avatar.svg').write_text('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')
