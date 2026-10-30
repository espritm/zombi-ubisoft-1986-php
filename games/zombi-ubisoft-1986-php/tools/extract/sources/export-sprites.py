"""Decode the original object pointer table with upstream CPC graphics decoding."""
from pathlib import Path
import json,sys
from PIL import Image,ImageChops
sys.path.insert(0,'/workspace/dsk2jumphp/tools')
from cpc import Dump,graphics
root=Path('/workspace/analysis'); out=root/'extracted/objects';out.mkdir(exist_ok=True)
d=Dump(root/'runs/direction-3/dump-08900.bin')
def entry_for(obj):
    # Literal mapping from #90BE–#90F0 (many IDs share one graphic).
    if obj<4: n=1
    elif obj<14: n=obj-2
    elif obj<17: n=12
    elif obj<29: n=obj-4
    elif obj<33: n=25
    elif obj<58: n=obj-7
    else: n=obj-9
    return n-1
manifest=[]
for obj in range(1,76):
    p=0x7a0a+4*entry_for(obj)
    address=int.from_bytes(d.mem[p:p+2],'little');w=d.mem[p+2]*4;h=d.mem[p+3]*16
    if not w or not h or w>32 or h>64:
        raise RuntimeError((obj,hex(p),address,w,h))
    img=graphics(d.mem,address,w,h,1,mode=1,rgb=d.rgb,gap=0).convert('RGBA')
    masked=27<=obj<56 or obj==73
    if masked:
        pixels=img.load(); transparent=d.rgb(0)
        for y in range(img.height):
            for x in range(img.width):
                if pixels[x,y][:3]==transparent: pixels[x,y]=(0,0,0,0)
    img.save(out/f'object-{obj:02d}.png')
    manifest.append({'id':obj,'source_address':hex(address),'table_address':hex(p),
        'width':img.width,'height':h,'transparent_pen0':masked,'file':f'object-{obj:02d}.png'})
(out/'objects.json').write_text(json.dumps(manifest,indent=2))

# Validate an observed room: original #901B draws at #C282, row stride #A0,
# i.e. screen (8,64) in 16-pixel cells, relative to crop origin (8,48).
world=json.loads((root/'extracted/world.json').read_text())
room=next(r for r in world['rooms'] if r['id']==38)
canvas=Image.open(root/'extracted/rooms/background-38.png').convert('RGBA')
drawn=set()
for row,cells in enumerate(room['objects_grid']):
    for col,obj in enumerate(cells):
        if obj not in (0,255) and (obj not in drawn or obj==48):
            sprite=Image.open(out/f'object-{obj:02d}.png')
            canvas.alpha_composite(sprite,(col*16,16+row*16))
            drawn.add(obj)
canvas.convert('RGB').save(out/'room-38-rebuilt.png')
diff=ImageChops.difference(canvas.convert('RGB'),Image.open(root/'extracted/rooms/room-38.png'))
print('Room 38 reconstructed diff bounding box:',diff.getbbox())
if diff.getbbox() is not None:
    raise RuntimeError('Sprite composition does not match original')
print('Exported',len(manifest),'object IDs')
