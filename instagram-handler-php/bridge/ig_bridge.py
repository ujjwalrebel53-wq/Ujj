#!/usr/bin/env python3
"""Instagram API bridge — called from PHP via stdin/stdout JSON."""

import json
import os
import sys

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PY_HANDLER = os.path.join(os.path.dirname(BASE), "instagram-handler")
sys.path.insert(0, PY_HANDLER)

from instagrapi import Client
from instagrapi.exceptions import (
    ChallengeRequired,
    ClientError,
    LoginRequired,
    PleaseWaitFewMinutes,
    TwoFactorRequired,
)


def build_client(proxy: str = "") -> Client:
    client = Client()
    client.delay_range = [1, 3]
    if proxy:
        client.set_proxy(proxy)
    return client


def action_login(params: dict) -> dict:
    client = build_client(params.get("proxy", ""))
    session_path = params.get("session_path", "")
    username = params["username"]
    password = params.get("password", "")

    if session_path and os.path.exists(session_path):
        client.load_settings(session_path)
        try:
            client.login(username, password or "")
        except Exception:
            pass

    if not client.user_id:
        if params.get("verification_code"):
            client.login(username, password, verification_code=params["verification_code"])
        else:
            client.login(username, password)

    if session_path:
        client.dump_settings(session_path)

    info = client.account_info()
    return {
        "username": info.username,
        "full_name": info.full_name or "",
        "followers": info.follower_count,
        "following": info.following_count,
        "posts_count": info.media_count,
        "profile_pic": str(info.profile_pic_url) if info.profile_pic_url else "",
        "is_verified": bool(info.is_verified),
        "session_settings": client.get_settings(),
    }


def action_signup(params: dict) -> dict:
    client = build_client(params.get("proxy", ""))
    code = params.get("verification_code", "")

    if code:
        client.challenge_code_handler = lambda u, c: code

    user = client.signup(
        username=params["username"],
        password=params["password"],
        email=params["email"],
        phone_number="",
        full_name=params.get("full_name", ""),
        year=params.get("year"),
        month=params.get("month"),
        day=params.get("day"),
    )
    return {
        "username": user.username or params["username"],
        "full_name": params.get("full_name", ""),
        "email": params["email"],
    }


def action_post_photo(params: dict) -> dict:
    client = build_client(params.get("proxy", ""))
    session_path = params.get("session_path", "")
    if session_path and os.path.exists(session_path):
        client.load_settings(session_path)
        client.login(params["username"], "")
    media = client.photo_upload(params["image_path"], params.get("caption", ""))
    return {"media_id": str(media.pk), "code": media.code}


def main() -> None:
    payload = json.load(sys.stdin)
    action = payload.get("action")
    params = payload.get("params", {})
    try:
        if action == "login":
            result = action_login(params)
        elif action == "signup":
            result = action_signup(params)
        elif action == "post_photo":
            result = action_post_photo(params)
        else:
            raise ValueError(f"Unknown action: {action}")
        print(json.dumps({"success": True, "data": result}))
    except TwoFactorRequired as exc:
        print(json.dumps({"success": False, "error": str(exc), "needs_2fa": True}))
    except ChallengeRequired as exc:
        print(json.dumps({"success": False, "error": "Challenge required: " + str(exc), "needs_code": True}))
    except PleaseWaitFewMinutes as exc:
        print(json.dumps({"success": False, "error": f"Rate limited: {exc}"}))
    except (LoginRequired, ClientError, AssertionError, Exception) as exc:
        msg = str(exc)
        needs_code = "code" in msg.lower() or "verification" in msg.lower()
        print(json.dumps({"success": False, "error": msg, "needs_code": needs_code}))


if __name__ == "__main__":
    main()
