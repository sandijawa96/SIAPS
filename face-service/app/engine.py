from __future__ import annotations

import time
from dataclasses import dataclass

import cv2 as cv
import numpy as np

from .settings import Settings


class FaceEngineError(RuntimeError):
    pass


@dataclass
class EnrollResult:
    template_vector: list[float]
    template_version: str
    quality_score: float
    detection_score: float
    metadata: dict


@dataclass
class VerifyResult:
    result: str
    score: float | None
    threshold: float
    reason_code: str
    template_version: str
    metadata: dict


class FaceRecognitionEngine:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings

        if not settings.yunet_model_path.exists():
            raise FaceEngineError(f"YuNet model not found: {settings.yunet_model_path}")

        if not settings.sface_model_path.exists():
            raise FaceEngineError(f"SFace model not found: {settings.sface_model_path}")

        self.detector = cv.FaceDetectorYN.create(
            str(settings.yunet_model_path),
            "",
            (320, 320),
            score_threshold=settings.detector_score_threshold,
            nms_threshold=0.3,
            top_k=5000,
        )
        self.recognizer = cv.FaceRecognizerSF.create(
            str(settings.sface_model_path),
            "",
        )

    def enroll_from_bytes(self, image_bytes: bytes) -> EnrollResult:
        image = self._decode_image(image_bytes)
        image, resize_metadata = self._normalize_image_size(image)
        face = self._detect_single_face(image)
        aligned = self.recognizer.alignCrop(image, face)
        embedding = self.recognizer.feature(aligned)
        template_vector = embedding.flatten().astype(np.float32).tolist()
        detection_score = float(face[14])
        quality_score = self._estimate_quality(image, face, detection_score)

        metadata = {
            "image_width": int(image.shape[1]),
            "image_height": int(image.shape[0]),
            "face_box": self._face_box(face),
            **resize_metadata,
        }

        return EnrollResult(
            template_vector=template_vector,
            template_version=self.settings.template_version,
            quality_score=quality_score,
            detection_score=detection_score,
            metadata=metadata,
        )

    def verify_from_bytes(
        self,
        image_bytes: bytes,
        template_vector: list[float],
        threshold: float,
    ) -> VerifyResult:
        image = self._decode_image(image_bytes)
        image, resize_metadata = self._normalize_image_size(image)
        face = self._detect_single_face(image)
        aligned = self.recognizer.alignCrop(image, face)
        embedding = self.recognizer.feature(aligned)
        template = self._build_template(template_vector)
        score = float(self.recognizer.match(embedding, template, cv.FaceRecognizerSF_FR_COSINE))

        result = "verified" if score >= threshold else "rejected"
        reason_code = "matched" if result == "verified" else "below_threshold"

        metadata = {
            "image_width": int(image.shape[1]),
            "image_height": int(image.shape[0]),
            "face_box": self._face_box(face),
            "detection_score": float(face[14]),
            **resize_metadata,
        }

        return VerifyResult(
            result=result,
            score=score,
            threshold=threshold,
            reason_code=reason_code,
            template_version=self.settings.template_version,
            metadata=metadata,
        )

    def _decode_image(self, image_bytes: bytes) -> np.ndarray:
        if not image_bytes:
            raise FaceEngineError("empty_image")

        image_array = np.frombuffer(image_bytes, dtype=np.uint8)
        image = cv.imdecode(image_array, cv.IMREAD_COLOR)
        if image is None:
            raise FaceEngineError("image_decode_failed")

        return image

    def _normalize_image_size(self, image: np.ndarray) -> tuple[np.ndarray, dict]:
        original_height, original_width = image.shape[:2]
        max_side = max(original_width, original_height)

        if max_side <= self.settings.max_image_side:
            return image, {
                "original_width": int(original_width),
                "original_height": int(original_height),
                "resized": False,
            }

        scale = self.settings.max_image_side / float(max_side)
        resized_width = max(1, int(round(original_width * scale)))
        resized_height = max(1, int(round(original_height * scale)))
        resized = cv.resize(image, (resized_width, resized_height), interpolation=cv.INTER_AREA)

        return resized, {
            "original_width": int(original_width),
            "original_height": int(original_height),
            "resized_width": int(resized_width),
            "resized_height": int(resized_height),
            "resized": True,
        }

    def _detect_single_face(self, image: np.ndarray) -> np.ndarray:
        height, width = image.shape[:2]
        self.detector.setInputSize((width, height))
        _, faces = self.detector.detect(image)

        if faces is None or len(faces) == 0:
            raise FaceEngineError("no_face_detected")

        if len(faces) > 1:
            raise FaceEngineError("multiple_faces_detected")

        return faces[0]

    def _build_template(self, template_vector: list[float]) -> np.ndarray:
        if not template_vector:
            raise FaceEngineError("template_vector_empty")

        template = np.asarray(template_vector, dtype=np.float32).reshape(1, -1)
        if template.size == 0:
            raise FaceEngineError("template_vector_invalid")

        return template

    def _estimate_quality(self, image: np.ndarray, face: np.ndarray, detection_score: float) -> float:
        width = max(float(image.shape[1]), 1.0)
        height = max(float(image.shape[0]), 1.0)
        area_ratio = max(float(face[2]) * float(face[3]), 1.0) / (width * height)
        normalized_area = max(0.0, min(area_ratio * 8.0, 1.0))
        quality = (detection_score * 0.7) + (normalized_area * 0.3)

        return round(max(0.0, min(quality, 0.9999)), 4)

    def _face_box(self, face: np.ndarray) -> dict:
        return {
            "x": round(float(face[0]), 2),
            "y": round(float(face[1]), 2),
            "w": round(float(face[2]), 2),
            "h": round(float(face[3]), 2),
        }


def timed(callable_fn):
    started = time.perf_counter()
    result = callable_fn()
    elapsed = int(round((time.perf_counter() - started) * 1000))
    return result, elapsed
