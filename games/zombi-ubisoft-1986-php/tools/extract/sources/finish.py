from pathlib import Path
from PIL import Image
root=Path('/workspace/analysis')
Image.open(root/'runs/rom-boot/screen-03000.ppm').save(root/'runs/rom-boot/screen-03000.png')
Image.open(root/'runs/helicopter-fuel-1/departure-result.png').save(root/'extracted/ending-victory.png')
Image.open(root/'runs/global-defeat/defeat.png').save(root/'extracted/ending-defeat.png')
