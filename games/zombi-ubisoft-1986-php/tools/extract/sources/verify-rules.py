"""Validate measured original-state transitions and emit a compact behavior specification."""
from pathlib import Path
import json
root=Path('/workspace/analysis');runs=root/'runs'
def read(name):return json.loads((runs/name/'results.json').read_text())
rules=[]
def checked(name,condition,description,addresses,scenario):
    if not condition:raise AssertionError(name)
    rules.append({'id':name,'description':description,'addresses':addresses,
                  'evidence':f'runs/{scenario}/results.json','verified':True})

r=read('equipment-38')['results']
checked('equip-swap',r[1]['inventory']==[38,0,0,0,0] and r[2]['inventory']==[0,0,0,0,38]
        and r[4]['inventory']==[38,0,0,0,0],
        'Action 18 swaps the selected item with the held slot (+28); choosing the blank unequips it.',
        ['0xA522','character+24..28'],'equipment-38')
checked('activate-held-item',r[3]['used_item']==38 and r[4]['used_item']==38 and r[5]['used_item']==0,
        'Action 3 activates the held item; unequipping alone does not clear activation; using an empty hand does.',
        ['0x9F73','character+39'],'equipment-38')
r=read('door-38')['results']
checked('door-open-close',[v['room_record'][18] for v in r]==[8,21,8]
        and [v['room_record'][1] for v in r]==[240,250,240],
        'Actions 8/9 open/close this door: object 8->21->8 and exit flag F0->FA->F0.',
        ['0x9FCF','0x9FD2','0xA041'],'door-38')
for obj,before,after,index in [(54,60,62,0),(54,69,70,0),(55,70,74,2),(55,79,80,2)]:
    name=f'consume-{obj}-{before}';r=read(name)['results']
    checked(name,r[0]['gauges_F_H_S'][index]==before and r[-1]['gauges_F_H_S'][index]==after
            and r[1]['inventory'][0]==obj and r[-1]['inventory']==[0]*5,
            f'Object {obj}: measured gauge index {index} {before}->{after}; item consumed. Low gauge was a controlled precondition.',
            ['0xA4DD','0xA3E9'],name)
r=read('sleep-34')['results'];by={v['label']:v for v in r}
b,a=by['before-wake']['party'][3],by['after-wake']['party'][3]
checked('sleep-wake',r[1]['party'][3]['state']==16 and b['state']==16 and a['state']==0
        and a['gauges_F_H_S'][2]==min(b['gauges_F_H_S'][2]+8,80),
        'Sleeping marks the character unavailable (10h). At counter 01D400h it wakes and adds 8 to S, capped at its maximum. Time-related S losses also occur during sleep.',
        ['0xA136','0x7CC4','character+33','character+35..37'],'sleep-34')
r=read('combat-idle')['results']
checked('death-reanimation',r[-3]['party'][3]['state']==64 and r[-2]['active_character']=='0x6633'
        and r[-1]['party'][3]['state']==128,
        'Without defense Patrick reaches the cross/dead state 40h; while Alexandre remains active elsewhere, Patrick reaches zombie state 80h. No health or enemy patch.',
        ['0x8803','0x8368','0x7F1B'],'combat-idle')
r=read('combat-alternating')['results']
checked('combat-alternation',r[0]['room_record'][6]==1 and r[-1]['room_record'][6]==0
        and r[-1]['combat_bytes'][12]==0 and r[-1]['party'][3]['state']==0,
        'Rapid alternating right/left inputs defeat the enemy (HP 31->0, count 1->0). Tested down=2 frames, next press after 4 frames.',
        ['0x8707','0x871B','0x7EB0','0x8803'],'combat-alternating')
r=read('light-item-43')['results']
checked('light-activation',r[1]['held']==43 and r[1]['used_item']==0 and r[2]['used_item']==43
        and r[3]['used_item']==0,
        'Held item 43 alone leaves room 2 dark; activation reveals it, deactivation returns darkness. The item was injected as an explicit precondition; obtaining it was not tested.',
        ['0x9D57','character+39'],'light-item-43')
r=read('idle-gauges')['results']
checked('idle-observation',all(v['gauges_F_H_S']==[70,80,80] for v in r),
        'In this safe-room run gauges stayed unchanged between 10:27 and 16:27. This is not a general claim that gauges never decay.',
        ['0x7D09'],'idle-gauges')
out=root/'extracted/behavior-rules.json';out.write_text(json.dumps({'scope':'Measured subset, not a complete game specification','rules':rules},indent=2))
print(len(rules),'behavior checks passed')
