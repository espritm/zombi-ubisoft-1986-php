"""Regenerate private website pictures from the game's verified screenshots."""
from pathlib import Path
import shutil
from PIL import Image
game=Path(__file__).resolve().parents[2]
out=game/'site/images';out.mkdir(parents=True,exist_ok=True)
shots=game/'var/screenshots'
for name in ('title','play'):
    Image.open(shots/f'{name}.png').convert('RGB').save(out/f'{name}.png')
screen=Image.open(shots/'play.png').convert('RGB')
og=Image.new('RGB',(1200,630),(0,0,0))
og.paste(screen.resize((960,600),Image.Resampling.NEAREST),(120,15));og.save(out/'og.png')
title=Image.open(game/'assets/original/title.png')
title.crop((116,64,204,120)).resize((64,64),Image.Resampling.NEAREST).save(out/'favicon.png')
print('Website images regenerated')
# JumPHP accepts private uploads below assets/ only. Mirror its private-file route locally.
for target in (game/'assets/site', game/'site/play/private/assets/site', game/'dist/site/play/private/assets/site'):
    target.mkdir(parents=True,exist_ok=True)
    for file in out.glob('*.png'):shutil.copyfile(file,target/file.name)
