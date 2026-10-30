"""Decode world tables whose addresses were identified in observed movement code."""
import json, sys
from pathlib import Path
sys.path.insert(0, '/workspace/dsk2jumphp/tools')
from cpc import Dump
root = Path('/workspace/analysis')
d = Dump(root/'runs/direction-3/dump-08900.bin')
directions = [('north',8,0),('east',1,1),('south',-8,2),('west',-1,3),('down',-56,4),('up',56,5)]
cells = list(d.mem[0x6726:0x6876])
rooms = []
for index, room_id in enumerate(cells):
    if not room_id:
        continue
    address = 0x6876+(room_id-1)*50
    data = list(d.mem[address:address+50])
    exits=[]
    for name, delta, field in directions:
        target=index+delta
        exits.append({'direction':name, 'cell_delta':delta, 'flag':data[field],
                      'target_id':cells[target] if 0<=target<len(cells) else None,
                      'ordinary_passage_in_code':data[field] in (0,20),
                      'tested_in_game':False})
    rooms.append({'id':room_id,'map_address':hex(0x6726+index),
                  'map_index':index,'layer':index//56,'row':index%56//8,'column':index%8,
                  'record_address':hex(address),'exits':exits,
                  'record_prefix':data[:10],
                  'objects_grid':[data[10+y*10:20+y*10] for y in range(4)]})
out=root/'extracted'; out.mkdir(exist_ok=True)
interiors=[]
for room_id in range(87,91):
    address=0x6876+(room_id-1)*50;data=list(d.mem[address:address+50])
    interiors.append({'id':room_id,'record_address':hex(address),'record_prefix':data[:10],
        'objects_grid':[data[10+y*10:20+y*10] for y in range(4)],
        'entry_rule':'Open vehicle object 14..17 -> character+32 = object+73; action 16'})
world={'source':'Original RAM after boot; movement code #9BB7–#9CA2; record lookup #9DBB',
       'map_address':'0x6726','map_dimensions':{'layers':6,'rows':7,'columns':8},
       'layout_note':'Coordinates derived from observed strides 1,8,56; compass names are conventional.',
       'rooms':rooms,'vehicle_interiors':interiors}
(out/'world.json').write_text(json.dumps(world,indent=2),encoding='utf-8')
ids=sorted(set(cells)-{0})
objects=sorted({v for r in rooms for row in r['objects_grid'] for v in row if v not in (0,255)})
print('rooms/cells',len(rooms),'unique IDs',len(ids),'range',min(ids),max(ids),'object IDs',objects)
for room in rooms:
    if room['id'] in (62,61,60,57):
        print(room['id'],[(e['direction'],e['target_id'],e['flag']) for e in room['exits']],room['objects_grid'])
