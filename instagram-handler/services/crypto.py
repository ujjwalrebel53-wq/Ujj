import base64
import hashlib
import os

from cryptography.fernet import Fernet, InvalidToken


def _derive_key(secret: str) -> bytes:
    digest = hashlib.sha256(secret.encode()).digest()
    return base64.urlsafe_b64encode(digest)


def get_cipher() -> Fernet:
    key = os.getenv("ENCRYPTION_KEY") or os.getenv("SECRET_KEY") or "instagram-handler-default-key"
    return Fernet(_derive_key(key))


def encrypt(value: str) -> str:
    if not value:
        return ""
    return get_cipher().encrypt(value.encode()).decode()


def decrypt(value: str) -> str:
    if not value:
        return ""
    try:
        return get_cipher().decrypt(value.encode()).decode()
    except InvalidToken:
        return ""
