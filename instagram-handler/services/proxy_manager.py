import os
import random
import time
from typing import Any

import requests

CACHE_FILE = os.path.join(os.path.dirname(os.path.dirname(__file__)), "data", "proxy_cache.json")
CACHE_TTL = 300  # 5 minutes
_index = 0


def get_proxy_url() -> str:
    return os.getenv(
        "WEBSHARE_PROXY_URL",
        "",
    ).strip()


def _parse_line(line: str) -> str:
    line = line.strip()
    if not line or line.startswith("#"):
        return ""
    parts = line.split(":")
    if len(parts) < 4:
        return ""
    host, port, user = parts[0], parts[1], parts[2]
    password = ":".join(parts[3:])
    return f"http://{user}:{password}@{host}:{port}"


def fetch_proxies(force: bool = False) -> list[str]:
    global _index
    url = get_proxy_url()
    if not url:
        return []

    if not force and os.path.exists(CACHE_FILE):
        try:
            import json

            with open(CACHE_FILE, encoding="utf-8") as f:
                cache = json.load(f)
            if time.time() - cache.get("fetched_at", 0) < CACHE_TTL:
                return cache.get("proxies", [])
        except (json.JSONDecodeError, OSError):
            pass

    resp = requests.get(url, timeout=30, headers={"User-Agent": "IG-Handler/1.0"})
    resp.raise_for_status()
    proxies = [_parse_line(line) for line in resp.text.splitlines()]
    proxies = [p for p in proxies if p]

    os.makedirs(os.path.dirname(CACHE_FILE), exist_ok=True)
    import json

    with open(CACHE_FILE, "w", encoding="utf-8") as f:
        json.dump({"fetched_at": time.time(), "proxies": proxies}, f)

    _index = 0
    return proxies


def get_next_proxy() -> str:
    global _index
    proxies = fetch_proxies()
    if not proxies:
        return ""
    proxy = proxies[_index % len(proxies)]
    _index += 1
    return proxy


def get_random_proxy() -> str:
    proxies = fetch_proxies()
    return random.choice(proxies) if proxies else ""


def resolve_proxy(explicit: str = "", auto: bool = True) -> str:
    if explicit and explicit.strip():
        return explicit.strip()
    if auto and get_proxy_url():
        return get_next_proxy()
    return ""


def get_stats() -> dict[str, Any]:
    proxies = fetch_proxies()
    return {
        "enabled": bool(get_proxy_url()),
        "total_proxies": len(proxies),
        "cache_ttl_seconds": CACHE_TTL,
        "provider": "webshare",
    }
