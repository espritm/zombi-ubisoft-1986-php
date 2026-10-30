"""Controlled fuse-box assay; objects provided, no electricity flag patched."""
from importlib.machinery import SourceFileLoader
runner=SourceFileLoader('rules','/workspace/analysis/rule-runner.py').load_module()
for gloves in (False,True):
    e=runner.Experiment(f'electricity-gloves-{int(gloves)}',38,position_start=True)
    e.pokes += ['11000:66c3=33','11000:6fda=09','11000:6fdb=3e']
    if gloves:
        e.pokes += ['11000:66c7=2d']
        e.select(3);e.key('copy')
    e.mark('initial')
    e.select(4);e.key('copy');e.cell(3,3);e.key('copy');e.mark('without-fuse')
    e.select(8);e.key('copy');e.cell(3,2);e.key('copy');e.mark('box-open')
    e.select(2);e.key('copy');e.key('copy');e.key('copy');e.mark('fuse-installed',1800)
    e.select(4);e.key('copy');e.cell(3,3);e.key('copy');e.mark('electricity-on')
    e.key('copy');e.key('copy');e.mark('electricity-off')
    r=e.run()
    by={v['label']:v for v in r}
    assert by['without-fuse']['electricity_flag']==0
    assert by['fuse-installed']['quest_storage'][3]==(51 if gloves else 0)
    assert by['fuse-installed']['inventory'][0]==(0 if gloves else 51)
    expected=[70,80,80] if gloves else [50,70,80]
    assert by['fuse-installed']['gauges_F_H_S']==expected
    assert by['electricity-on']['electricity_flag']==int(gloves)
    assert by['electricity-on']['room_record'][43]==(67 if gloves else 62)
    assert by['electricity-off']['electricity_flag']==0 and by['electricity-off']['room_record'][43]==62

e=runner.Experiment('cupboard-torch',38,position_start=True)
e.mark('initial')
e.select(8);e.key('copy');e.cell(2,9);e.key('copy');e.mark('cupboard-open')
e.select(1);e.key('copy');e.key('copy');e.key('copy');e.mark('torch-taken')
r=e.run()
assert r[1]['room_record'][39]==26
assert r[2]['inventory'][0]==43
