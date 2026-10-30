"""Export observed original assets using dsk2jumphp's existing dump/graphics tools.

Run after probe-directions.py. Outputs stay outside the public game repository.
"""
from pathlib import Path
import json
import sys

root = Path('/workspace')
sys.path.insert(0, str(root/'dsk2jumphp/tools'))
from cpc import Dump, graphics

out = root/'analysis/extracted'
out.mkdir(exist_ok=True)
roof = Dump(root/'analysis/runs/direction-3/dump-08900.bin')
terrace = Dump(root/'analysis/runs/direction-3/dump-12499.bin')
stairs = Dump(root/'analysis/runs/terrace-1/dump-16999.bin')
roof.screen().save(out/'original-roof.png')
terrace.screen().save(out/'original-terrace.png')
for label, dump in [('roof', roof), ('terrace', terrace), ('stairs', stairs)]:
    dump.screen().crop((8, 48, 168, 128)).save(out/f'scene-{label}.png')

# #890B..#898D draws the carousel: source #5401, eight bytes/icon,
# 32 scanlines, 144-byte source stride (= 18 icons). See analysis notes.
atlas = graphics(roof.mem, 0x5401, 144, 32, 1, mode=1, rgb=roof.rgb, gap=0)
atlas.save(out/'actions-atlas.png')
for i in range(18):
    atlas.crop((i*32, 0, (i+1)*32, 32)).save(out/f'action-{i+1:02d}.png')

characters = []
for i in range(4):
    address = 0x6633 + i*40
    data = roof.mem[address:address+40]
    pos = int.from_bytes(data[:2], 'little')
    characters.append({'index': i, 'address': hex(address),
                       'name': data[2:11].decode('ascii').strip(),
                       'map_pointer': hex(pos), 'room_id': roof.mem[pos],
                       'raw_record_hex': data.hex()})
result = {'screen': {'width': 320, 'height': 200, 'mode': roof.mode},
          'palette_hardware': roof.palette,
          'characters': characters,
          'action_selector_address': '0x6601',
          'active_character_pointer_address': '0x6615',
          'active_room_record_pointer_address': '0x6617',
          'icons': [{'atlas_index': i+1, 'action_id': (i+14)%18+1,
                     'x': i*32, 'y': 0, 'width': 32, 'height': 32} for i in range(18)],
          'observed_rooms': [
              {'id': 62, 'map_pointer': '0x6862', 'scene': 'scene-roof.png'},
              {'id': 61, 'map_pointer': '0x6863', 'scene': 'scene-terrace.png'},
              {'id': 60, 'map_pointer': '0x682b', 'scene': 'scene-stairs.png'}],
          'observed_edges': [
              {'from': 62, 'to': 61, 'direction_choice': 3},
              {'from': 61, 'to': 60, 'cycles_after_previous_choice': 1},
              {'from': 61, 'to': 62, 'cycles_after_previous_choice': 2}],
          'room_transition': {'before_pointer': '0x6862', 'before_id': 62,
                              'after_pointer': '0x6863', 'after_id': 61},
          'scope': 'Observed assets and state, not a complete remake specification'}
(out/'observations.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps(result, indent=2))
