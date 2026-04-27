from __future__ import annotations

import hashlib
import urllib.request
from pathlib import Path


BASE_DIR = Path(__file__).resolve().parent.parent
MODELS_DIR = BASE_DIR / "models"
MODELS_DIR.mkdir(parents=True, exist_ok=True)

FILES = {
    "face_detection_yunet_2023mar.onnx": "https://github.com/opencv/opencv_zoo/raw/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx",
    "face_recognition_sface_2021dec.onnx": "https://github.com/opencv/opencv_zoo/raw/main/models/face_recognition_sface/face_recognition_sface_2021dec.onnx",
}


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def download(url: str, destination: Path) -> None:
    print(f"Downloading {destination.name} ...")
    urllib.request.urlretrieve(url, destination)
    print(f"Saved {destination} ({sha256(destination)})")


def main() -> None:
    for filename, url in FILES.items():
        destination = MODELS_DIR / filename
        if destination.exists():
            print(f"Skip {filename}, already exists ({sha256(destination)})")
            continue
        download(url, destination)


if __name__ == "__main__":
    main()
