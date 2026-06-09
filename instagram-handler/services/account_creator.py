import random
import re
import string
import threading
import time
import uuid
from datetime import datetime, timezone
from typing import Any

from instagrapi import Client
from instagrapi.exceptions import ClientError, PleaseWaitFewMinutes

from services import database as db
from services.crypto import encrypt
from services.proxy_manager import resolve_proxy
from services.temp_email import TempEmail, TempEmailError

FIRST_NAMES = [
    "Aarav", "Vihaan", "Arjun", "Rohan", "Kabir", "Ishaan", "Dev", "Karan",
    "Priya", "Ananya", "Diya", "Sneha", "Riya", "Meera", "Kavya", "Nisha",
    "Alex", "Jordan", "Sam", "Taylor", "Morgan", "Casey", "Riley", "Avery",
]
LAST_NAMES = [
    "Sharma", "Patel", "Singh", "Kumar", "Gupta", "Verma", "Reddy", "Joshi",
    "Mehta", "Shah", "Rao", "Nair", "Das", "Roy", "Mishra", "Pandey",
    "Smith", "Johnson", "Williams", "Brown", "Davis", "Miller", "Wilson",
]
USERNAME_ADJECTIVES = ["cool", "real", "daily", "life", "the", "its", "hey", "just"]
USERNAME_NOUNS = ["vibes", "world", "soul", "dream", "life", "mood", "zone", "hub"]

_worker_lock = threading.Lock()
_worker_running = False
_pending_codes: dict[int, str] = {}
_code_events: dict[int, threading.Event] = {}


class AccountCreatorError(Exception):
    def __init__(self, message: str, needs_code: bool = False, job_id: int | None = None):
        super().__init__(message)
        self.needs_code = needs_code
        self.job_id = job_id


def generate_password(length: int = 14) -> str:
    chars = string.ascii_letters + string.digits + "!@#$%"
    while True:
        pwd = "".join(random.choice(chars) for _ in range(length))
        if (
            any(c.isupper() for c in pwd)
            and any(c.islower() for c in pwd)
            and any(c.isdigit() for c in pwd)
        ):
            return pwd


def generate_full_name() -> str:
    return f"{random.choice(FIRST_NAMES)} {random.choice(LAST_NAMES)}"


def generate_username(prefix: str = "") -> str:
    base = re.sub(r"[^a-z0-9_]", "", prefix.lower()) if prefix else ""
    if not base:
        base = f"{random.choice(USERNAME_ADJECTIVES)}_{random.choice(USERNAME_NOUNS)}"
    suffix = "".join(random.choices(string.ascii_lowercase + string.digits, k=random.randint(4, 6)))
    username = f"{base}_{suffix}"
    return username[:30]


def generate_birthday() -> tuple[int, int, int]:
    year = random.randint(1994, 2002)
    month = random.randint(1, 12)
    day = random.randint(1, 28)
    return year, month, day


def preview_profiles(count: int = 5, prefix: str = "") -> list[dict[str, str]]:
    profiles = []
    for _ in range(min(count, 20)):
        full_name = generate_full_name()
        profiles.append(
            {
                "username": generate_username(prefix),
                "password": generate_password(),
                "full_name": full_name,
            }
        )
    return profiles


def _build_client(proxy: str = "") -> Client:
    client = Client()
    client.delay_range = [2, 5]
    if proxy:
        client.set_proxy(proxy)
    return client


def submit_verification_code(job_id: int, code: str) -> None:
    _pending_codes[job_id] = code.strip()
    event = _code_events.get(job_id)
    if event:
        event.set()


def _make_code_handler(job_id: int, temp_email: TempEmail | None):
    def handler(username: str, choice) -> str:
        if job_id in _pending_codes:
            code = _pending_codes.pop(job_id)
            return code
        if temp_email:
            try:
                return temp_email.wait_for_code(timeout=90)
            except TempEmailError:
                pass
        event = _code_events.setdefault(job_id, threading.Event())
        db.update_creation_job(job_id, {"status": "waiting_code"})
        if event.wait(timeout=300):
            return _pending_codes.pop(job_id, "")
        return ""

    return handler


def create_instagram_account(
    job_id: int | None = None,
    *,
    username: str | None = None,
    password: str | None = None,
    full_name: str | None = None,
    email: str | None = None,
    proxy: str = "",
    group_name: str = "auto-created",
    verification_code: str | None = None,
) -> dict[str, Any]:
    username = username or generate_username()
    password = password or generate_password()
    full_name = full_name or generate_full_name()
    year, month, day = generate_birthday()

    temp_mail = None
    if not email:
        temp_mail = TempEmail()
        email = temp_mail.address
    elif verification_code and job_id:
        _pending_codes[job_id] = verification_code

    proxy = resolve_proxy(proxy)
    client = _build_client(proxy)
    if job_id:
        client.challenge_code_handler = _make_code_handler(job_id, temp_mail)

    try:
        user = client.signup(
            username=username,
            password=password,
            email=email,
            phone_number="",
            full_name=full_name,
            year=year,
            month=month,
            day=day,
        )
        account = db.create_account(
            {
                "username": user.username or username,
                "password_enc": encrypt(password),
                "proxy": proxy,
                "group_name": group_name,
                "notes": f"Auto-created | {email}",
                "status": "active",
            }
        )
        db.update_account(
            account["id"],
            {
                "full_name": full_name,
                "last_login": datetime.now(timezone.utc).isoformat(),
            },
        )
        db.log_activity(account["id"], "auto_create", f"Account @{username} created via auto-creator")
        return {
            "success": True,
            "account": account,
            "username": user.username or username,
            "password": password,
            "email": email,
            "full_name": full_name,
        }
    except AssertionError as exc:
        raise AccountCreatorError(str(exc)) from exc
    except PleaseWaitFewMinutes as exc:
        raise AccountCreatorError(f"Rate limited: {exc}") from exc
    except ClientError as exc:
        raise AccountCreatorError(str(exc)) from exc
    except Exception as exc:
        msg = str(exc)
        if "code" in msg.lower() or not verification_code:
            raise AccountCreatorError(msg, needs_code=True, job_id=job_id) from exc
        raise AccountCreatorError(msg) from exc
    finally:
        if job_id:
            _code_events.pop(job_id, None)
            _pending_codes.pop(job_id, None)


def start_single_creation(data: dict[str, Any]) -> dict[str, Any]:
    job_proxy = resolve_proxy(data.get("proxy", ""), auto=data.get("use_webshare", True))
    job = db.create_creation_job(
        {
            "username": data.get("username") or generate_username(data.get("username_prefix", "")),
            "email": data.get("email", ""),
            "password": data.get("password") or generate_password(),
            "full_name": data.get("full_name") or generate_full_name(),
            "proxy": job_proxy,
            "group_name": data.get("group_name", "auto-created"),
            "job_batch_id": "single",
        }
    )
    db.update_creation_job(job["id"], {"status": "creating"})
    try:
        result = create_instagram_account(
            job_id=job["id"],
            username=job["username"],
            password=job["password"],
            full_name=job["full_name"],
            email=job["email"] or None,
            proxy=job["proxy"],
            group_name=job["group_name"],
            verification_code=data.get("verification_code"),
        )
        db.update_creation_job(
            job["id"],
            {
                "status": "success",
                "account_id": result["account"]["id"],
                "email": result["email"],
            },
        )
        return {"job": db.get_creation_job(job["id"]), **result}
    except AccountCreatorError as exc:
        status = "waiting_code" if exc.needs_code else "failed"
        db.update_creation_job(job["id"], {"status": status, "error": str(exc)})
        return {
            "job": db.get_creation_job(job["id"]),
            "success": False,
            "error": str(exc),
            "needs_code": exc.needs_code,
        }


def start_batch_creation(data: dict[str, Any]) -> dict[str, Any]:
    count = min(int(data.get("count", 1)), 20)
    batch_id = uuid.uuid4().hex[:12]
    delay = max(int(data.get("delay_seconds", 30)), 10)
    prefix = data.get("username_prefix", "")
    explicit_proxy = data.get("proxy", "")
    group = data.get("group_name", "auto-created")
    auto_proxy = data.get("use_webshare", True)

    jobs = []
    for _ in range(count):
        job_proxy = resolve_proxy(explicit_proxy, auto=auto_proxy)
        job = db.create_creation_job(
            {
                "username": generate_username(prefix),
                "password": generate_password(),
                "full_name": generate_full_name(),
                "proxy": job_proxy,
                "group_name": group,
                "job_batch_id": batch_id,
            }
        )
        jobs.append(job)

    _start_worker(batch_id, delay)
    return {"batch_id": batch_id, "count": count, "jobs": jobs}


def _start_worker(batch_id: str, delay: int) -> None:
    global _worker_running
    with _worker_lock:
        if _worker_running:
            return
        _worker_running = True
    thread = threading.Thread(target=_process_queue, args=(delay,), daemon=True)
    thread.start()


def _process_queue(delay: int) -> None:
    global _worker_running
    try:
        while True:
            jobs = db.list_pending_creation_jobs()
            if not jobs:
                break
            job = jobs[0]
            db.update_creation_job(job["id"], {"status": "creating"})
            try:
                result = create_instagram_account(
                    job_id=job["id"],
                    username=job["username"],
                    password=job["password"],
                    full_name=job["full_name"],
                    email=job["email"] or None,
                    proxy=job["proxy"],
                    group_name=job["group_name"],
                )
                db.update_creation_job(
                    job["id"],
                    {
                        "status": "success",
                        "account_id": result["account"]["id"],
                        "email": result["email"],
                        "error": "",
                    },
                )
            except AccountCreatorError as exc:
                status = "waiting_code" if exc.needs_code else "failed"
                db.update_creation_job(job["id"], {"status": status, "error": str(exc)})
            except Exception as exc:
                db.update_creation_job(job["id"], {"status": "failed", "error": str(exc)})
            time.sleep(delay)
    finally:
        with _worker_lock:
            _worker_running = False
        if db.list_pending_creation_jobs():
            _start_worker("", delay)


def retry_job(job_id: int) -> dict[str, Any]:
    job = db.get_creation_job(job_id)
    if not job:
        raise AccountCreatorError("Job not found")
    db.update_creation_job(job_id, {"status": "creating", "error": ""})
    try:
        result = create_instagram_account(
            job_id=job_id,
            username=job["username"],
            password=job["password"],
            full_name=job["full_name"],
            email=job["email"] or None,
            proxy=job["proxy"],
            group_name=job["group_name"],
        )
        db.update_creation_job(
            job_id,
            {"status": "success", "account_id": result["account"]["id"], "email": result["email"]},
        )
        return {"success": True, "job": db.get_creation_job(job_id), **result}
    except AccountCreatorError as exc:
        status = "waiting_code" if exc.needs_code else "failed"
        db.update_creation_job(job_id, {"status": status, "error": str(exc)})
        return {"success": False, "job": db.get_creation_job(job_id), "error": str(exc), "needs_code": exc.needs_code}


def verify_job_code(job_id: int, code: str) -> dict[str, Any]:
    submit_verification_code(job_id, code)
    job = db.get_creation_job(job_id)
    if not job:
        raise AccountCreatorError("Job not found")
    if job["status"] not in ("waiting_code", "failed", "creating"):
        return {"job": job, "message": "Code submitted, waiting for processing"}
    return retry_job(job_id)
