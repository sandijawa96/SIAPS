from __future__ import annotations

import json
import logging

from fastapi import Depends, FastAPI, File, Form, HTTPException, UploadFile, status

from .engine import FaceEngineError, FaceRecognitionEngine, timed
from .schemas import EnrollResponse, HealthResponse, VerifyResponse
from .security import verify_internal_token
from .settings import get_settings


settings = get_settings()
logging.basicConfig(level=settings.log_level)
logger = logging.getLogger("face-service")
app = FastAPI(title="SIAPS Face Service", version="1.0.0")
engine = FaceRecognitionEngine(settings)


async def _read_upload(upload: UploadFile) -> bytes:
    content = await upload.read()
    if not content:
        raise HTTPException(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY, detail="Empty image payload")

    if len(content) > settings.max_upload_bytes:
        raise HTTPException(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY, detail="Image exceeds max upload size")

    return content


def _map_engine_error(error: FaceEngineError) -> VerifyResponse:
    reason_code = str(error)
    return VerifyResponse(
        success=False,
        result="manual_review",
        score=None,
        threshold=0.0,
        reason_code=reason_code,
        template_version=settings.template_version,
        metadata={},
    )


@app.get("/health", response_model=HealthResponse)
async def health(_: None = Depends(verify_internal_token)) -> HealthResponse:
    return HealthResponse(
        status="ok",
        engine="opencv-yunet-sface",
        yunet_model_loaded=True,
        sface_model_loaded=True,
        template_version=settings.template_version,
    )


@app.post("/enroll", response_model=EnrollResponse)
async def enroll(
    image: UploadFile = File(...),
    _: None = Depends(verify_internal_token),
) -> EnrollResponse:
    image_bytes = await _read_upload(image)

    try:
        result, processing_ms = timed(lambda: engine.enroll_from_bytes(image_bytes))
    except FaceEngineError as error:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail=str(error),
        ) from error

    metadata = {
        **result.metadata,
        "processing_ms": processing_ms,
    }

    return EnrollResponse(
        success=True,
        template_vector=result.template_vector,
        template_version=result.template_version,
        quality_score=result.quality_score,
        detection_score=result.detection_score,
        metadata=metadata,
    )


@app.post("/verify", response_model=VerifyResponse)
async def verify(
    image: UploadFile = File(...),
    threshold: float = Form(...),
    template_vector: str = Form(...),
    _: None = Depends(verify_internal_token),
) -> VerifyResponse:
    image_bytes = await _read_upload(image)

    try:
        parsed_template = json.loads(template_vector)
        if not isinstance(parsed_template, list):
            raise ValueError("template_vector must be a JSON array")
    except (json.JSONDecodeError, ValueError) as error:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="Invalid template_vector payload",
        ) from error

    try:
        result, processing_ms = timed(
            lambda: engine.verify_from_bytes(
                image_bytes=image_bytes,
                template_vector=parsed_template,
                threshold=threshold,
            )
        )
    except FaceEngineError as error:
        logger.warning("Face verify failed", extra={"reason_code": str(error)})
        return VerifyResponse(
            success=False,
            result="manual_review",
            score=None,
            threshold=threshold,
            reason_code=str(error),
            template_version=settings.template_version,
            metadata={},
        )

    metadata = {
        **result.metadata,
        "processing_ms": processing_ms,
    }

    return VerifyResponse(
        success=True,
        result=result.result,
        score=result.score,
        threshold=result.threshold,
        reason_code=result.reason_code,
        template_version=result.template_version,
        metadata=metadata,
    )
