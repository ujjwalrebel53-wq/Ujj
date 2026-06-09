const API = "";
let accounts = [];
let selectedIds = new Set();
let currentPage = "dashboard";
let creatorPollTimer = null;
let logsEventSource = null;

document.addEventListener("DOMContentLoaded", () => {
  initNav();
  loadDashboard();
  loadAccounts();
  loadActivity();
});

function initNav() {
  document.querySelectorAll(".nav-item").forEach((btn) => {
    btn.addEventListener("click", () => {
      const page = btn.dataset.page;
      switchPage(page);
    });
  });
}

function switchPage(page) {
  currentPage = page;
  document.querySelectorAll(".nav-item").forEach((b) => b.classList.toggle("active", b.dataset.page === page));
  document.querySelectorAll(".page").forEach((p) => p.classList.toggle("active", p.id === `page-${page}`));
  if (page === "accounts") loadAccounts();
  if (page === "activity") loadActivity();
  if (page === "dashboard") loadDashboard();
  if (page === "creator") {
    loadCreatorJobs();
    loadProxyStatus();
    startCreatorPolling();
    stopLogsStream();
  } else if (page === "logs") {
    loadLogs();
    startLogsStream();
    stopCreatorPolling();
  } else {
    stopCreatorPolling();
    stopLogsStream();
  }
}

async function api(path, options = {}) {
  const res = await fetch(`${API}${path}`, {
    headers: { "Content-Type": "application/json", ...options.headers },
    ...options,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `Request failed (${res.status})`);
  return data;
}

function toast(msg, type = "success") {
  const container = document.getElementById("toasts");
  const el = document.createElement("div");
  el.className = `toast ${type}`;
  el.textContent = msg;
  container.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

async function loadDashboard() {
  try {
    const stats = await api("/api/stats");
    document.getElementById("stat-total").textContent = stats.total_accounts;
    document.getElementById("stat-active").textContent = stats.active_accounts;
    document.getElementById("stat-error").textContent = stats.error_accounts;
    document.getElementById("stat-pending").textContent = stats.pending_posts;
    document.getElementById("stat-created").textContent = stats.accounts_created || 0;
    document.getElementById("stat-creating").textContent = stats.creation_in_progress || 0;
  } catch (e) {
    toast(e.message, "error");
  }
}

async function loadAccounts() {
  const grid = document.getElementById("accounts-grid");
  grid.innerHTML = '<div class="loading" style="margin:40px auto"></div>';
  try {
    const group = document.getElementById("filter-group")?.value || "";
    accounts = await api(group ? `/api/accounts?group=${encodeURIComponent(group)}` : "/api/accounts");
    renderAccounts();
    updateGroupFilter();
  } catch (e) {
    grid.innerHTML = `<div class="empty-state"><p>${e.message}</p></div>`;
  }
}

function updateGroupFilter() {
  const select = document.getElementById("filter-group");
  if (!select) return;
  const groups = [...new Set(accounts.map((a) => a.group_name))];
  const current = select.value;
  select.innerHTML = '<option value="">All Groups</option>' + groups.map((g) => `<option value="${g}">${g}</option>`).join("");
  select.value = current;
}

function renderAccounts() {
  const grid = document.getElementById("accounts-grid");
  const search = (document.getElementById("search-accounts")?.value || "").toLowerCase();
  const filtered = accounts.filter(
    (a) =>
      a.username.toLowerCase().includes(search) ||
      (a.full_name || "").toLowerCase().includes(search) ||
      (a.notes || "").toLowerCase().includes(search)
  );

  if (!filtered.length) {
    grid.innerHTML = `
      <div class="empty-state" style="grid-column:1/-1">
        <div class="icon">📸</div>
        <h3>No accounts yet</h3>
        <p>Add your first Instagram account to get started</p>
        <button class="btn btn-primary" style="margin-top:16px" onclick="openAddModal()">+ Add Account</button>
      </div>`;
    return;
  }

  grid.innerHTML = filtered
    .map(
      (a) => `
    <div class="account-card ${selectedIds.has(a.id) ? "selected" : ""}" data-id="${a.id}">
      <span class="status-badge status-${a.status}">${a.status}</span>
      <div class="account-header">
        <div class="checkbox-wrap" style="margin-right:4px">
          <input type="checkbox" ${selectedIds.has(a.id) ? "checked" : ""} onchange="toggleSelect(${a.id}, this.checked)" />
        </div>
        ${
          a.profile_pic
            ? `<img class="account-avatar" src="${a.profile_pic}" alt="" onerror="this.style.display='none'">`
            : `<div class="account-avatar placeholder">📷</div>`
        }
        <div class="account-info">
          <h3>${esc(a.full_name || a.username)}</h3>
          <div class="username">@${esc(a.username)}</div>
        </div>
      </div>
      <div class="account-stats">
        <div class="account-stat"><div class="num">${fmt(a.followers)}</div><div class="lbl">Followers</div></div>
        <div class="account-stat"><div class="num">${fmt(a.following)}</div><div class="lbl">Following</div></div>
        <div class="account-stat"><div class="num">${fmt(a.posts_count)}</div><div class="lbl">Posts</div></div>
      </div>
      <div class="account-meta">
        <span class="tag">📁 ${esc(a.group_name)}</span>
        ${a.proxy ? '<span class="tag">🌐 Proxy</span>' : ""}
        ${a.is_verified ? '<span class="tag">✓ Verified</span>' : ""}
      </div>
      ${a.last_error ? `<p style="color:var(--error);font-size:0.8rem;margin-bottom:10px">${esc(a.last_error)}</p>` : ""}
      <div class="account-actions">
        <button class="btn btn-sm btn-primary" onclick="loginAccount(${a.id})">Login</button>
        <button class="btn btn-sm btn-secondary" onclick="refreshAccount(${a.id})">Refresh</button>
        <button class="btn btn-sm btn-secondary" onclick="openPostModal(${a.id})">Post</button>
        <button class="btn btn-sm btn-secondary" onclick="openEditModal(${a.id})">Edit</button>
        <button class="btn btn-sm btn-danger" onclick="deleteAccount(${a.id})">Delete</button>
      </div>
    </div>`
    )
    .join("");
}

function esc(s) {
  const d = document.createElement("div");
  d.textContent = s;
  return d.innerHTML;
}

function fmt(n) {
  if (n >= 1e6) return (n / 1e6).toFixed(1) + "M";
  if (n >= 1e3) return (n / 1e3).toFixed(1) + "K";
  return n || 0;
}

function toggleSelect(id, checked) {
  if (checked) selectedIds.add(id);
  else selectedIds.delete(id);
  document.querySelector(`.account-card[data-id="${id}"]`)?.classList.toggle("selected", checked);
}

function selectAll() {
  accounts.forEach((a) => selectedIds.add(a.id));
  renderAccounts();
}

function deselectAll() {
  selectedIds.clear();
  renderAccounts();
}

async function bulkLogin() {
  if (!selectedIds.size) return toast("Select accounts first", "error");
  toast("Logging in selected accounts...");
  try {
    const results = await api("/api/accounts/bulk/login", {
      method: "POST",
      body: JSON.stringify({ account_ids: [...selectedIds] }),
    });
    const ok = results.filter((r) => r.success).length;
    toast(`${ok}/${results.length} accounts logged in`);
    loadAccounts();
    loadDashboard();
  } catch (e) {
    toast(e.message, "error");
  }
}

async function bulkRefresh() {
  if (!selectedIds.size) return toast("Select accounts first", "error");
  toast("Refreshing selected accounts...");
  try {
    const results = await api("/api/accounts/bulk/refresh", {
      method: "POST",
      body: JSON.stringify({ account_ids: [...selectedIds] }),
    });
    const ok = results.filter((r) => r.success).length;
    toast(`${ok}/${results.length} accounts refreshed`);
    loadAccounts();
  } catch (e) {
    toast(e.message, "error");
  }
}

function openModal(id) {
  document.getElementById(id).classList.add("open");
}

function closeModal(id) {
  document.getElementById(id).classList.remove("open");
}

function openAddModal() {
  document.getElementById("add-form").reset();
  openModal("add-modal");
}

function openEditModal(id) {
  const a = accounts.find((x) => x.id === id);
  if (!a) return;
  document.getElementById("edit-id").value = id;
  document.getElementById("edit-username").value = a.username;
  document.getElementById("edit-proxy").value = a.proxy || "";
  document.getElementById("edit-group").value = a.group_name || "default";
  document.getElementById("edit-notes").value = a.notes || "";
  document.getElementById("edit-password").value = "";
  openModal("edit-modal");
}

function openPostModal(id) {
  document.getElementById("post-account-id").value = id;
  document.getElementById("post-form").reset();
  openModal("post-modal");
}

async function submitAdd(e) {
  e.preventDefault();
  const form = e.target;
  const data = {
    username: form.username.value,
    password: form.password.value,
    proxy: form.proxy.value,
    use_webshare: true,
    group_name: form.group_name.value || "default",
    notes: form.notes.value,
  };
  try {
    const res = await fetch("/api/accounts", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data),
    });
    const result = await res.json();
    if (!res.ok) throw new Error(result.error);
    if (result.warning) toast(result.warning, "error");
    else toast(`@${data.username} added successfully`);
    closeModal("add-modal");
    loadAccounts();
    loadDashboard();
  } catch (e) {
    toast(e.message, "error");
  }
}

async function submitEdit(e) {
  e.preventDefault();
  const id = document.getElementById("edit-id").value;
  const data = {
    proxy: document.getElementById("edit-proxy").value,
    group_name: document.getElementById("edit-group").value,
    notes: document.getElementById("edit-notes").value,
  };
  const pwd = document.getElementById("edit-password").value;
  if (pwd) data.password = pwd;
  try {
    await api(`/api/accounts/${id}`, { method: "PUT", body: JSON.stringify(data) });
    toast("Account updated");
    closeModal("edit-modal");
    loadAccounts();
  } catch (e) {
    toast(e.message, "error");
  }
}

async function loginAccount(id, code) {
  try {
    const body = code ? { verification_code: code } : {};
    await api(`/api/accounts/${id}/login`, { method: "POST", body: JSON.stringify(body) });
    toast("Login successful");
    loadAccounts();
    loadDashboard();
  } catch (e) {
    if (e.message.includes("2FA")) {
      const c = prompt("Enter 2FA verification code:");
      if (c) loginAccount(id, c);
    } else {
      toast(e.message, "error");
    }
  }
}

async function refreshAccount(id) {
  try {
    await api(`/api/accounts/${id}/refresh`, { method: "POST" });
    toast("Profile refreshed");
    loadAccounts();
  } catch (e) {
    toast(e.message, "error");
  }
}

async function deleteAccount(id) {
  if (!confirm("Delete this account?")) return;
  try {
    await api(`/api/accounts/${id}`, { method: "DELETE" });
    selectedIds.delete(id);
    toast("Account deleted");
    loadAccounts();
    loadDashboard();
  } catch (e) {
    toast(e.message, "error");
  }
}

async function submitPost(e) {
  e.preventDefault();
  const form = e.target;
  const fd = new FormData(form);
  try {
    const res = await fetch("/api/accounts/" + fd.get("account_id") + "/post", {
      method: "POST",
      body: fd,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error);
    toast("Posted successfully!");
    closeModal("post-modal");
    loadAccounts();
  } catch (e) {
    toast(e.message, "error");
  }
}

async function loadActivity() {
  const list = document.getElementById("activity-list");
  if (!list) return;
  try {
    const items = await api("/api/activity?limit=50");
    if (!items.length) {
      list.innerHTML = '<div class="empty-state"><p>No activity yet</p></div>';
      return;
    }
    const icons = {
      account_created: "➕",
      login: "🔑",
      logout: "🚪",
      post_photo: "📸",
      schedule_post: "📅",
      account_deleted: "🗑️",
      auto_create: "🤖",
    };
    list.innerHTML = items
      .map(
        (a) => `
      <div class="activity-item">
        <div class="activity-icon">${icons[a.action] || "📌"}</div>
        <div class="activity-details">
          <div class="action">${esc(a.action.replace(/_/g, " "))}${a.username ? ` — @${esc(a.username)}` : ""}</div>
          <div class="meta">${esc(a.details || "")} · ${new Date(a.created_at).toLocaleString()}</div>
        </div>
        <span class="status-badge status-${a.status === "success" ? "active" : "error"}">${a.status}</span>
      </div>`
      )
      .join("");
  } catch (e) {
    list.innerHTML = `<div class="empty-state"><p>${e.message}</p></div>`;
  }
}

async function exportData() {
  try {
    const res = await api("/api/export");
    const blob = new Blob([res.data], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "instagram-accounts-export.json";
    a.click();
    URL.revokeObjectURL(url);
    toast("Export downloaded");
  } catch (e) {
    toast(e.message, "error");
  }
}

function startCreatorPolling() {
  stopCreatorPolling();
  creatorPollTimer = setInterval(() => {
    if (currentPage === "creator") loadCreatorJobs(true);
  }, 5000);
}

function stopCreatorPolling() {
  if (creatorPollTimer) {
    clearInterval(creatorPollTimer);
    creatorPollTimer = null;
  }
}

async function loadProxyStatus() {
  const el = document.getElementById("proxy-status");
  if (!el) return;
  try {
    const stats = await api("/api/proxies/stats");
    if (!stats.enabled) {
      el.innerHTML = '<span class="proxy-info">Webshare proxy URL set nahi hai (.env mein WEBSHARE_PROXY_URL)</span>';
      return;
    }
    el.innerHTML = `
      <span class="proxy-info">Webshare Proxy Pool: <span class="proxy-count">${stats.total_proxies}</span> proxies loaded</span>
      <button class="btn btn-secondary btn-sm" onclick="refreshProxies()">Refresh Proxies</button>`;
  } catch (e) {
    el.innerHTML = `<span class="proxy-info" style="color:var(--error)">${esc(e.message)}</span>`;
  }
}

async function refreshProxies() {
  try {
    const res = await api("/api/proxies/refresh", { method: "POST" });
    toast(`${res.total_proxies} proxies refreshed from Webshare`);
    loadProxyStatus();
  } catch (e) {
    toast(e.message, "error");
  }
}

function formUseWebshare(form) {
  const cb = form.querySelector('[name="use_webshare"]');
  return cb ? cb.checked : true;
}

async function previewProfiles() {
  const form = document.getElementById("creator-single-form");
  const prefix = form.username_prefix.value;
  try {
    const profiles = await api(`/api/creator/preview?count=5&prefix=${encodeURIComponent(prefix)}`);
    const box = document.getElementById("preview-box");
    const list = document.getElementById("preview-list");
    box.style.display = "block";
    list.innerHTML = profiles
      .map(
        (p) => `
      <div class="preview-item">
        <strong>@${esc(p.username)}</strong>
        <span>${esc(p.full_name)}</span>
        <code>${esc(p.password)}</code>
      </div>`
      )
      .join("");
  } catch (e) {
    toast(e.message, "error");
  }
}

async function submitCreatorSingle(e) {
  e.preventDefault();
  const form = e.target;
  const data = {
    username_prefix: form.username_prefix.value,
    group_name: form.group_name.value || "auto-created",
    proxy: form.proxy.value,
    use_webshare: formUseWebshare(form),
    email: form.email.value,
    username: form.username.value,
    password: form.password.value,
  };
  toast("Creating account... this may take 1-2 minutes");
  try {
    const res = await fetch("/api/creator/create", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data),
    });
    const result = await res.json();
    if (result.success) {
      showCredentials(result);
      toast("Account created successfully!");
      loadCreatorJobs();
      loadDashboard();
    } else if (result.needs_code) {
      toast("OTP auto-fetch fail — dubara try karo ya thodi der baad", "error");
    } else {
      toast(result.error || "Creation failed", "error");
    }
    loadCreatorJobs();
  } catch (e) {
    toast(e.message, "error");
  }
}

async function submitCreatorBatch(e) {
  e.preventDefault();
  const form = e.target;
  const data = {
    count: parseInt(form.count.value, 10),
    delay_seconds: parseInt(form.delay_seconds.value, 10),
    username_prefix: form.username_prefix.value,
    group_name: form.group_name.value || "auto-created",
    proxy: form.proxy.value,
    use_webshare: formUseWebshare(form),
  };
  if (!confirm(`Start creating ${data.count} accounts?`)) return;
  try {
    const result = await api("/api/creator/batch", { method: "POST", body: JSON.stringify(data) });
    toast(`Batch started — ${result.count} accounts queued`);
    loadCreatorJobs();
    loadDashboard();
  } catch (e) {
    toast(e.message, "error");
  }
}

async function loadCreatorJobs(silent = false) {
  const container = document.getElementById("creator-jobs");
  if (!container) return;
  if (!silent) container.innerHTML = '<div class="loading" style="margin:20px auto"></div>';
  try {
    const jobs = await api("/api/creator/jobs?limit=30");
    if (!jobs.length) {
      container.innerHTML = '<div class="empty-state"><p>No creation jobs yet</p></div>';
      return;
    }
    const statusClass = {
      success: "active",
      failed: "error",
      pending: "inactive",
      creating: "inactive",
      waiting_code: "error",
    };
    container.innerHTML = jobs
      .map((j) => {
        const actions =
          j.status === "waiting_code"
            ? `<button class="btn btn-sm btn-primary" onclick="openVerifyModal(${j.id})">Enter Code</button>`
            : j.status === "failed"
              ? `<button class="btn btn-sm btn-secondary" onclick="retryJob(${j.id})">Retry</button>`
              : j.status === "success"
                ? `<button class="btn btn-sm btn-secondary" onclick="showJobCredentials(${j.id})">Show Creds</button>`
                : "";
        return `
      <div class="job-card">
        <div class="job-header">
          <strong>@${esc(j.username)}</strong>
          <span class="status-badge status-${statusClass[j.status] || "inactive"}">${j.status}</span>
        </div>
        <div class="job-meta">
          <span>${esc(j.full_name || "")}</span>
          ${j.email ? `<span>📧 ${esc(j.email)}</span>` : ""}
          <span>📁 ${esc(j.group_name)}</span>
          <span>${new Date(j.created_at).toLocaleString()}</span>
        </div>
        ${j.error ? `<p class="job-error">${esc(j.error)}</p>` : ""}
        <div class="account-actions">${actions}</div>
      </div>`;
      })
      .join("");
  } catch (e) {
    if (!silent) container.innerHTML = `<div class="empty-state"><p>${e.message}</p></div>`;
  }
}

function openVerifyModal(jobId) {
  document.getElementById("verify-job-id").value = jobId;
  document.getElementById("verify-code").value = "";
  openModal("verify-modal");
}

async function submitVerifyCode(e) {
  e.preventDefault();
  const jobId = document.getElementById("verify-job-id").value;
  const code = document.getElementById("verify-code").value;
  try {
    const result = await api(`/api/creator/jobs/${jobId}/verify`, {
      method: "POST",
      body: JSON.stringify({ code }),
    });
    closeModal("verify-modal");
    if (result.success) {
      showCredentials(result);
      toast("Account created!");
    } else {
      toast(result.error || "Verification failed", "error");
    }
    loadCreatorJobs();
    loadDashboard();
  } catch (e) {
    toast(e.message, "error");
  }
}

async function retryJob(jobId) {
  toast("Retrying...");
  try {
    const result = await api(`/api/creator/jobs/${jobId}/retry`, { method: "POST" });
    if (result.success) {
      showCredentials(result);
      toast("Account created!");
    } else if (result.needs_code) {
      openVerifyModal(jobId);
    } else {
      toast(result.error || "Retry failed", "error");
    }
    loadCreatorJobs();
  } catch (e) {
    toast(e.message, "error");
  }
}

async function showJobCredentials(jobId) {
  try {
    const job = await api(`/api/creator/jobs/${jobId}`);
    showCredentials(job);
  } catch (e) {
    toast(e.message, "error");
  }
}

function showCredentials(data) {
  const body = document.getElementById("creds-body");
  body.innerHTML = `
    <div class="creds-box">
      <div class="cred-row"><label>Username</label><code>@${esc(data.username)}</code></div>
      <div class="cred-row"><label>Password</label><code>${esc(data.password)}</code></div>
      <div class="cred-row"><label>Email</label><code>${esc(data.email || "N/A")}</code></div>
      <div class="cred-row"><label>Full Name</label><code>${esc(data.full_name || "")}</code></div>
    </div>
    <p style="color:var(--warning);font-size:0.85rem;margin-top:12px">⚠️ Credentials save kar lo — baad mein dubara nahi dikhenge!</p>`;
  openModal("creds-modal");
}

function renderLogEntry(entry) {
  const level = (entry.level || "INFO").toLowerCase();
  const ctx = entry.context && Object.keys(entry.context).length
    ? " " + JSON.stringify(entry.context)
    : "";
  return `<div class="log-line log-${level}">
    <span class="log-time">${esc(entry.time || "")}</span>
    <span class="log-level">[${esc(entry.level || "")}]</span>
    <span class="log-msg">${esc(entry.message || "")}${esc(ctx)}</span>
  </div>`;
}

async function loadLogs() {
  const box = document.getElementById("live-logs");
  if (!box) return;
  try {
    const logs = await api("/api/logs?limit=300");
    box.innerHTML = logs.length ? logs.map(renderLogEntry).join("") : '<div class="log-line log-info">No logs yet — Account Creator try karo</div>';
    box.scrollTop = box.scrollHeight;
  } catch (e) {
    box.innerHTML = `<div class="log-line log-error">${esc(e.message)}</div>`;
  }
}

function startLogsStream() {
  stopLogsStream();
  if (!window.EventSource) return;
  logsEventSource = new EventSource("/api/logs/stream");
  logsEventSource.onmessage = (e) => {
    try {
      const entry = JSON.parse(e.data);
      const box = document.getElementById("live-logs");
      if (!box || currentPage !== "logs") return;
      box.insertAdjacentHTML("beforeend", renderLogEntry(entry));
      if (box.children.length > 500) box.removeChild(box.firstChild);
      box.scrollTop = box.scrollHeight;
    } catch (_) {}
  };
  logsEventSource.onerror = () => {
    document.getElementById("logs-status").textContent = "● RECONNECTING";
    setTimeout(() => { if (currentPage === "logs") startLogsStream(); }, 3000);
  };
}

function stopLogsStream() {
  if (logsEventSource) {
    logsEventSource.close();
    logsEventSource = null;
  }
}

async function clearLogs() {
  if (!confirm("Saare logs clear karein?")) return;
  await api("/api/logs/clear", { method: "POST" });
  document.getElementById("live-logs").innerHTML = "";
  toast("Logs cleared");
}

document.getElementById("search-accounts")?.addEventListener("input", renderAccounts);
document.getElementById("filter-group")?.addEventListener("change", loadAccounts);
