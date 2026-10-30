"""Package locally extracted CPC resources. Originals stay in ignored assets/original.

Run in the CPC analysis container with the parent workspace mounted at /workspace.
See README.md for the complete reproducible disc-extraction procedure.
"""
import json,sys,subprocess
from pathlib import Path
from PIL import Image,ImageDraw
root=Path('/workspace');source=root/'analysis/extracted'
game=root/'zombi-ubisoft-1986-php/games/zombi-ubisoft-1986-php'
out=game/'assets/original';out.mkdir(parents=True,exist_ok=True)
sys.path.insert(0,str(root/'dsk2jumphp/tools'))
from cpc import Dump,graphics
d=Dump(root/'analysis/runs/direction-3/dump-08900.bin')
world=json.loads((source/'world.json').read_text())
observed=json.loads((source/'observations.json').read_text())
objects=json.loads((source/'objects/objects.json').read_text())
atlas=Image.new('RGB',(1600,720))
for room in range(1,91):
    atlas.paste(Image.open(source/f'rooms-lit/background-{room:02d}.png'),(((room-1)%10)*160,((room-1)//10)*80))
atlas.save(out/'rooms.png')
sprites=Image.new('RGBA',(320,256),(0,0,0,0))
for entry in objects:
    i=entry['id']-1;entry['x']=i%10*32;entry['y']=i//10*32
    sprites.alpha_composite(Image.open(source/'objects'/entry['file']),(entry['x'],entry['y']))
sprites.save(out/'objects.png')
Image.open(source/'actions-atlas.png').save(out/'actions.png')
portraits=Image.new('RGB',(288,32))
for i in range(9):
    address=0x4901+i*256
    portraits.paste(graphics(d.mem,address,8,32,1,mode=1,rgb=d.rgb,gap=0),(i*32,0))
portraits.save(out/'portraits.png')
frame=d.screen()
draw=ImageDraw.Draw(frame)
for box,color in [((8,48,167,127),(128,128,128)),((224,0,319,15),(128,128,128)),
                  ((224,16,255,55),(128,128,128)),((224,64,319,111),(128,128,128)),
                  ((240,116,287,123),(0,0,0)),((100,140,219,151),(0,0,0)),
                  ((0,156,319,199),(0,0,0))]:draw.rectangle(box,fill=color)
frame.save(out/'frame.png')
Image.open(root/'analysis/runs/rom-boot/screen-03000.png').save(out/'title.png')
for name in ('victory','defeat'):Image.open(source/f'ending-{name}.png').save(out/f'{name}.png')
rom=(root/'ROM CPC 6128 OS + BASIC et AMSDOS/CPC6128.ROM').read_bytes()
assert rom[0x3800+65*8:0x3800+66*8]==bytes([24,60,102,102,126,102,102,0])
for name,color in [('black',(0,0,0)),('white',(255,255,255)),('blue',(0,128,255)),('red',(255,0,0))]:
    font=Image.new('RGBA',(128,128),(0,0,0,0));pixels=font.load()
    for ch in range(256):
        for y,b in enumerate(rom[0x3800+ch*8:0x3800+(ch+1)*8]):
            for x in range(8):
                if b&(128>>x):pixels[ch%16*8+x,ch//16*8+y]=color+(255,)
    font.save(out/f'font-{name}.png')
containers={}
for obj in range(22,33):
    p=int.from_bytes(d.mem[0xa26c+(obj-22)*2:0xa26e+(obj-22)*2],'little')
    count=d.mem[p];containers[str(obj)]=list(d.mem[p+1:p+1+count])
data={'format':1,'world':world,'characters':observed['characters'],'objects':objects,
      'containers':containers,'screen':[320,200],'source_commit':'7df03ec2b1e75765a5fcd5e744175292d914e90d'}
(out/'game.json').write_text(json.dumps(data,separators=(',',':')),encoding='utf-8')
subprocess.run(['ffmpeg','-y','-loglevel','error','-i',str(root/'analysis/zombi-intro.wav'),'-c:a','libvorbis','-q:a','4',str(out/'intro.ogg')],check=True)
print('Packaged original resources:',sum(p.stat().st_size for p in out.iterdir()),'bytes')
