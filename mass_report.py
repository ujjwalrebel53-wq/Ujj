#!/usr/bin/env python3
"""
Advanced Mass Report Script for Educational Security Testing
------------------------------------------------------------
Warning: Use ONLY on authorized test websites owned by your college.
Misuse on real platforms is illegal and unethical.
"""

import argparse
import csv
import json
import logging
import random
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from typing import Dict, List, Optional, Tuple

import requests

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[
        logging.FileHandler("mass_report.log"),
        logging.StreamHandler(sys.stdout),
    ],
)

USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Safari/605.1.15",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36",
    "Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1",
    "Mozilla/5.0 (Windows NT 10.0; rv:78.0) Gecko/20100101 Firefox/78.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:78.0) Gecko/20100101 Firefox/78.0",
]

LOGIN_OK_STATUSES = {200, 201, 204, 302, 303, 307, 308}
REPORT_OK_STATUSES = {200, 201, 204, 302, 303, 307, 308}


class MassReporter:
    def __init__(
        self,
        base_url: str,
        login_endpoint: str,
        report_endpoint: str,
        post_id: str,
        reason: str,
        users_file: str,
        threads: int = 10,
        delay: float = 0.2,
        use_proxies: bool = False,
        proxy_file: Optional[str] = None,
        dry_run: bool = False,
        max_retries: int = 3,
        csrf_field: Optional[str] = None,
    ):
        self.base_url = base_url.rstrip("/")
        self.login_url = f"{self.base_url}/{login_endpoint.lstrip('/')}"
        self.report_url = f"{self.base_url}/{report_endpoint.lstrip('/')}"
        self.post_id = post_id
        self.reason = reason
        self.users = self._load_users(users_file)
        self.threads = threads
        self.delay = delay
        self.use_proxies = use_proxies
        self.proxies = self._load_proxies(proxy_file) if use_proxies and proxy_file else []
        self.dry_run = dry_run
        self.max_retries = max_retries
        self.csrf_field = csrf_field

    def _load_users(self, file_path: str) -> List[Dict[str, str]]:
        """Load users from JSON or CSV."""
        if file_path.endswith(".json"):
            with open(file_path, "r", encoding="utf-8") as f:
                users = json.load(f)
            if not isinstance(users, list):
                logging.error("JSON users file must contain a list of user objects.")
                sys.exit(1)
            return users
        if file_path.endswith(".csv"):
            with open(file_path, "r", encoding="utf-8", newline="") as f:
                reader = csv.DictReader(f)
                return list(reader)
        logging.error("Unsupported users file format. Use .json or .csv")
        sys.exit(1)

    def _load_proxies(self, file_path: str) -> List[str]:
        """Load proxies from file, one per line."""
        with open(file_path, "r", encoding="utf-8") as f:
            return [line.strip() for line in f if line.strip()]

    def _random_headers(self) -> Dict[str, str]:
        return {
            "User-Agent": random.choice(USER_AGENTS),
            "Accept": "application/json, text/plain, */*",
            "Accept-Language": "en-US,en;q=0.5",
            "Referer": self.base_url,
            "Origin": self.base_url,
        }

    def _get_csrf_token(self, session: requests.Session, page_url: Optional[str] = None) -> Optional[str]:
        """If CSRF protection is enabled, fetch token from the given page."""
        if not self.csrf_field:
            return None
        try:
            from bs4 import BeautifulSoup
        except ImportError:
            logging.error("beautifulsoup4 is required when --csrf-field is set. Install: pip install beautifulsoup4")
            return None

        target_url = page_url or self.login_url
        try:
            resp = session.get(target_url, headers=self._random_headers(), timeout=10)
            resp.raise_for_status()
            soup = BeautifulSoup(resp.text, "html.parser")
            token_tag = soup.find("input", {"name": self.csrf_field})
            if token_tag and token_tag.get("value"):
                return token_tag["value"]
            meta = soup.find("meta", {"name": "csrf-token"})
            if meta and meta.get("content"):
                return meta["content"]
            logging.warning("CSRF field specified but not found on %s", target_url)
        except Exception as e:
            logging.error("Failed to fetch CSRF token: %s", e)
        return None

    def _has_auth(self, session: requests.Session, login_resp: requests.Response) -> bool:
        """Detect whether login succeeded via cookies, headers, or JSON body."""
        cookie_names = {cookie.name for cookie in session.cookies}
        if cookie_names.intersection({"session", "sessionid", "auth_token", "access_token"}):
            return True
        if session.headers.get("Authorization"):
            return True
        content_type = login_resp.headers.get("Content-Type", "")
        if "application/json" in content_type:
            try:
                resp_json = login_resp.json()
            except ValueError:
                resp_json = {}
            token = resp_json.get("token") or resp_json.get("access_token")
            if token:
                session.headers.update({"Authorization": f"Bearer {token}"})
                return True
        return False

    def _login_and_report(self, user: Dict[str, str]) -> Tuple[str, bool, Optional[str]]:
        """Login with user credentials, then send report. Returns (username, success, error)."""
        username = user.get("username") or user.get("email")
        password = user.get("password")
        if not username or not password:
            return (username or "unknown", False, "Missing credentials")

        session = requests.Session()
        if self.use_proxies and self.proxies:
            proxy = random.choice(self.proxies)
            session.proxies = {"http": proxy, "https": proxy}

        session.headers.update(self._random_headers())

        for attempt in range(self.max_retries):
            try:
                login_payload: Dict[str, str] = {
                    "username": username,
                    "password": password,
                }
                if self.csrf_field:
                    token = self._get_csrf_token(session)
                    if token:
                        login_payload[self.csrf_field] = token

                login_resp = session.post(
                    self.login_url,
                    data=login_payload,
                    allow_redirects=False,
                    timeout=10,
                )
                if login_resp.status_code not in LOGIN_OK_STATUSES:
                    raise RuntimeError(f"Login status {login_resp.status_code}")

                if not self._has_auth(session, login_resp):
                    raise RuntimeError("No session cookie or token received; login likely failed.")

                if self.dry_run:
                    logging.info("[DRY RUN] %s: Would report post %s", username, self.post_id)
                    return (username, True, None)

                report_payload: Dict[str, str] = {
                    "post_id": self.post_id,
                    "reason": self.reason,
                }
                if self.csrf_field:
                    token = self._get_csrf_token(session, page_url=self.report_url)
                    if token:
                        report_payload[self.csrf_field] = token

                report_resp = session.post(
                    self.report_url,
                    data=report_payload,
                    timeout=10,
                )
                if report_resp.status_code in REPORT_OK_STATUSES:
                    return (username, True, None)
                raise RuntimeError(
                    f"Report failed with status {report_resp.status_code}: {report_resp.text[:100]}"
                )

            except Exception as e:
                logging.warning("%s: Attempt %d failed: %s", username, attempt + 1, e)
                time.sleep(1 * (attempt + 1))

        return (username, False, "All retries exhausted")

    def run(self) -> None:
        logging.info("Starting mass report: %d users, %d threads", len(self.users), self.threads)
        logging.info(
            "Target: %s | Post ID: %s | Reason: %s",
            self.report_url,
            self.post_id,
            self.reason,
        )
        if self.dry_run:
            logging.info("DRY RUN MODE - No actual reports will be sent.")

        success = 0
        failed = 0
        errors: List[str] = []

        with ThreadPoolExecutor(max_workers=self.threads) as executor:
            futures = [executor.submit(self._login_and_report, user) for user in self.users]
            for future in as_completed(futures):
                username, ok, error = future.result()
                if ok:
                    success += 1
                    logging.info("✓ %s: Report sent successfully", username)
                else:
                    failed += 1
                    errors.append(f"{username}: {error}")
                    logging.error("✗ %s: %s", username, error)

                delay = random.uniform(self.delay, self.delay * 2) if failed > success else self.delay
                time.sleep(delay)

        logging.info("Done. Success: %d, Failed: %d", success, failed)
        if errors:
            logging.info("Top errors:")
            for err in errors[:10]:
                logging.info("  %s", err)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Mass report script for authorized educational security testing only."
    )
    parser.add_argument("--base-url", required=True, help="Base URL of the test site")
    parser.add_argument("--login-endpoint", required=True, help="Login path, e.g. api/login")
    parser.add_argument("--report-endpoint", required=True, help="Report path, e.g. api/report")
    parser.add_argument("--post-id", required=True, help="Target post/content ID")
    parser.add_argument("--reason", required=True, help="Report reason text")
    parser.add_argument("--users-file", required=True, help="JSON or CSV file with credentials")
    parser.add_argument("--threads", type=int, default=10, help="Worker thread count")
    parser.add_argument("--delay", type=float, default=0.2, help="Delay between completed jobs")
    parser.add_argument("--use-proxies", action="store_true", help="Enable proxy rotation")
    parser.add_argument("--proxy-file", help="Proxy list file (one proxy per line)")
    parser.add_argument("--dry-run", action="store_true", help="Login only; do not submit reports")
    parser.add_argument("--max-retries", type=int, default=3, help="Retries per user")
    parser.add_argument("--csrf-field", help="CSRF form field name, e.g. csrfmiddlewaretoken")
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    if args.use_proxies and not args.proxy_file:
        logging.error("--proxy-file is required when --use-proxies is enabled.")
        sys.exit(1)

    reporter = MassReporter(
        base_url=args.base_url,
        login_endpoint=args.login_endpoint,
        report_endpoint=args.report_endpoint,
        post_id=args.post_id,
        reason=args.reason,
        users_file=args.users_file,
        threads=args.threads,
        delay=args.delay,
        use_proxies=args.use_proxies,
        proxy_file=args.proxy_file,
        dry_run=args.dry_run,
        max_retries=args.max_retries,
        csrf_field=args.csrf_field,
    )
    reporter.run()


if __name__ == "__main__":
    main()
