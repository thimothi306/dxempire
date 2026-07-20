/* ============================================================================
   DXEmpire — Partner Web Portal (view-only)
   Talks to the same backend API. Partners log in with email/phone + password
   and see ONLY their own dashboard, orders, invoices and dues.
   ========================================================================== */

const CONFIG = {
  // 🔧 Point this at your backend API.
  //   Local (XAMPP): http://localhost/dxempire/dxempire-backend/public/api/v1
  //   Production:    https://api.dxempire.in/api/v1
  API_BASE: 'https://api.dxempire.in/api/v1',
};

const TOKEN_KEY = 'dx_partner_token';
const PARTNER_KEY = 'dx_partner_info';

/* ── tiny helpers ─────────────────────────────────────────────────────────── */
const $ = (sel) => document.querySelector(sel);
const token = () => localStorage.getItem(TOKEN_KEY);
const inr = (n) =>
  '₹' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
const fmtDate = (d) =>
  d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const STATUS_COLORS = {
  pending: '#b45309', approved: '#1d4ed8', picking: '#7c3aed', packing: '#7c3aed',
  packed: '#0891b2', dispatched: '#c2410c', delivered: '#15803d',
  cancelled: '#b91c1c', returned: '#b91c1c',
  unpaid: '#b91c1c', partial: '#b45309', paid: '#15803d', refunded: '#6b7280',
};

async function api(path, opts = {}) {
  const res = await fetch(CONFIG.API_BASE + path, {
    ...opts,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...(token() ? { Authorization: 'Bearer ' + token() } : {}),
      ...(opts.headers || {}),
    },
  });
  if (res.status === 401) { logout(); throw new Error('Session expired'); }
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(json.message || 'Something went wrong');
  return json;
}

/* ── auth ─────────────────────────────────────────────────────────────────── */
async function doLogin(e) {
  e.preventDefault();
  const btn = $('#loginBtn');
  const err = $('#loginError');
  err.textContent = '';
  btn.disabled = true; btn.textContent = 'Signing in…';
  try {
    const { data } = await api('/partner/auth/login', {
      method: 'POST',
      body: JSON.stringify({ login: $('#login').value.trim(), password: $('#password').value }),
    });
    localStorage.setItem(TOKEN_KEY, data.token);
    localStorage.setItem(PARTNER_KEY, JSON.stringify(data.partner));
    showApp();
  } catch (ex) {
    err.textContent = ex.message;
  } finally {
    btn.disabled = false; btn.textContent = 'Sign In';
  }
}

function logout() {
  const t = token();
  if (t) { api('/partner/auth/logout', { method: 'POST' }).catch(() => {}); }
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(PARTNER_KEY);
  $('#app').classList.add('hidden');
  $('#loginScreen').classList.remove('hidden');
}

/* ── view switching ───────────────────────────────────────────────────────── */
function showApp() {
  const p = JSON.parse(localStorage.getItem(PARTNER_KEY) || '{}');
  $('#partnerName').textContent = p.business_name || p.name || 'Partner';
  $('#partnerInitial').textContent = (p.business_name || p.name || 'P').charAt(0).toUpperCase();
  $('#loginScreen').classList.add('hidden');
  $('#app').classList.remove('hidden');
  navigate('dashboard');
}

function navigate(view) {
  document.querySelectorAll('.nav-item').forEach((el) =>
    el.classList.toggle('active', el.dataset.view === view));
  const map = { dashboard: renderDashboard, orders: renderOrders, invoices: renderInvoices, dues: renderDues };
  const titles = { dashboard: 'Dashboard', orders: 'My Orders', invoices: 'My Invoices', dues: 'My Dues' };
  $('#pageTitle').textContent = titles[view];
  $('#content').innerHTML = '<div class="loading">Loading…</div>';
  (map[view] || renderDashboard)();
}

/* ── views ────────────────────────────────────────────────────────────────── */
async function renderDashboard() {
  try {
    const { data: d } = await api('/partner/dashboard');
    const kyc = d.kyc_status === 'verified'
      ? '<span class="badge" style="background:#dcfce7;color:#15803d">KYC Verified</span>'
      : `<span class="badge" style="background:#fef9c3;color:#b45309">KYC ${d.kyc_status || 'pending'}</span>`;

    $('#content').innerHTML = `
      <div class="welcome">Welcome back, <strong>${d.business_name || 'Partner'}</strong> ${kyc}</div>
      <div class="stat-grid">
        ${statCard('Total Orders', d.total_orders, '#1d4ed8')}
        ${statCard('Active Orders', d.active_orders, '#c2410c')}
        ${statCard('Delivered', d.delivered_orders, '#15803d')}
        ${statCard('Lifetime Purchases', inr(d.lifetime_purchases), '#7c3aed')}
      </div>
      <div class="stat-grid">
        ${statCard('Credit Limit', inr(d.credit_limit), '#334155')}
        ${statCard('Credit Used', inr(d.credit_used), '#b91c1c')}
        ${statCard('Available Credit', inr(d.available_credit), '#15803d')}
      </div>
      <div class="card">
        <div class="card-head">Recent Orders</div>
        ${ordersTable(d.recent_orders || [])}
      </div>`;
  } catch (ex) { showError(ex); }
}

async function renderOrders() {
  try {
    const { data } = await api('/partner/orders?per_page=50');
    const rows = data.data || data;
    $('#content').innerHTML = `
      <div class="card">
        <div class="card-head">All Orders (${rows.length})</div>
        ${ordersTable(rows, true)}
      </div>`;
  } catch (ex) { showError(ex); }
}

async function renderInvoices() {
  try {
    const { data } = await api('/partner/invoices?per_page=50');
    const rows = data.data || data;
    const body = rows.length ? rows.map((i) => `
      <tr>
        <td><strong>${i.invoice_number}</strong></td>
        <td>${i.order?.order_number || '—'}</td>
        <td>${fmtDate(i.issued_at)}</td>
        <td>${inr(i.subtotal)}</td>
        <td>${inr(i.gst_amount)}</td>
        <td><strong>${inr(i.total)}</strong></td>
      </tr>`).join('') : emptyRow(6, 'No invoices yet');
    $('#content').innerHTML = `
      <div class="card">
        <div class="card-head">All Invoices (${rows.length})</div>
        <div class="table-wrap"><table>
          <thead><tr><th>Invoice #</th><th>Order</th><th>Date</th><th>Subtotal</th><th>GST</th><th>Total</th></tr></thead>
          <tbody>${body}</tbody>
        </table></div>
      </div>`;
  } catch (ex) { showError(ex); }
}

async function renderDues() {
  try {
    const { data: d } = await api('/partner/dues');
    const rows = d.unpaid_orders || [];
    const body = rows.length ? rows.map((o) => `
      <tr>
        <td><strong>${o.order_number}</strong></td>
        <td>${fmtDate(o.created_at)}</td>
        <td>${badge(o.status)}</td>
        <td>${badge(o.payment_status)}</td>
        <td><strong>${inr(o.total_amount)}</strong></td>
      </tr>`).join('') : emptyRow(5, 'No outstanding orders 🎉');

    $('#content').innerHTML = `
      <div class="stat-grid">
        ${statCard('Credit Limit', inr(d.credit_limit), '#334155')}
        ${statCard('Outstanding', inr(d.outstanding_amount), '#b91c1c')}
        ${statCard('Available Credit', inr(d.available_credit), '#15803d')}
      </div>
      <div class="card">
        <div class="card-head">Unpaid / Partial Orders</div>
        <div class="table-wrap"><table>
          <thead><tr><th>Order #</th><th>Date</th><th>Status</th><th>Payment</th><th>Amount</th></tr></thead>
          <tbody>${body}</tbody>
        </table></div>
      </div>
      <div class="note">💡 ${d.note}</div>`;
  } catch (ex) { showError(ex); }
}

/* ── render helpers ───────────────────────────────────────────────────────── */
function statCard(label, value, color) {
  return `<div class="stat"><div class="stat-label">${label}</div>
          <div class="stat-value" style="color:${color}">${value}</div></div>`;
}
function badge(s) {
  const c = STATUS_COLORS[s] || '#6b7280';
  return `<span class="badge" style="background:${c}1a;color:${c}">${(s || '').replace(/_/g, ' ')}</span>`;
}
function ordersTable(rows, showItems) {
  const body = rows.length ? rows.map((o) => `
    <tr>
      <td><strong>${o.order_number}</strong></td>
      <td>${fmtDate(o.created_at)}</td>
      ${showItems ? `<td>${o.items_count ?? '—'} item(s)</td>` : ''}
      <td>${badge(o.status)}</td>
      <td><strong>${inr(o.total_amount)}</strong></td>
    </tr>`).join('') : emptyRow(showItems ? 5 : 4, 'No orders yet');
  return `<div class="table-wrap"><table>
    <thead><tr><th>Order #</th><th>Date</th>${showItems ? '<th>Items</th>' : ''}<th>Status</th><th>Amount</th></tr></thead>
    <tbody>${body}</tbody></table></div>`;
}
function emptyRow(cols, msg) {
  return `<tr><td colspan="${cols}" class="empty">${msg}</td></tr>`;
}
function showError(ex) {
  $('#content').innerHTML = `<div class="error-box">⚠️ ${ex.message}</div>`;
}

/* ── boot ─────────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  $('#loginForm').addEventListener('submit', doLogin);
  $('#logoutBtn').addEventListener('click', logout);
  document.querySelectorAll('.nav-item').forEach((el) =>
    el.addEventListener('click', () => navigate(el.dataset.view)));

  if (token()) showApp();
  else { $('#loginScreen').classList.remove('hidden'); }
});
