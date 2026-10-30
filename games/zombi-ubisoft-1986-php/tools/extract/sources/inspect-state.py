"""Summarize observed state using the upstream dump reader."""
import sys, json
from pathlib import Path
sys.path.insert(0, '/workspace/dsk2jumphp/tools')
from cpc import Dump
for path in sys.argv[1:]:
    d = Dump(path)
    word = lambda a: int.from_bytes(d.mem[a:a+2], 'little')
    cp, rp = word(0x6615), word(0x6617)
    print(json.dumps({'file': path, 'action': d.mem[0x6601], 'character_pointer': hex(cp),
                     'room_pointer': hex(rp), 'room_record': list(d.mem[rp:rp+50]),
                     'object_cursor': list(d.mem[0x661a:0x661c]),
                     'characters': [{'name':d.mem[a+2:a+11].decode('ascii').strip(),
                        'position':hex(word(a)), 'room_id':d.mem[word(a)],
                        'bytes':list(d.mem[a:a+40])} for a in range(0x6633,0x66d3,40)]}, indent=2))
