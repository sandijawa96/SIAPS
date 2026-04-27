from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path


BASE_DIR = Path(__file__).resolve().parent.parent


@dataclass(frozen=True)
class Settings:
    service_token: str
    yunet_model_path: Path
    sface_model_path: Path
    detector_score_threshold: float
    max_image_side: int
    log_level: str
    max_upload_bytes: int
    template_version: str


def get_settings() -> Settings:
    return Settings(
        service_token=os.getenv("FACE_SERVICE_TOKEN", "").strip(),
        yunet_model_path=Path(
            os.getenv(
                "FACE_SERVICE_YUNET_MODEL_PATH",
                str(BASE_DIR / "models" / "face_detection_yunet_2023mar.onnx"),
            )
        ),
        sface_model_path=Path(
            os.getenv(
                "FACE_SERVICE_SFACE_MODEL_PATH",
                str(BASE_DIR / "models" / "face_recognition_sface_2021dec.onnx"),
            )
        ),
        detector_score_threshold=float(os.getenv("FACE_SERVICE_DETECTOR_SCORE_THRESHOLD", "0.9")),
        max_image_side=max(640, int(os.getenv("FACE_SERVICE_MAX_IMAGE_SIDE", "1280"))),
        log_level=os.getenv("FACE_SERVICE_LOG_LEVEL", "INFO").upper(),
        max_upload_bytes=int(os.getenv("FACE_SERVICE_MAX_UPLOAD_BYTES", str(5 * 1024 * 1024))),
        template_version=os.getenv("FACE_SERVICE_TEMPLATE_VERSION", "opencv-yunet-sface-v1"),
    )
