import json
import os
import sqlite3
from contextlib import contextmanager
from datetime import datetime, timezone
from typing import Any

DATA_DIR = os.path.join(os.path.dirname(os.path.dirname(__file__)), "data")
DB_PATH = os.path.join(DATA_DIR, "accounts.db")


def _utcnow() -> str:
    return datetime.now(timezone.utc).isoformat()


def init_db() -> None:
    os.makedirs(DATA_DIR, exist_ok=True)
    with get_conn() as conn:
        conn.executescript(
            """
            CREATE TABLE IF NOT EXISTS accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_enc TEXT DEFAULT '',
                session_data TEXT DEFAULT '',
                proxy TEXT DEFAULT '',
                group_name TEXT DEFAULT 'default',
                notes TEXT DEFAULT '',
                status TEXT DEFAULT 'inactive',
                followers INTEGER DEFAULT 0,
                following INTEGER DEFAULT 0,
                posts_count INTEGER DEFAULT 0,
                profile_pic TEXT DEFAULT '',
                full_name TEXT DEFAULT '',
                is_verified INTEGER DEFAULT 0,
                last_login TEXT DEFAULT '',
                last_error TEXT DEFAULT '',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER,
                action TEXT NOT NULL,
                details TEXT DEFAULT '',
                status TEXT DEFAULT 'success',
                created_at TEXT NOT NULL,
                FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS scheduled_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER NOT NULL,
                caption TEXT DEFAULT '',
                media_path TEXT NOT NULL,
                scheduled_at TEXT NOT NULL,
                status TEXT DEFAULT 'pending',
                posted_at TEXT DEFAULT '',
                error TEXT DEFAULT '',
                created_at TEXT NOT NULL,
                FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS creation_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                email TEXT DEFAULT '',
                password TEXT DEFAULT '',
                full_name TEXT DEFAULT '',
                proxy TEXT DEFAULT '',
                group_name TEXT DEFAULT 'auto-created',
                status TEXT DEFAULT 'pending',
                error TEXT DEFAULT '',
                account_id INTEGER,
                job_batch_id TEXT DEFAULT '',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
            """
        )


@contextmanager
def get_conn():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    try:
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


def row_to_dict(row: sqlite3.Row | None) -> dict[str, Any] | None:
    if row is None:
        return None
    return dict(row)


def log_activity(account_id: int | None, action: str, details: str = "", status: str = "success") -> None:
    with get_conn() as conn:
        conn.execute(
            "INSERT INTO activity_log (account_id, action, details, status, created_at) VALUES (?, ?, ?, ?, ?)",
            (account_id, action, details, status, _utcnow()),
        )


def get_setting(key: str, default: str = "") -> str:
    with get_conn() as conn:
        row = conn.execute("SELECT value FROM settings WHERE key = ?", (key,)).fetchone()
        return row["value"] if row else default


def set_setting(key: str, value: str) -> None:
    with get_conn() as conn:
        conn.execute(
            "INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value",
            (key, value),
        )


def list_accounts(group: str | None = None) -> list[dict[str, Any]]:
    with get_conn() as conn:
        if group:
            rows = conn.execute(
                "SELECT * FROM accounts WHERE group_name = ? ORDER BY username",
                (group,),
            ).fetchall()
        else:
            rows = conn.execute("SELECT * FROM accounts ORDER BY group_name, username").fetchall()
    return [sanitize_account(row_to_dict(r)) for r in rows]


def get_account(account_id: int) -> dict[str, Any] | None:
    with get_conn() as conn:
        row = conn.execute("SELECT * FROM accounts WHERE id = ?", (account_id,)).fetchone()
    return sanitize_account(row_to_dict(row))


def get_account_by_username(username: str) -> dict[str, Any] | None:
    with get_conn() as conn:
        row = conn.execute("SELECT * FROM accounts WHERE username = ?", (username.lower(),)).fetchone()
    return sanitize_account(row_to_dict(row))


def sanitize_account(account: dict[str, Any] | None) -> dict[str, Any] | None:
    if not account:
        return None
    account.pop("password_enc", None)
    account.pop("session_data", None)
    return account


def create_account(data: dict[str, Any]) -> dict[str, Any]:
    now = _utcnow()
    with get_conn() as conn:
        cur = conn.execute(
            """
            INSERT INTO accounts (
                username, password_enc, session_data, proxy, group_name, notes,
                status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            (
                data["username"].lower(),
                data.get("password_enc", ""),
                data.get("session_data", ""),
                data.get("proxy", ""),
                data.get("group_name", "default"),
                data.get("notes", ""),
                data.get("status", "inactive"),
                now,
                now,
            ),
        )
        account_id = cur.lastrowid
    log_activity(account_id, "account_created", f"Added @{data['username']}")
    return get_account(account_id)


def update_account(account_id: int, data: dict[str, Any]) -> dict[str, Any] | None:
    fields = []
    values = []
    allowed = {
        "password_enc",
        "session_data",
        "proxy",
        "group_name",
        "notes",
        "status",
        "followers",
        "following",
        "posts_count",
        "profile_pic",
        "full_name",
        "is_verified",
        "last_login",
        "last_error",
    }
    for key, value in data.items():
        if key in allowed:
            fields.append(f"{key} = ?")
            values.append(value)
    if not fields:
        return get_account(account_id)
    fields.append("updated_at = ?")
    values.append(_utcnow())
    values.append(account_id)
    with get_conn() as conn:
        conn.execute(f"UPDATE accounts SET {', '.join(fields)} WHERE id = ?", values)
    return get_account(account_id)


def delete_account(account_id: int) -> bool:
    with get_conn() as conn:
        cur = conn.execute("DELETE FROM accounts WHERE id = ?", (account_id,))
    if cur.rowcount:
        log_activity(None, "account_deleted", f"Account ID {account_id} removed")
    return cur.rowcount > 0


def get_account_secrets(account_id: int) -> dict[str, Any] | None:
    with get_conn() as conn:
        row = conn.execute("SELECT * FROM accounts WHERE id = ?", (account_id,)).fetchone()
    return row_to_dict(row)


def list_activity(limit: int = 50) -> list[dict[str, Any]]:
    with get_conn() as conn:
        rows = conn.execute(
            """
            SELECT a.*, acc.username
            FROM activity_log a
            LEFT JOIN accounts acc ON a.account_id = acc.id
            ORDER BY a.id DESC LIMIT ?
            """,
            (limit,),
        ).fetchall()
    return [dict(r) for r in rows]


def list_groups() -> list[str]:
    with get_conn() as conn:
        rows = conn.execute("SELECT DISTINCT group_name FROM accounts ORDER BY group_name").fetchall()
    return [r["group_name"] for r in rows]


def get_stats() -> dict[str, Any]:
    with get_conn() as conn:
        total = conn.execute("SELECT COUNT(*) as c FROM accounts").fetchone()["c"]
        active = conn.execute("SELECT COUNT(*) as c FROM accounts WHERE status = 'active'").fetchone()["c"]
        error = conn.execute("SELECT COUNT(*) as c FROM accounts WHERE status = 'error'").fetchone()["c"]
        pending = conn.execute(
            "SELECT COUNT(*) as c FROM scheduled_posts WHERE status = 'pending'"
        ).fetchone()["c"]
        created = conn.execute(
            "SELECT COUNT(*) as c FROM creation_jobs WHERE status = 'success'"
        ).fetchone()["c"]
        creating = conn.execute(
            "SELECT COUNT(*) as c FROM creation_jobs WHERE status IN ('pending', 'creating', 'waiting_code')"
        ).fetchone()["c"]
    return {
        "total_accounts": total,
        "active_accounts": active,
        "error_accounts": error,
        "pending_posts": pending,
        "groups": len(list_groups()),
        "accounts_created": created,
        "creation_in_progress": creating,
    }


def create_scheduled_post(data: dict[str, Any]) -> dict[str, Any]:
    now = _utcnow()
    with get_conn() as conn:
        cur = conn.execute(
            """
            INSERT INTO scheduled_posts (account_id, caption, media_path, scheduled_at, created_at)
            VALUES (?, ?, ?, ?, ?)
            """,
            (data["account_id"], data.get("caption", ""), data["media_path"], data["scheduled_at"], now),
        )
        post_id = cur.lastrowid
        row = conn.execute("SELECT * FROM scheduled_posts WHERE id = ?", (post_id,)).fetchone()
    return dict(row)


def list_scheduled_posts(status: str | None = None) -> list[dict[str, Any]]:
    with get_conn() as conn:
        if status:
            rows = conn.execute(
                """
                SELECT sp.*, a.username
                FROM scheduled_posts sp
                JOIN accounts a ON sp.account_id = a.id
                WHERE sp.status = ?
                ORDER BY sp.scheduled_at
                """,
                (status,),
            ).fetchall()
        else:
            rows = conn.execute(
                """
                SELECT sp.*, a.username
                FROM scheduled_posts sp
                JOIN accounts a ON sp.account_id = a.id
                ORDER BY sp.scheduled_at DESC
                """
            ).fetchall()
    return [dict(r) for r in rows]


def update_scheduled_post(post_id: int, data: dict[str, Any]) -> None:
    fields = []
    values = []
    for key in ("status", "posted_at", "error"):
        if key in data:
            fields.append(f"{key} = ?")
            values.append(data[key])
    if not fields:
        return
    values.append(post_id)
    with get_conn() as conn:
        conn.execute(f"UPDATE scheduled_posts SET {', '.join(fields)} WHERE id = ?", values)


def create_creation_job(data: dict[str, Any]) -> dict[str, Any]:
    now = _utcnow()
    with get_conn() as conn:
        cur = conn.execute(
            """
            INSERT INTO creation_jobs (
                username, email, password, full_name, proxy, group_name,
                status, job_batch_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            (
                data["username"],
                data.get("email", ""),
                data.get("password", ""),
                data.get("full_name", ""),
                data.get("proxy", ""),
                data.get("group_name", "auto-created"),
                data.get("status", "pending"),
                data.get("job_batch_id", ""),
                now,
                now,
            ),
        )
        job_id = cur.lastrowid
    return get_creation_job(job_id)


def get_creation_job(job_id: int) -> dict[str, Any] | None:
    with get_conn() as conn:
        row = conn.execute("SELECT * FROM creation_jobs WHERE id = ?", (job_id,)).fetchone()
    return row_to_dict(row)


def update_creation_job(job_id: int, data: dict[str, Any]) -> dict[str, Any] | None:
    fields = []
    values = []
    allowed = {
        "username", "email", "password", "full_name", "proxy", "group_name",
        "status", "error", "account_id", "job_batch_id",
    }
    for key, value in data.items():
        if key in allowed:
            fields.append(f"{key} = ?")
            values.append(value)
    if not fields:
        return get_creation_job(job_id)
    fields.append("updated_at = ?")
    values.append(_utcnow())
    values.append(job_id)
    with get_conn() as conn:
        conn.execute(f"UPDATE creation_jobs SET {', '.join(fields)} WHERE id = ?", values)
    return get_creation_job(job_id)


def list_creation_jobs(batch_id: str | None = None, limit: int = 50) -> list[dict[str, Any]]:
    with get_conn() as conn:
        if batch_id:
            rows = conn.execute(
                "SELECT * FROM creation_jobs WHERE job_batch_id = ? ORDER BY id DESC LIMIT ?",
                (batch_id, limit),
            ).fetchall()
        else:
            rows = conn.execute(
                "SELECT * FROM creation_jobs ORDER BY id DESC LIMIT ?",
                (limit,),
            ).fetchall()
    return [dict(r) for r in rows]


def list_pending_creation_jobs() -> list[dict[str, Any]]:
    with get_conn() as conn:
        rows = conn.execute(
            """
            SELECT * FROM creation_jobs
            WHERE status = 'pending'
            ORDER BY id ASC
            """
        ).fetchall()
    return [dict(r) for r in rows]


def export_accounts() -> str:
    with get_conn() as conn:
        rows = conn.execute(
            "SELECT username, proxy, group_name, notes, status, followers, following, posts_count, full_name FROM accounts"
        ).fetchall()
    return json.dumps([dict(r) for r in rows], indent=2)
