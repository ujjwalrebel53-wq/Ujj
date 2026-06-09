#!/usr/bin/env python3
"""Instagram API bridge — PHP se call hota hai."""

import json
import os
import random
import re
import sys
import time

import requests
from instagrapi import Client
from instagrapi.exceptions import (
    ChallengeRequired,
    ClientError,
    LoginRequired,
    PleaseWaitFewMinutes,
    TwoFactorRequired,
)

CODE_RE = re.compile(r"\b(\d{6})\b")


class TempMail:
    def __init__(self):
        self.provider = "mailtm"
        self.token = ""
        self.login = ""
        self.domain = ""
        self.password = ""
        self.address = self._create()

    def _create(self) -> str:
        for provider in ("mailtm", "1secmail"):
            try:
                if provider == "mailtm":
                    return self._create_mailtm()
                return self._create_1secmail()
            except Exception:
                continue
        raise RuntimeError("Temp email providers failed")

    def _create_mailtm(self) -> str:
        r = requests.get("https://api.mail.tm/domains", timeout=20)
        r.raise_for_status()
        data = r.json()
        items = data.get("hydra:member") or (data if isinstance(data, list) else [])
        active = [d for d in items if d.get("isActive")]
        if not active:
            raise RuntimeError("no mail.tm domains")
        domain = random.choice(active)["domain"]
        self.login = "ig" + os.urandom(5).hex()
        self.domain = domain
        self.address = f"{self.login}@{domain}"
        self.password = os.urandom(8).hex()
        requests.post(
            "https://api.mail.tm/accounts",
            json={"address": self.address, "password": self.password},
            timeout=20,
        ).raise_for_status()
        tok = requests.post(
            "https://api.mail.tm/token",
            json={"address": self.address, "password": self.password},
            timeout=20,
        ).json()
        self.token = tok.get("token", "")
        self.provider = "mailtm"
        return self.address

    def _create_1secmail(self) -> str:
        r = requests.get(
            "https://www.1secmail.com/api/v1/?action=genRandomMailbox&count=1",
            headers={"User-Agent": "Mozilla/5.0"},
            timeout=20,
        )
        r.raise_for_status()
        self.address = r.json()[0]
        self.login, self.domain = self.address.split("@", 1)
        self.provider = "1secmail"
        return self.address

    def _messages(self) -> list:
        if self.provider == "mailtm":
            r = requests.get(
                "https://api.mail.tm/messages",
                headers={"Authorization": f"Bearer {self.token}"},
                timeout=20,
            )
            data = r.json()
            return data.get("hydra:member") or (data if isinstance(data, list) else [])
        r = requests.get(
            f"https://www.1secmail.com/api/v1/?action=getMessages&login={self.login}&domain={self.domain}",
            headers={"User-Agent": "Mozilla/5.0"},
            timeout=20,
        )
        return r.json()

    def _read_body(self, msg: dict) -> str:
        if self.provider == "mailtm":
            mid = msg.get("id")
            r = requests.get(
                f"https://api.mail.tm/messages/{mid}",
                headers={"Authorization": f"Bearer {self.token}"},
                timeout=20,
            )
            full = r.json()
            return f"{msg.get('subject','')} {msg.get('intro','')} {full.get('text','')} {full.get('html','')}"
        mid = msg.get("id")
        r = requests.get(
            f"https://www.1secmail.com/api/v1/?action=readMessage&login={self.login}&domain={self.domain}&id={mid}",
            headers={"User-Agent": "Mozilla/5.0"},
            timeout=20,
        )
        full = r.json()
        return f"{full.get('subject','')} {full.get('textBody','')} {full.get('htmlBody','')}"

    def wait_for_code(self, timeout: int = 120) -> str:
        seen = set()
        deadline = time.time() + timeout
        while time.time() < deadline:
            for msg in self._messages():
                mid = str(msg.get("id", ""))
                if not mid or mid in seen:
                    continue
                seen.add(mid)
                body = self._read_body(msg)
                m = CODE_RE.search(body)
                if m:
                    return m.group(1)
            time.sleep(5)
        raise RuntimeError("OTP not received from temp email")


def build_client(proxy: str = "") -> Client:
    client = Client()
    client.delay_range = [2, 5]
    if proxy:
        client.set_proxy(proxy)
    client.set_uuids({})
    client.device_id = client.android_device_id
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
    temp_mail = None
    email = (params.get("email") or "").strip()

    if not email:
        temp_mail = TempMail()
        email = temp_mail.address

    preset_code = (params.get("verification_code") or "").strip()

    def code_handler(username, choice):
        if preset_code:
            return preset_code
        if temp_mail:
            return temp_mail.wait_for_code(120)
        return ""

    client.challenge_code_handler = code_handler

    user = client.signup(
        username=params["username"],
        password=params["password"],
        email=email,
        phone_number="",
        full_name=params.get("full_name", ""),
        year=params.get("year"),
        month=params.get("month"),
        day=params.get("day"),
    )
    return {
        "username": user.username or params["username"],
        "full_name": params.get("full_name", ""),
        "email": email,
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
        needs_code = "code" in msg.lower() or "verification" in msg.lower() or "otp" in msg.lower()
        print(json.dumps({"success": False, "error": msg, "needs_code": needs_code}))


if __name__ == "__main__":
    main()
