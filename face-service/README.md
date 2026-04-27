# Face Service

Service inference wajah internal untuk SIAPS, dijalankan terpisah dari Laravel.

## Fase saat ini
- `FastAPI`
- `OpenCV YuNet`
- `OpenCV SFace`
- tanpa anti-spoofing

## Endpoint
- `GET /health`
- `POST /enroll`
- `POST /verify`

## Setup cepat
```bash
python -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
python scripts/download_models.py
uvicorn app.main:app --host 127.0.0.1 --port 9001
```

## Environment
- `FACE_SERVICE_TOKEN`
- `FACE_SERVICE_YUNET_MODEL_PATH`
- `FACE_SERVICE_SFACE_MODEL_PATH`
- `FACE_SERVICE_MAX_IMAGE_SIDE`
- `FACE_SERVICE_LOG_LEVEL`

Secara default model dicari di folder `models/`.
