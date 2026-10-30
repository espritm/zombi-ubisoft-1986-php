"""Enter the helicopter via its original open-door / enter actions."""
from importlib.machinery import SourceFileLoader
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
for fuel in (0,1):
    e=runner.Experiment(f'helicopter-fuel-{fuel}',62,position_start=True)
    if fuel:e.pokes.append('11000:6711=01')
    e.mark('initial')
    e.select(10);e.key('copy');e.key('right');e.key('copy');e.mark('alexandre')
    e.select(8);e.key('copy');e.cell(1,3);e.key('copy');e.mark('door-open')
    e.select(16);e.key('copy');e.mark('inside')
    e.select(4);e.key('copy');e.cell(2,2);e.key('copy');e.mark('key-inserted')
    e.key('copy');e.cell(3,3);e.key('copy');e.mark('departure-result',1600)
    r=e.run()
    assert r[1]['active_character']=='0x6633' and r[1]['held']==47
    assert r[2]['room_record'][23]==17 and r[3]['state_aux']==90
    assert r[4]['room_record'][32]==69
    assert (r[-1]['program_counter']=='0x8553')==bool(fuel)
