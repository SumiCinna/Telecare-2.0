from pathlib import Path
from PIL import Image

root = Path(r"C:\xampp\htdocs\Telecare 2.0")
source = root / "telecarelogo.png"
img = Image.open(source).convert("RGBA")
for folder in [root, root / "doctor", root / "private_telecare"]:
    if folder.exists():
        img.copy().save(folder / "favicon.ico", format="ICO", sizes=[(16, 16), (32, 32), (64, 64)])
        img.resize((32, 32)).save(folder / "favicon-32x32.png")
        img.resize((16, 16)).save(folder / "favicon-16x16.png")

print("favicon files regenerated")
