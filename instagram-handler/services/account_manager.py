import json
import os
import threading
from datetime import datetime, timezone
from typing import Any

from instagrapi import Client
from instagrapi.exceptions import (
    ChallengeRequired,
    ClientError,
    LoginRequired,
    PleaseWaitFewMinutes,
    TwoFactorRequired,
)

from services import database as db
from services.crypto import decrypt, encrypt
from services.proxy_manager import resolve_proxy

SESSIONS_DIR = os.path.join(os.path.dirname(os.path.dirname(__file__)), "data", "sessions")
os.makedirs(SESSIONS_DIR, exist_ok=True)

_clients: dict[int, Client] = {}
_lock = threading.Lock()


class AccountLoginError(Exception):
    def __init__(self, message: str, needs_2fa: bool = False):
        super().__init__(message)
        self.needs_2fa = needs_2fa


def _session_path(account_id: int) -> str:
    return os.path.join(SESSIONS_DIR, f"account_{account_id}.json")


def _build_client(proxy: str = "") -> Client:
    client = Client()
    client.delay_range = [1, 3]
    if proxy:
        client.set_proxy(proxy)
    return client


def _save_session(account_id: int, client: Client) -> None:
    settings = client.get_settings()
    db.update_account(account_id, {"session_data": encrypt(json.dumps(settings))})
    with open(_session_path(account_id), "w", encoding="utf-8") as f:
        json.dump(settings, f)


def _load_session_client(account_id: int, secrets: dict[str, Any]) -> Client | None:
    client = _build_client(resolve_proxy(secrets.get("proxy") or ""))
    settings = None
    session_json = decrypt(secrets.get("session_data") or "")
    if session_json:
        try:
            settings = json.loads(session_json)
        except json.JSONDecodeError:
            settings = None
    if not settings:
        path = _session_path(account_id)
        if os.path.exists(path):
            try:
                with open(path, encoding="utf-8") as f:
                    settings = json.load(f)
            except (json.JSONDecodeError, OSError):
                return None
    if not settings:
        return None
    try:
        client.set_settings(settings)
        client.account_info()
        return client
    except Exception:
        return None


def login_account(
    account_id: int,
    password: str | None = None,
    verification_code: str | None = None,
) -> dict[str, Any]:
    secrets = db.get_account_secrets(account_id)
    if not secrets:
        raise AccountLoginError("Account not found")

    username = secrets["username"]
    pwd = password or decrypt(secrets.get("password_enc") or "")
    if not pwd and not secrets.get("session_data"):
        raise AccountLoginError("Password required")

    client = _build_client(resolve_proxy(secrets.get("proxy") or ""))

    with _lock:
        try:
            if secrets.get("session_data") or os.path.exists(_session_path(account_id)):
                loaded = _load_session_client(account_id, secrets)
                if loaded:
                    _clients[account_id] = loaded
                    return _sync_profile(account_id, loaded)

            if verification_code:
                client.login(username, pwd, verification_code=verification_code)
            else:
                client.login(username, pwd)

            _clients[account_id] = client
            _save_session(account_id, client)
            if password:
                db.update_account(account_id, {"password_enc": encrypt(password)})
            return _sync_profile(account_id, client)

        except TwoFactorRequired:
            raise AccountLoginError("2FA code required. Enter the verification code.", needs_2fa=True)
        except ChallengeRequired:
            raise AccountLoginError("Instagram challenge required. Login manually on the app first.")
        except PleaseWaitFewMinutes as exc:
            db.update_account(account_id, {"status": "error", "last_error": str(exc)})
            raise AccountLoginError(f"Rate limited: {exc}")
        except (LoginRequired, ClientError) as exc:
            db.update_account(account_id, {"status": "error", "last_error": str(exc)})
            raise AccountLoginError(str(exc))


def _sync_profile(account_id: int, client: Client) -> dict[str, Any]:
    info = client.account_info()
    now = datetime.now(timezone.utc).isoformat()
    db.update_account(
        account_id,
        {
            "status": "active",
            "followers": info.follower_count,
            "following": info.following_count,
            "posts_count": info.media_count,
            "profile_pic": str(info.profile_pic_url) if info.profile_pic_url else "",
            "full_name": info.full_name or "",
            "is_verified": 1 if info.is_verified else 0,
            "last_login": now,
            "last_error": "",
        },
    )
    db.log_activity(account_id, "login", f"Logged in as @{info.username}")
    return db.get_account(account_id)


def get_client(account_id: int) -> Client:
    with _lock:
        if account_id in _clients:
            return _clients[account_id]
    secrets = db.get_account_secrets(account_id)
    if not secrets:
        raise AccountLoginError("Account not found")
    client = _load_session_client(account_id, secrets)
    if not client:
        raise AccountLoginError("Session expired. Please login again.")
    _clients[account_id] = client
    return client


def logout_account(account_id: int) -> None:
    with _lock:
        _clients.pop(account_id, None)
    path = _session_path(account_id)
    if os.path.exists(path):
        os.remove(path)
    db.update_account(account_id, {"status": "inactive", "session_data": ""})
    db.log_activity(account_id, "logout", "Session cleared")


def refresh_account(account_id: int) -> dict[str, Any]:
    client = get_client(account_id)
    return _sync_profile(account_id, client)


def bulk_refresh(account_ids: list[int]) -> list[dict[str, Any]]:
    results = []
    for aid in account_ids:
        try:
            account = refresh_account(aid)
            results.append({"id": aid, "success": True, "account": account})
        except Exception as exc:
            results.append({"id": aid, "success": False, "error": str(exc)})
    return results


def bulk_login(account_ids: list[int]) -> list[dict[str, Any]]:
    results = []
    for aid in account_ids:
        try:
            account = login_account(aid)
            results.append({"id": aid, "success": True, "account": account})
        except AccountLoginError as exc:
            results.append({"id": aid, "success": False, "error": str(exc), "needs_2fa": exc.needs_2fa})
        except Exception as exc:
            results.append({"id": aid, "success": False, "error": str(exc)})
    return results


def post_photo(account_id: int, image_path: str, caption: str = "") -> dict[str, Any]:
    client = get_client(account_id)
    media = client.photo_upload(image_path, caption)
    db.log_activity(account_id, "post_photo", f"Posted media {media.pk}")
    db.update_account(account_id, {"posts_count": (db.get_account(account_id) or {}).get("posts_count", 0) + 1})
    return {"media_id": str(media.pk), "code": media.code}


def import_session(account_id: int, session_settings: dict[str, Any]) -> dict[str, Any]:
    secrets = db.get_account_secrets(account_id)
    if not secrets:
        raise AccountLoginError("Account not found")
    client = _build_client(resolve_proxy(secrets.get("proxy") or ""))
    client.set_settings(session_settings)
    try:
        client.get_timeline_feed()
    except Exception as exc:
        raise AccountLoginError(f"Invalid session: {exc}")
    _clients[account_id] = client
    _save_session(account_id, client)
    return _sync_profile(account_id, client)
