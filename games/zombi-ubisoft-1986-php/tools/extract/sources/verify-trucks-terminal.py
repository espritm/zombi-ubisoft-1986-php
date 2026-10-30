"""Verify the original truck state changes and six post-move render compositions."""
from pathlib import Path
from PIL import Image,ImageChops
import json,sys
root=Path('/workspace/analysis');checks=[]
objects={v['id']:v for v in json.loads((root/'extracted/objects/objects.json').read_text())}
def check(name,condition):
    assert condition,name
    checks.append(name)
for source,target,door,blocked,field,target_field in ((72,66,2,31,1,3),(77,65,1,19,0,2),(82,64,3,32,3,1)):
    out=root/f'runs/truck-{source}-key-1'
    states={r['label']:r for r in json.loads((out/'truck-states.json').read_text())}
    a,b=states['contact'],states['driven'];old=a['rooms'][str(source)];new=b['rooms'][str(target)]
    check(f'truck-{source}-transfer',new[10:40]==old[10:40] and new[42:50]==[0]*8
        and b['rooms'][str(source)][10:40]==[0]*30 and b['rooms'][str(source)][9]==0 and new[9]==77)
    check(f'truck-{source}-doors',b['rooms'][str(blocked)][field]==10 and new[target_field]==10)
    check(f'truck-{source}-riders',b['positions'][0]==b['positions'][3]!=a['positions'][3]
        and b['positions'][1]==a['positions'][1] and b['interiors'][0]==door+86 and b['interiors'][1]==(87 if door==3 else 89))
    for room,variant,label in ((source,'empty','source-after'),(target,'parked','outside')):
        canvas=Image.open(root/f'extracted/trucks/background-{room:02d}-{variant}.png').convert('RGBA')
        cells=states[label]['rooms'][str(room)][10:];seen=set()
        for i,obj in enumerate(cells):
            if obj in (0,255) or (obj in seen and obj!=48):continue
            seen.add(obj);entry=objects[obj]
            canvas.alpha_composite(Image.open(root/'extracted/objects'/entry['file']),(i%10*16,16+i//10*16))
        expected=Image.open(out/f'{label}.png').crop((8,48,168,128)).convert('RGB')
        check(f'render-{room}-{variant}',ImageChops.difference(canvas.convert('RGB'),expected).getbbox() is None)
r=json.loads((root/'runs/truck-77-key-0/truck-states.json').read_text());by={v['label']:v for v in r}
check('truck-no-key-refused',by['initial']['positions']==by['driven']['positions']
    and by['driven']['rooms']['77'][9]==77 and by['driven']['rooms']['65'][9]==0)
for command in ('list','electricite','nuit','bidule','zombi-dead','bye'):
    r=json.loads((root/f'runs/terminal-{command}/results.json').read_text())['results']
    b=next(v for v in r if v['label']=='response')
    check('terminal-'+command,b['training_bonus']==int(command=='zombi-dead')
        and b['electricity_flag']==0 and b['state']==0)
(root/'extracted/trucks-terminal-rules.json').write_text(json.dumps({'controlled':True,'checks':checks},indent=2))
print(len(checks),'original truck/terminal checks passed, including six pixel-exact variants')
