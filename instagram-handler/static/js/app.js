const API = "";
let accounts = [];
let selectedIds = new Set();
let currentPage = "dashboard";

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

document.getElementById("search-accounts")?.addEventListener("input", renderAccounts);
document.getElementById("filter-group")?.addEventListener("change", loadAccounts);
