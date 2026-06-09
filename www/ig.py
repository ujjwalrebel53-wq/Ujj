#!/usr/bin/env python3
import json, os, random, re, sys, time, requests
from instagrapi import Client
from instagrapi.exceptions import ChallengeRequired, ClientError, LoginRequired, PleaseWaitFewMinutes, TwoFactorRequired

LOG = os.path.join(os.path.dirname(__file__), "data", "logs", "live.log")
CODE = re.compile(r"\b(\d{6})\b")

def plog(lvl, msg, **ctx):
    os.makedirs(os.path.dirname(LOG), exist_ok=True)
    with open(LOG, "a", encoding="utf-8") as f:
        f.write(json.dumps({"time": time.strftime("%Y-%m-%d %H:%M:%S", time.gmtime()), "level": lvl, "message": msg, "context": ctx}, ensure_ascii=False) + "\n")

def limited(e):
    s = str(e).lower()
    return "429" in s or "too many" in s or "rate limit" in s or "please wait" in s

class TempMail:
    def __init__(self):
        self.provider = "mailtm"
        self.token = self.login = self.domain = self.password = ""
        self.address = self._create()

    def _create(self):
        for fn in (self._mailtm, self._1sec):
            try: return fn()
            except Exception: continue
        raise RuntimeError("Temp email fail")

    def _mailtm(self):
        r = requests.get("https://api.mail.tm/domains", timeout=20).json()
        items = r.get("hydra:member") or r
        dom = random.choice([d["domain"] for d in items if d.get("isActive")])
        self.login = "ig" + os.urandom(4).hex()
        self.domain = dom
        self.address = f"{self.login}@{dom}"
        self.password = os.urandom(6).hex()
        requests.post("https://api.mail.tm/accounts", json={"address": self.address, "password": self.password}, timeout=20).raise_for_status()
        self.token = requests.post("https://api.mail.tm/token", json={"address": self.address, "password": self.password}, timeout=20).json().get("token", "")
        self.provider = "mailtm"
        return self.address

    def _1sec(self):
        self.address = requests.get("https://www.1secmail.com/api/v1/?action=genRandomMailbox&count=1", headers={"User-Agent": "Mozilla/5.0"}, timeout=20).json()[0]
        self.login, self.domain = self.address.split("@", 1)
        self.provider = "1secmail"
        return self.address

    def _msgs(self):
        if self.provider == "mailtm":
            r = requests.get("https://api.mail.tm/messages", headers={"Authorization": f"Bearer {self.token}"}, timeout=20).json()
            return r.get("hydra:member") or r
        return requests.get(f"https://www.1secmail.com/api/v1/?action=getMessages&login={self.login}&domain={self.domain}", headers={"User-Agent": "Mozilla/5.0"}, timeout=20).json()

    def _body(self, m):
        if self.provider == "mailtm":
            full = requests.get(f"https://api.mail.tm/messages/{m['id']}", headers={"Authorization": f"Bearer {self.token}"}, timeout=20).json()
            return f"{m.get('subject','')} {m.get('intro','')} {full.get('text','')} {full.get('html','')}"
        full = requests.get(f"https://www.1secmail.com/api/v1/?action=readMessage&login={self.login}&domain={self.domain}&id={m['id']}", headers={"User-Agent": "Mozilla/5.0"}, timeout=20).json()
        return f"{full.get('subject','')} {full.get('textBody','')} {full.get('htmlBody','')}"

    def wait(self, timeout=120):
        seen = set()
        end = time.time() + timeout
        while time.time() < end:
            for m in self._msgs():
                mid = str(m.get("id", ""))
                if not mid or mid in seen: continue
                seen.add(mid)
                hit = CODE.search(self._body(m))
                if hit: return hit.group(1)
            time.sleep(5)
        raise RuntimeError("OTP timeout")

def client(proxy=""):
    c = Client()
    c.delay_range = [10, 25]
    c.request_timeout = 30
    proxy and c.set_proxy(proxy)
    c.set_uuids({})
    c.device_id = c.android_device_id
    return c

def signup(p):
    proxies = p.get("proxy_list") or []
    if p.get("proxy") and p["proxy"] not in proxies: proxies.insert(0, p["proxy"])
    if not proxies: proxies = [p.get("proxy") or ""]
    last = None
    for i, proxy in enumerate(proxies[:5]):
        if i: time.sleep(45 * i + random.randint(15, 30))
        try: return _signup(p, proxy)
        except Exception as e:
            last = e
            if limited(e): continue
            raise
    raise RuntimeError(f"429 rate limit — 30-60 min wait. Last: {last}")

def _signup(p, proxy):
    time.sleep(random.randint(20, 50))
    cl = client(proxy)
    mail = None
    email = (p.get("email") or "").strip()
    if not email:
        mail = TempMail()
        email = mail.address
        plog("INFO", f"Temp email: {email}")
    preset = (p.get("verification_code") or "").strip()
    cl.challenge_code_handler = lambda u, c: preset or (mail.wait(120) if mail else "")
    u = cl.signup(username=p["username"], password=p["password"], email=email, phone_number="", full_name=p.get("full_name", ""), year=p.get("year"), month=p.get("month"), day=p.get("day"))
    return {"username": u.username or p["username"], "full_name": p.get("full_name", ""), "email": email}

def login(p):
    cl = client(p.get("proxy", ""))
    sp = p.get("session_path", "")
    if sp and os.path.exists(sp):
        cl.load_settings(sp)
        try: cl.login(p["username"], p.get("password") or "")
        except Exception: pass
    if not cl.user_id:
        kw = {"verification_code": p["verification_code"]} if p.get("verification_code") else {}
        cl.login(p["username"], p.get("password", ""), **kw)
    sp and cl.dump_settings(sp)
    i = cl.account_info()
    return {"username": i.username, "full_name": i.full_name or "", "followers": i.follower_count, "following": i.following_count, "posts_count": i.media_count, "profile_pic": str(i.profile_pic_url) if i.profile_pic_url else "", "is_verified": bool(i.is_verified), "session_settings": cl.get_settings()}

def post_photo(p):
    cl = client(p.get("proxy", ""))
    sp = p.get("session_path", "")
    if sp and os.path.exists(sp):
        cl.load_settings(sp)
        cl.login(p["username"], "")
    m = cl.photo_upload(p["image_path"], p.get("caption", ""))
    return {"media_id": str(m.pk), "code": m.code}

def main():
    x = json.load(sys.stdin)
    act, p = x.get("action"), x.get("params", {})
    try:
        if act == "login": out = login(p)
        elif act == "signup": out = signup(p)
        elif act == "post_photo": out = post_photo(p)
        else: raise ValueError(act)
        print(json.dumps({"success": True, "data": out}))
    except TwoFactorRequired as e:
        print(json.dumps({"success": False, "error": str(e), "needs_2fa": True}))
    except ChallengeRequired as e:
        print(json.dumps({"success": False, "error": str(e), "needs_code": True}))
    except PleaseWaitFewMinutes as e:
        print(json.dumps({"success": False, "error": str(e), "rate_limited": True}))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e), "rate_limited": limited(e), "needs_code": "code" in str(e).lower() or "verification" in str(e).lower()}))

if __name__ == "__main__":
    main()
