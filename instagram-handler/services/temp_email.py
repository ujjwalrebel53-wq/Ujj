import random
import re
import string
import time
from typing import Any

import requests

CODE_PATTERN = re.compile(r"\b(\d{6})\b")

PROVIDERS = [
  "mailtm",
  "1secmail",
]


class TempEmailError(Exception):
    pass


class TempEmail:
    def __init__(self, address: str | None = None, provider: str | None = None):
        self.provider = provider or "mailtm"
        self.token = ""
        self.password = ""
        if address:
            self.address = address
            self.login, self.domain = address.split("@", 1)
        else:
            self._create_mailbox()

    def _create_mailbox(self) -> None:
        errors = []
        providers = [self.provider] + [p for p in PROVIDERS if p != self.provider]
        for name in providers:
            try:
                if name == "mailtm":
                    self._create_mailtm()
                elif name == "1secmail":
                    self._create_1secmail()
                self.provider = name
                return
            except Exception as exc:
                errors.append(f"{name}: {exc}")
        raise TempEmailError("All temp email providers failed — " + "; ".join(errors))

    def _create_mailtm(self) -> None:
        domains_resp = requests.get("https://api.mail.tm/domains", timeout=15)
        domains_resp.raise_for_status()
        domains = [d["domain"] for d in domains_resp.json().get("hydra:member", []) if d.get("isActive")]
        if not domains:
            raise TempEmailError("No mail.tm domains available")
        self.domain = random.choice(domains)
        self.login = "ig" + "".join(random.choices(string.ascii_lowercase + string.digits, k=10))
        self.address = f"{self.login}@{self.domain}"
        self.password = "".join(random.choices(string.ascii_letters + string.digits, k=16))
        acc_resp = requests.post(
            "https://api.mail.tm/accounts",
            json={"address": self.address, "password": self.password},
            timeout=15,
        )
        acc_resp.raise_for_status()
        token_resp = requests.post(
            "https://api.mail.tm/token",
            json={"address": self.address, "password": self.password},
            timeout=15,
        )
        token_resp.raise_for_status()
        self.token = token_resp.json().get("token", "")

    def _create_1secmail(self) -> None:
        resp = requests.get(
            "https://www.1secmail.com/api/v1/?action=genRandomMailbox&count=1",
            timeout=15,
            headers={"User-Agent": "Mozilla/5.0"},
        )
        resp.raise_for_status()
        data = resp.json()
        if not data:
            raise TempEmailError("Failed to generate 1secmail address")
        self.address = data[0]
        self.login, self.domain = self.address.split("@", 1)

    def get_messages(self) -> list[dict[str, Any]]:
        if self.provider == "mailtm":
            resp = requests.get(
                "https://api.mail.tm/messages",
                headers={"Authorization": f"Bearer {self.token}"},
                timeout=15,
            )
            resp.raise_for_status()
            return resp.json().get("hydra:member", [])
        resp = requests.get(
            f"https://www.1secmail.com/api/v1/?action=getMessages&login={self.login}&domain={self.domain}",
            timeout=15,
            headers={"User-Agent": "Mozilla/5.0"},
        )
        resp.raise_for_status()
        return resp.json()

    def read_message(self, msg_id: str | int) -> dict[str, Any]:
        if self.provider == "mailtm":
            resp = requests.get(
                f"https://api.mail.tm/messages/{msg_id}",
                headers={"Authorization": f"Bearer {self.token}"},
                timeout=15,
            )
            resp.raise_for_status()
            data = resp.json()
            return {
                "subject": data.get("subject", ""),
                "textBody": data.get("text", ""),
                "htmlBody": data.get("html", ""),
            }
        resp = requests.get(
            f"https://www.1secmail.com/api/v1/?action=readMessage&login={self.login}&domain={self.domain}&id={msg_id}",
            timeout=15,
            headers={"User-Agent": "Mozilla/5.0"},
        )
        resp.raise_for_status()
        return resp.json()

    def extract_code(self, text: str) -> str | None:
        match = CODE_PATTERN.search(text or "")
        return match.group(1) if match else None

    def wait_for_code(self, timeout: int = 120, poll_interval: int = 5) -> str:
        seen: set[str] = set()
        deadline = time.time() + timeout
        while time.time() < deadline:
            for msg in self.get_messages():
                msg_id = str(msg.get("id", ""))
                if not msg_id or msg_id in seen:
                    continue
                seen.add(msg_id)
                if self.provider == "mailtm":
                    body = f"{msg.get('subject', '')} {msg.get('intro', '')}"
                    full = self.read_message(msg_id)
                    body += f" {full.get('textBody', '')} {full.get('htmlBody', '')}"
                else:
                    full = self.read_message(msg_id)
                    body = f"{full.get('subject', '')} {full.get('textBody', '')} {full.get('htmlBody', '')}"
                code = self.extract_code(body)
                if code:
                    return code
            time.sleep(poll_interval)
        raise TempEmailError(f"Verification code not received within {timeout}s")
