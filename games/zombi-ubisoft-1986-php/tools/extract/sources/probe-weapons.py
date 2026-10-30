"""Provide a firearm explicitly, then measure the original aiming/attack behavior."""
from importlib.machinery import SourceFileLoader
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
for trained in (0,1):
    e=runner.Experiment(f'weapon-34-training-{trained}',41,position_start=True)
    e.pokes += ['11000:66c7=22',f'11000:66d1={trained:02x}']
    # Prepare the held weapon with no combat interruption; introduce the enemy
    # at the original weapon's enemy-count check, not by altering shot damage.
    e.pokes += ['8500:704c=00']
    e.pc_pokes += ['949e:704c=01','949e:670c=1f','949e:670d=1b']
    e.mark('initial',0)
    e.select(3);e.key('copy');e.mark('aiming',150)
    e.key('copy');e.mark('after_shot')
    r=e.run(watch='670c-670d')
    print('trained',trained,'enemy counts',[v['room_record'][6] for v in r],
          'enemy HP',[v['combat_bytes'][12] for v in r])
    assert r[1]['combat_bytes'][12]==31 and r[1]['used_item']==34
    assert r[-1]['held']==34 and r[-1]['party'][3]['state']==0
