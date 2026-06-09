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
LOG_FILE = os.path.join(os.path.dirname(os.path.dirname(__file__)), "data", "logs", "live.log")


def plog(level: str, message: str, **ctx):
    entry = {
        "id": f"log_py_{time.time()}",
        "time": time.strftime("%Y-%m-%d %H:%M:%S", time.gmtime()),
        "level": level,
        "message": message,
        "context": ctx,
    }
    os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)
    with open(LOG_FILE, "a", encoding="utf-8") as f:
        f.write(json.dumps(entry, ensure_ascii=False) + "\n")


def is_rate_limited(err) -> bool:
    s = str(err).lower()
    return "429" in s or "too many" in s or "rate limit" in s or "please wait" in s


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
        plog("INFO", f"OTP wait shuru: {self.address}")
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
                    plog("SUCCESS", f"OTP mil gaya: {m.group(1)}", email=self.address)
                    return m.group(1)
            time.sleep(5)
        plog("ERROR", "OTP timeout", email=self.address)
        raise RuntimeError("OTP not received from temp email")


def build_client(proxy: str = "") -> Client:
    client = Client()
    client.delay_range = [10, 25]
    client.request_timeout = 30
    if proxy:
        client.set_proxy(proxy)
        plog("DEBUG", "Proxy set", proxy=proxy[:40] + "...")
    else:
        plog("WARN", "Bina proxy — 429 ka risk zyada hai!")
    client.set_uuids({})
    client.device_id = client.android_device_id
    return client


def _do_signup(params: dict, proxy: str) -> dict:
    username = params["username"]
    full_name = params.get("full_name", "")

    pre_wait = random.randint(20, 50)
    plog("INFO", f"Anti-rate-limit wait {pre_wait}s (Instagram 429 se bachne ke liye)...")
    time.sleep(pre_wait)

    client = build_client(proxy)

    temp_mail = None
    email = (params.get("email") or "").strip()

    if not email:
        plog("INFO", "Temp email bana rahe hain...")
        temp_mail = TempMail()
        email = temp_mail.address
        plog("SUCCESS", f"Temp email: {email}", provider=temp_mail.provider)
    else:
        plog("INFO", f"Email use: {email}")

    preset_code = (params.get("verification_code") or "").strip()

    def code_handler(u, choice):
        if preset_code:
            return preset_code
        if temp_mail:
            plog("INFO", "Instagram ne email bheji, OTP auto fetch...")
            return temp_mail.wait_for_code(120)
        return ""

    client.challenge_code_handler = code_handler

    plog("INFO", "Instagram signup API call...")
    user = client.signup(
        username=username,
        password=params["password"],
        email=email,
        phone_number="",
        full_name=full_name,
        year=params.get("year"),
        month=params.get("month"),
        day=params.get("day"),
    )
    plog("SUCCESS", f"Account ban gaya: @{user.username or username}", email=email)
    return {
        "username": user.username or username,
        "full_name": full_name,
        "email": email,
    }


def action_signup(params: dict) -> dict:
    username = params["username"]
    plog("INFO", f"Signup shuru: @{username}", name=params.get("full_name", ""))

    proxy_list = params.get("proxy_list") or []
    primary = (params.get("proxy") or "").strip()
    if primary and primary not in proxy_list:
        proxy_list.insert(0, primary)
    if not proxy_list:
        proxy_list = [primary] if primary else [""]

    last_err = None
    max_attempts = min(len(proxy_list), 5)

    for attempt in range(max_attempts):
        proxy = proxy_list[attempt % len(proxy_list)]
        if attempt > 0:
            wait = 45 * attempt + random.randint(15, 30)
            plog("WARN", f"429 retry #{attempt} — {wait}s wait + naya proxy", attempt=attempt)
            time.sleep(wait)

        try:
            return _do_signup(params, proxy)
        except (PleaseWaitFewMinutes, Exception) as exc:
            last_err = exc
            if is_rate_limited(exc):
                plog("ERROR", f"429 rate limit attempt {attempt + 1}/{max_attempts}: {exc}")
                continue
            raise

    msg = f"Instagram rate limit (429) — {max_attempts} proxies try kiye. 30-60 min wait karo ya naya proxy use karo. Last: {last_err}"
    plog("ERROR", msg)
    raise RuntimeError(msg)


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
        print(json.dumps({"success": False, "error": f"Rate limited: {exc}", "rate_limited": True}))
    except (LoginRequired, ClientError, AssertionError, Exception) as exc:
        msg = str(exc)
        plog("ERROR", f"Bridge error [{action}]: {msg}")
        rate_limited = is_rate_limited(exc)
        needs_code = "code" in msg.lower() or "verification" in msg.lower() or "otp" in msg.lower()
        print(json.dumps({"success": False, "error": msg, "needs_code": needs_code, "rate_limited": rate_limited}))


if __name__ == "__main__":
    main()
