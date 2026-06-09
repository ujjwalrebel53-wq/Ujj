import os
import uuid
from datetime import datetime

from dotenv import load_dotenv
from flask import Flask, jsonify, render_template, request, send_from_directory
from flask_cors import CORS
from werkzeug.utils import secure_filename

from services import account_manager as ig
from services import database as db
from services.account_manager import AccountLoginError
from services.crypto import encrypt

load_dotenv()

app = Flask(__name__)
app.config["SECRET_KEY"] = os.getenv("SECRET_KEY", "dev-secret-change-me")
app.config["MAX_CONTENT_LENGTH"] = 50 * 1024 * 1024
CORS(app)

UPLOAD_DIR = os.path.join(os.path.dirname(__file__), "data", "uploads")
os.makedirs(UPLOAD_DIR, exist_ok=True)
ALLOWED_EXT = {".jpg", ".jpeg", ".png", ".webp", ".mp4"}


def allowed_file(filename: str) -> bool:
    return os.path.splitext(filename.lower())[1] in ALLOWED_EXT


@app.before_request
def ensure_db():
    if not getattr(app, "_db_ready", False):
        db.init_db()
        app._db_ready = True


@app.route("/")
def index():
    return render_template("index.html")


@app.route("/api/stats")
def api_stats():
    return jsonify(db.get_stats())


@app.route("/api/accounts", methods=["GET"])
def api_list_accounts():
    group = request.args.get("group")
    return jsonify(db.list_accounts(group))


@app.route("/api/accounts", methods=["POST"])
def api_create_account():
    data = request.get_json(force=True)
    username = (data.get("username") or "").strip().lstrip("@")
    if not username:
        return jsonify({"error": "Username required"}), 400
    if db.get_account_by_username(username):
        return jsonify({"error": "Account already exists"}), 409
    password = data.get("password", "")
    account = db.create_account(
        {
            "username": username,
            "password_enc": encrypt(password) if password else "",
            "proxy": data.get("proxy", ""),
            "group_name": data.get("group_name", "default"),
            "notes": data.get("notes", ""),
        }
    )
    if password:
        try:
            account = ig.login_account(account["id"], password)
        except AccountLoginError as exc:
            return jsonify({"account": account, "warning": str(exc), "needs_2fa": exc.needs_2fa}), 201
    return jsonify(account), 201


@app.route("/api/accounts/<int:account_id>", methods=["GET"])
def api_get_account(account_id):
    account = db.get_account(account_id)
    if not account:
        return jsonify({"error": "Not found"}), 404
    return jsonify(account)


@app.route("/api/accounts/<int:account_id>", methods=["PUT"])
def api_update_account(account_id):
    if not db.get_account(account_id):
        return jsonify({"error": "Not found"}), 404
    data = request.get_json(force=True)
    update = {}
    for field in ("proxy", "group_name", "notes"):
        if field in data:
            update[field] = data[field]
    if "password" in data and data["password"]:
        update["password_enc"] = encrypt(data["password"])
    account = db.update_account(account_id, update)
    return jsonify(account)


@app.route("/api/accounts/<int:account_id>", methods=["DELETE"])
def api_delete_account(account_id):
    ig.logout_account(account_id)
    if not db.delete_account(account_id):
        return jsonify({"error": "Not found"}), 404
    return jsonify({"ok": True})


@app.route("/api/accounts/<int:account_id>/login", methods=["POST"])
def api_login(account_id):
    data = request.get_json(force=True) if request.is_json else {}
    try:
        account = ig.login_account(
            account_id,
            password=data.get("password"),
            verification_code=data.get("verification_code"),
        )
        return jsonify(account)
    except AccountLoginError as exc:
        return jsonify({"error": str(exc), "needs_2fa": exc.needs_2fa}), 400


@app.route("/api/accounts/<int:account_id>/logout", methods=["POST"])
def api_logout(account_id):
    ig.logout_account(account_id)
    return jsonify({"ok": True})


@app.route("/api/accounts/<int:account_id>/refresh", methods=["POST"])
def api_refresh(account_id):
    try:
        account = ig.refresh_account(account_id)
        return jsonify(account)
    except AccountLoginError as exc:
        return jsonify({"error": str(exc)}), 400


@app.route("/api/accounts/bulk/login", methods=["POST"])
def api_bulk_login():
    data = request.get_json(force=True)
    ids = data.get("account_ids", [])
    return jsonify(ig.bulk_login(ids))


@app.route("/api/accounts/bulk/refresh", methods=["POST"])
def api_bulk_refresh():
    data = request.get_json(force=True)
    ids = data.get("account_ids", [])
    return jsonify(ig.bulk_refresh(ids))


@app.route("/api/accounts/<int:account_id>/import-session", methods=["POST"])
def api_import_session(account_id):
    data = request.get_json(force=True)
    settings = data.get("session_settings")
    if not settings:
        return jsonify({"error": "session_settings required"}), 400
    try:
        account = ig.import_session(account_id, settings)
        return jsonify(account)
    except AccountLoginError as exc:
        return jsonify({"error": str(exc)}), 400


@app.route("/api/accounts/<int:account_id>/post", methods=["POST"])
def api_post(account_id):
    if "media" not in request.files:
        return jsonify({"error": "Media file required"}), 400
    file = request.files["media"]
    if not file.filename or not allowed_file(file.filename):
        return jsonify({"error": "Invalid file type"}), 400
    ext = os.path.splitext(file.filename)[1]
    filename = f"{uuid.uuid4().hex}{ext}"
    path = os.path.join(UPLOAD_DIR, filename)
    file.save(path)
    caption = request.form.get("caption", "")
    try:
        result = ig.post_photo(account_id, path, caption)
        return jsonify(result)
    except AccountLoginError as exc:
        return jsonify({"error": str(exc)}), 400
    finally:
        if os.path.exists(path):
            os.remove(path)


@app.route("/api/groups")
def api_groups():
    return jsonify(db.list_groups())


@app.route("/api/activity")
def api_activity():
    limit = int(request.args.get("limit", 50))
    return jsonify(db.list_activity(limit))


@app.route("/api/scheduled-posts", methods=["GET"])
def api_scheduled_posts():
    status = request.args.get("status")
    return jsonify(db.list_scheduled_posts(status))


@app.route("/api/scheduled-posts", methods=["POST"])
def api_create_scheduled():
    if "media" not in request.files:
        return jsonify({"error": "Media file required"}), 400
    file = request.files["media"]
    account_id = request.form.get("account_id")
    scheduled_at = request.form.get("scheduled_at")
    if not account_id or not scheduled_at:
        return jsonify({"error": "account_id and scheduled_at required"}), 400
    if not file.filename or not allowed_file(file.filename):
        return jsonify({"error": "Invalid file"}), 400
    ext = os.path.splitext(file.filename)[1]
    filename = f"{uuid.uuid4().hex}{ext}"
    media_dir = os.path.join(UPLOAD_DIR, "scheduled")
    os.makedirs(media_dir, exist_ok=True)
    media_path = os.path.join(media_dir, filename)
    file.save(media_path)
    post = db.create_scheduled_post(
        {
            "account_id": int(account_id),
            "caption": request.form.get("caption", ""),
            "media_path": media_path,
            "scheduled_at": scheduled_at,
        }
    )
    db.log_activity(int(account_id), "schedule_post", f"Scheduled for {scheduled_at}")
    return jsonify(post), 201


@app.route("/api/export")
def api_export():
    return jsonify({"data": db.export_accounts()})


@app.route("/uploads/<path:filename>")
def serve_upload(filename):
    return send_from_directory(UPLOAD_DIR, filename)


if __name__ == "__main__":
    db.init_db()
    port = int(os.getenv("FLASK_PORT", 5050))
    debug = os.getenv("FLASK_DEBUG", "false").lower() == "true"
    app.run(host="0.0.0.0", port=port, debug=debug)
