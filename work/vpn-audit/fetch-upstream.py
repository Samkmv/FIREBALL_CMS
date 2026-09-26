import urllib.request,json,concurrent.futures
from pathlib import Path
base=Path(__file__).parent/'upstream'
files=['frontend/public/openapi.json','internal/web/service/client.go','internal/web/service/client_hwid.go','internal/web/controller/client.go','internal/web/model/client.go','internal/sub/controller.go','internal/sub/hwid.go','internal/web/service/inbound.go']
def get(name):
 try:
  data=urllib.request.urlopen('https://raw.githubusercontent.com/MHSanaei/3x-ui/v3.8.5/'+name,timeout=30).read()
  path=base/name;path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(data)
  return name+': saved'
 except Exception as e:return name+': '+str(e)
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
 for result in pool.map(get,files):print(result)
