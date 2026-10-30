"""Verify atlas completeness and compare original scenes to recomposed assets."""
from pathlib import Path
import json
from PIL import Image, ImageChops, ImageDraw
root=Path('/workspace/analysis/extracted')
world=json.loads((root/'world.json').read_text())
by_id={r['id']:r for r in world['rooms']+world['vehicle_interiors']}
for i in range(1,91):
    for kind in ('room','background'):
        p=root/'rooms-lit'/f'{kind}-{i:02d}.png'
        if Image.open(p).size!=(160,80): raise RuntimeError(p)
report=[]
for i in range(1,91):
    r=by_id[i]
    canvas=Image.open(root/'rooms-lit'/f'background-{i:02d}.png').convert('RGBA')
    drawn=set()
    for row,cells in enumerate(r['objects_grid']):
        for col,obj in enumerate(cells):
            if obj not in (0,255) and (obj not in drawn or obj==48):
                canvas.alpha_composite(Image.open(root/'objects'/f'object-{obj:02d}.png'),(col*16,16+row*16))
                drawn.add(obj)
    original=Image.open(root/'rooms-lit'/f'room-{i:02d}.png')
    bbox=ImageChops.difference(canvas.convert('RGB'),original).getbbox()
    report.append({'room':i,'exact_match':bbox is None,'difference_bbox':bbox,
                   'initial_enemy_count':r['record_prefix'][6]})
(root/'composition-validation.json').write_text(json.dumps(report,indent=2))

# Contact sheet for visual QA of the extracted original scenes (no invented artwork).
sheet=Image.new('RGB',(8*168,12*100),(24,24,24)); draw=ImageDraw.Draw(sheet)
for i in range(1,91):
    x=(i-1)%8*168+4;y=(i-1)//8*100
    draw.text((x,y+2),f'{i:02d}',fill=(255,255,255))
    sheet.paste(Image.open(root/'rooms-lit'/f'room-{i:02d}.png'),(x,y+17))
sheet.save(root/'rooms-contact-sheet.png')
sprites=Image.new('RGBA',(10*76,8*60),(40,40,40,255));label=ImageDraw.Draw(sprites)
for i in range(1,76):
    x=(i-1)%10*76;y=(i-1)//10*60
    label.text((x+2,y+2),str(i),fill='white')
    sprites.alpha_composite(Image.open(root/'objects'/f'object-{i:02d}.png'),(x+2,y+18))
sprites.convert('RGB').save(root/'objects-contact-sheet.png')
print('90 scenes + 90 backgrounds verified; 75 object IDs exported.')
print('Exact background+object compositions:',sum(r['exact_match'] for r in report),'/90')
print('Mismatches:',[r['room'] for r in report if not r['exact_match']])
assert all(r['exact_match'] for r in report), 'Original room reconstruction differs'
