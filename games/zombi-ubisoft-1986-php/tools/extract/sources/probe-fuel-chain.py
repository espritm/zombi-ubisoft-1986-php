"""Controlled fuel chain: provide siphoning tool, source fitting and empty can.

No fuel flag is injected. Filling, transfer, contact and departure use original actions.
This is a logic assay, not a naturally completed supply route through the mall.
"""
from importlib.machinery import SourceFileLoader
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
e=runner.Experiment('fuel-chain-controlled',62,position_start=True)
e.pokes += ['11000:66c7=2a','11000:748a=05','11000:748b=34']
e.mark('initial')
e.select(8);e.key('copy');e.cell(3,2);e.key('copy');e.mark('source-open')
e.select(3);e.key('copy');e.mark('can-filled',1600)
e.select(9);e.key('copy');e.key('copy');e.mark('source-closed')
e.select(8);e.key('copy');e.cell(1,2);e.key('copy');e.mark('tank-open')
e.select(3);e.key('copy');e.mark('tank-filled',1800)
e.select(10);e.key('copy');e.key('right');e.key('copy');e.mark('alexandre')
e.select(8);e.key('copy');e.cell(1,3);e.key('copy')
e.select(16);e.key('copy');e.mark('cockpit')
e.select(4);e.key('copy');e.cell(2,2);e.key('copy');e.mark('contact')
e.key('copy');e.cell(3,3);e.key('copy');e.mark('departure',1600)
r=e.run(watch='6710-6711')
by={v['label']:v for v in r}
assert by['initial']['fuel_can']==0 and by['initial']['fuel_tank']==0
assert by['source-open']['room_record'][42]==18
assert by['can-filled']['fuel_can']==1 and by['can-filled']['fuel_tank']==0
assert by['source-closed']['room_record'][42]==5
assert by['tank-open']['room_record'][22]==19
assert by['tank-filled']['fuel_can']==0 and by['tank-filled']['fuel_tank']==1
assert by['contact']['room_record'][32]==69
assert by['departure']['program_counter']=='0x8553'
