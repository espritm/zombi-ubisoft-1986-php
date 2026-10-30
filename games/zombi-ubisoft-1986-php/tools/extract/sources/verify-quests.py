"""Check the advanced original-game observations; keep controlled preconditions explicit."""
from pathlib import Path
import json
root=Path('/workspace/analysis')
rules=[]
def load(name):return json.loads((root/f'runs/{name}/results.json').read_text())
def check(name,ok,description,scenario):
    if not ok:raise AssertionError(name)
    rules.append({'id':name,'verified':True,'description':description,
                  'evidence':f'runs/{scenario}/results.json'})
for trained in (0,1):
    name=f'weapon-34-training-{trained}';d=load(name);r=d['results']
    check(name,r[1]['combat_bytes'][12]==31 and r[1]['used_item']==34
          and r[-1]['combat_bytes'][12]==0 and r[-1]['room_record'][6]==0
          and r[-1]['held']==34 and r[-1]['state']==0,
          'Controlled shot with item 34: original aiming followed by COPY defeats a 31-HP enemy; weapon remains held. Enemy introduced with its original observed HP/strength at the weapon check. This does not establish an ammunition rule or quantify training benefit.',name)
d=load('cupboard-torch');r=d['results']
check('find-torch',not d['additional_pokes'] and r[1]['room_record'][39]==26 and r[2]['inventory'][0]==43,
      'Open cupboard 13->26 in room 38, take first contents via the original menu: item 43. Only starting position is controlled.','cupboard-torch')
for gloves in (0,1):
    name=f'electricity-gloves-{gloves}';r={v['label']:v for v in load(name)['results']}
    ok=r['without-fuse']['electricity_flag']==0 and r['fuse-installed']['quest_storage'][3]==(51 if gloves else 0)
    ok=ok and r['fuse-installed']['gauges_F_H_S']==([70,80,80] if gloves else [50,70,80])
    ok=ok and r['electricity-on']['electricity_flag']==gloves and r['electricity-off']['electricity_flag']==0
    check(name,ok,'Provided fuse, box and switch. Without active gloves: F-20/H-10 and installation fails. With item 45 active: fuse installed, switch 62->67 enables electricity, 67->62 disables it.',name)
for fuel in (0,1):
    name=f'helicopter-fuel-{fuel}';r=load(name)['results']
    check(name,r[1]['held']==47 and r[2]['room_record'][23]==17 and r[3]['state_aux']==90
          and r[4]['room_record'][32]==69 and ((r[-1]['program_counter']=='0x8553')==bool(fuel)),
          'Alexandre uses his initial key: open helicopter door, enter cockpit, insert key. Departure fails with tank=0; tank=1 reaches victory. Positive tank=1 is an injected precondition in this pair.',name)
name='fuel-chain-controlled';d=load(name);r={v['label']:v for v in d['results']}
check('fuel-full-chain',r['initial']['fuel_can']==0 and r['initial']['fuel_tank']==0
      and r['can-filled']['fuel_can']==1 and r['tank-filled']['fuel_can']==0
      and r['tank-filled']['fuel_tank']==1 and r['departure']['program_counter']=='0x8553'
      and all(':6710=' not in p and ':6711=' not in p for p in d['additional_pokes']),
      'Tool 42, empty can 52 and source fitting 5 explicitly provided. Open source, use tool to fill can, close source, open tank, transfer fuel, select Alexandre, enter and start helicopter: victory without patching either fuel flag.',name)
name='global-defeat';r=load(name)['results']
check(name,r[0]['party'][3]['state']==0 and r[-1]['program_counter']=='0x851f',
      'Three survivors preset unavailable (FFh); final survivor loses original fight with untouched health. All four states >=20h leads to the global defeat screen.',name)
(root/'extracted/quest-rules.json').write_text(json.dumps({'scope':'Controlled original observations, not a completed natural walkthrough','rules':rules},indent=2))
print(len(rules),'advanced behavior checks passed')
