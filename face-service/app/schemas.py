from __future__ import annotations

from typing import Any

from pydantic import BaseModel, Field


class HealthResponse(BaseModel):
    status: str
    engine: str
    yunet_model_loaded: bool
    sface_model_loaded: bool
    template_version: str


class EnrollResponse(BaseModel):
    success: bool
    template_vector: list[float]
    template_version: str
    quality_score: float
    detection_score: float
    metadata: dict[str, Any] = Field(default_factory=dict)


class VerifyResponse(BaseModel):
    success: bool
    result: str
    score: float | None = None
    threshold: float
    reason_code: str
    template_version: str
    metadata: dict[str, Any] = Field(default_factory=dict)
