"""Public directory title card, drawn from code; contains no original-game resources."""
from pathlib import Path
from PIL import Image,ImageDraw,ImageFont
game=Path(__file__).resolve().parents[2]
im=Image.new('RGB',(640,360),(5,5,9));d=ImageDraw.Draw(im)
blue=(0,128,255);red=(255,48,48);white=(238,238,238)
for y in (16,20,26,338,344):d.line((16,y,624,y),fill=blue)
d.text((32,42),'ZOMBI',font=ImageFont.load_default(size=100),fill=red)
d.text((36,154),'AMSTRAD CPC  /  1986',font=ImageFont.load_default(size=24),fill=white)
d.text((36,197),'PROTOTYPE PHP  0.3',font=ImageFont.load_default(size=24),fill=blue)
for i in range(4):
    x=36+i*56;d.rectangle((x,257,x+40,305),outline=blue,width=2)
    d.text((x+13,268),str(i+1),font=ImageFont.load_default(size=22),fill=white)
d.text((300,264),'EXPLORER. SURVIVRE.',font=ImageFont.load_default(size=19),fill=white)
d.text((300,291),'PORTAGE EN COURS',font=ImageFont.load_default(size=16),fill=blue)
im.save(game/'assets/thumbnail.png')
