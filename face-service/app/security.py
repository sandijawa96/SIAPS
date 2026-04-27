from __future__ import annotations

from fastapi import Header, HTTPException, status

from .settings import get_settings


def verify_internal_token(x_face_service_token: str | None = Header(default=None)) -> None:
    settings = get_settings()
    expected = settings.service_token

    if not expected:
        return

    if x_face_service_token != expected:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid face service token",
        )
