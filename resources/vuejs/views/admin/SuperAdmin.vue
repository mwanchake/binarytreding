<template>
  <div class="content-wrapper admin-operations">
    <div class="content-body">
      <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
      <div v-if="notice" class="alert alert-success" role="status">{{ notice }}</div>

      <section v-if="can('dashboard.view')" class="card">
        <div class="card-header admin-operations__heading">
          <div>
            <h4 class="card-title mb-1">{{ $i18n.t('superadmin_title') }}</h4>
            <p class="text-muted mb-0">{{ $i18n.t('superadmin_subtitle') }}</p>
          </div>
          <div class="admin-operations__date-filter">
            <input v-model="from" type="date" class="form-control" :aria-label="$i18n.t('superadmin_from')">
            <input v-model="to" type="date" class="form-control" :aria-label="$i18n.t('superadmin_to')">
            <button class="btn btn-primary" type="button" @click="loadDashboard">{{ $i18n.t('superadmin_apply') }}</button>
          </div>
        </div>
        <div class="card-body">
          <div v-if="loadingDashboard" class="text-center py-3" role="status">
            <span class="spinner-border text-primary"></span>
          </div>
          <div v-else class="row">
            <div v-for="card in summaryCards" :key="card.key" class="col-6 col-lg-3 mb-1">
              <div class="admin-operations__summary">
                <small>{{ $i18n.t(card.label) }}</small>
                <strong>{{ card.currency ? '$' + formatAmount(dashboard[card.key]) : dashboard[card.key] }}</strong>
              </div>
            </div>
          </div>
          <div v-if="dashboard.alerts" class="admin-operations__alerts">
            <span class="badge badge-warning">{{ $i18n.t('superadmin_pending_deposits') }}: {{ dashboard.alerts.pending_deposits }}</span>
            <span class="badge badge-warning">{{ $i18n.t('superadmin_pending_withdrawals') }}: {{ dashboard.alerts.pending_withdrawals }}</span>
            <span class="badge badge-danger">{{ $i18n.t('superadmin_failed_withdrawals') }}: {{ dashboard.alerts.failed_withdrawals }}</span>
            <router-link v-if="can('finance.deposits.view')" class="btn btn-sm btn-outline-primary ml-auto" to="/admin/deposit">
              {{ $i18n.t('superadmin_deposit_queue') }}
            </router-link>
            <router-link v-if="can('finance.withdrawals.view')" class="btn btn-sm btn-outline-primary" to="/admin/withdrawal">
              {{ $i18n.t('superadmin_withdrawal_queue') }}
            </router-link>
          </div>
          <div v-if="dashboard.recent_audit && dashboard.recent_audit.length" class="table-responsive mt-2">
            <h5>{{ $i18n.t('superadmin_recent_activity') }}</h5>
            <table class="table table-sm">
              <thead><tr>
                <th>{{ $i18n.t('superadmin_action') }}</th>
                <th>{{ $i18n.t('superadmin_actor') }}</th>
                <th>{{ $i18n.t('superadmin_affected_user') }}</th>
                <th>{{ $i18n.t('superadmin_reason') }}</th>
                <th>{{ $i18n.t('superadmin_date') }}</th>
              </tr></thead>
              <tbody>
                <tr v-for="entry in dashboard.recent_audit" :key="entry.id">
                  <td>{{ entry.action }}</td>
                  <td>{{ entry.actor ? entry.actor.email : '—' }}</td>
                  <td>{{ entry.affected_user ? entry.affected_user.email : '—' }}</td>
                  <td>{{ entry.reason || '—' }}</td>
                  <td>{{ formatDate(entry.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="can('reports.export')" class="admin-operations__export">
            <select v-model="reportType" class="form-control">
              <option value="deposits">{{ $i18n.t('superadmin_deposit') }}</option>
              <option value="withdrawals">{{ $i18n.t('superadmin_withdrawal') }}</option>
              <option value="wallet_adjustments">{{ $i18n.t('superadmin_wallet_adjustment') }}</option>
            </select>
            <button type="button" class="btn btn-outline-primary" @click="exportReport">{{ $i18n.t('superadmin_export_csv') }}</button>
          </div>
        </div>
      </section>

      <nav class="admin-operations__tabs" :aria-label="$i18n.t('superadmin_sections')">
        <button v-if="can('users.view')" type="button" :class="{ active: tab === 'users' }" @click="selectTab('users')">{{ $i18n.t('superadmin_users') }}</button>
        <button v-if="isSuperAdmin" type="button" :class="{ active: tab === 'admins' }" @click="selectTab('admins')">{{ $i18n.t('superadmin_admins') }}</button>
        <button v-if="can('audit.view')" type="button" :class="{ active: tab === 'audit' }" @click="selectTab('audit')">{{ $i18n.t('superadmin_audit') }}</button>
      </nav>

      <section v-if="tab === 'users' && can('users.view')" class="card">
        <div class="card-header admin-operations__toolbar">
          <h4 class="card-title mb-0">{{ $i18n.t('superadmin_user_management') }}</h4>
          <button v-if="can('users.manage')" type="button" class="btn btn-primary" @click="showCreate = !showCreate">
            {{ $i18n.t('superadmin_create_user') }}
          </button>
        </div>
        <div class="card-body">
          <div v-if="showCreate" class="admin-operations__form mb-2">
            <h5>{{ $i18n.t('superadmin_create_user') }}</h5>
            <div class="row">
              <div class="col-md-4"><input v-model.trim="newUser.name" class="form-control mb-1" maxlength="120" :placeholder="$i18n.t('superadmin_name')"></div>
              <div class="col-md-4"><input v-model.trim="newUser.email" type="email" class="form-control mb-1" :placeholder="$i18n.t('superadmin_email')"></div>
              <div class="col-md-4"><input v-model.trim="newUser.reason" class="form-control mb-1" maxlength="1000" :placeholder="$i18n.t('superadmin_reason')"></div>
            </div>
            <button type="button" class="btn btn-success" :disabled="busy" @click="createUser">{{ $i18n.t('superadmin_create_and_reset') }}</button>
          </div>

          <div class="admin-operations__filters">
            <input v-model.trim="search" class="form-control" :placeholder="$i18n.t('superadmin_search')" @keyup.enter="loadUsers(1)">
            <select v-model="status" class="form-control" @change="loadUsers(1)">
              <option value="">{{ $i18n.t('superadmin_all_statuses') }}</option>
              <option value="active">{{ $i18n.t('superadmin_active') }}</option>
              <option value="blocked">{{ $i18n.t('superadmin_blocked') }}</option>
              <option value="deleted">{{ $i18n.t('superadmin_deactivated') }}</option>
            </select>
            <button type="button" class="btn btn-outline-primary" @click="loadUsers(1)">{{ $i18n.t('superadmin_search_button') }}</button>
          </div>

          <div class="table-responsive">
            <table class="table table-hover">
              <thead><tr>
                <th>{{ $i18n.t('superadmin_user') }}</th><th>{{ $i18n.t('superadmin_balance') }}</th>
                <th>{{ $i18n.t('superadmin_status') }}</th><th>{{ $i18n.t('admin_control_verify_user') }}</th><th>{{ $i18n.t('superadmin_registered') }}</th>
                <th>{{ $i18n.t('superadmin_actions') }}</th>
              </tr></thead>
              <tbody>
                <tr v-for="user in users.data" :key="user.id">
                  <td><strong>{{ user.name || '—' }}</strong><small class="d-block">{{ user.email }} · #{{ user.id }}</small></td>
                  <td>${{ Number(user.balance).toFixed(2) }}</td>
                  <td><span class="badge" :class="user.deleted_at ? 'badge-secondary' : (isBlocked(user) ? 'badge-danger' : 'badge-success')">
                    {{ user.deleted_at ? $i18n.t('superadmin_deactivated') : (isBlocked(user) ? $i18n.t('superadmin_blocked') : $i18n.t('superadmin_active')) }}
                  </span></td>
                  <td>
                    <span v-if="user.profile && user.profile.user_verify_at" class="badge badge-success">{{ $i18n.t('admin_control_account_active') }}</span>
                    <button
                      v-else-if="can('users.manage') && !user.deleted_at"
                      type="button"
                      class="btn btn-sm btn-success"
                      :disabled="busy"
                      @click="activateUser(user)"
                    >
                      {{ $i18n.t('admin_control_activate_account') }}
                    </button>
                    <span v-else class="text-muted">{{ $i18n.t('admin_control_account_inactive') }}</span>
                  </td>
                  <td>{{ formatDate(user.created_at) }}</td>
                  <td class="admin-operations__actions">
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary"
                      :disabled="loadingUserId === user.id"
                      @click="inspectUser(user)"
                    >
                      <span v-if="loadingUserId === user.id" class="spinner-border spinner-border-sm mr-1" role="status"></span>
                      {{ $i18n.t('superadmin_inspect') }}
                    </button>
                    <button v-if="can('users.manage') && !user.deleted_at" type="button" class="btn btn-sm btn-outline-warning" @click="toggleBlock(user)">
                      {{ isBlocked(user) ? $i18n.t('superadmin_unblock') : $i18n.t('superadmin_block') }}
                    </button>
                    <button v-if="can('users.manage') && !user.deleted_at" type="button" class="btn btn-sm btn-outline-danger" @click="deactivateUser(user)">
                      {{ $i18n.t('superadmin_deactivate') }}
                    </button>
                  </td>
                </tr>
                <tr v-if="!users.data || !users.data.length"><td colspan="6" class="text-center text-muted">{{ $i18n.t('superadmin_no_users') }}</td></tr>
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <small>{{ users.total || 0 }} {{ $i18n.t('superadmin_results') }}</small>
            <div>
              <button class="btn btn-sm btn-outline-secondary mr-1" :disabled="!users.current_page || users.current_page <= 1" @click="loadUsers(users.current_page - 1)">{{ $i18n.t('superadmin_previous') }}</button>
              <span>{{ users.current_page || 1 }} / {{ users.last_page || 1 }}</span>
              <button class="btn btn-sm btn-outline-secondary ml-1" :disabled="!users.last_page || users.current_page >= users.last_page" @click="loadUsers(users.current_page + 1)">{{ $i18n.t('superadmin_next') }}</button>
            </div>
          </div>
        </div>
      </section>

      <section v-if="selectedUser" ref="userDetails" class="card admin-operations__user-details">
        <div class="card-header admin-operations__toolbar">
          <h4 class="card-title mb-0">{{ $i18n.t('superadmin_user_detail') }} · #{{ selectedUser.user.id }}</h4>
          <button type="button" class="btn btn-outline-secondary" @click="selectedUser = null">{{ $i18n.t('superadmin_close') }}</button>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <dl class="admin-operations__details">
                <dt>{{ $i18n.t('superadmin_name') }}</dt><dd>{{ selectedUser.user.name || '—' }}</dd>
                <dt>{{ $i18n.t('superadmin_email') }}</dt><dd>{{ selectedUser.user.email }}</dd>
                <dt>{{ $i18n.t('superadmin_balance') }}</dt><dd>${{ Number(selectedUser.user.balance).toFixed(2) }}</dd>
                <dt>{{ $i18n.t('superadmin_referrals') }}</dt><dd>{{ selectedUser.referrals }}</dd>
                <dt>{{ $i18n.t('superadmin_referrer') }}</dt><dd>{{ selectedUser.referrer ? selectedUser.referrer.email : '—' }}</dd>
              </dl>
              <button v-if="can('users.manage')" class="btn btn-outline-info mb-1" type="button" @click="requestPasswordReset(selectedUser.user)">
                {{ $i18n.t('superadmin_password_reset') }}
              </button>
              <button v-if="can('finance.wallet.adjust')" class="btn btn-outline-danger mb-1" type="button" :disabled="walletAdjustmentBusy" @click="adjustWallet(selectedUser.user)">
                {{ $i18n.t('superadmin_wallet_adjustment') }}
              </button>
              <div v-if="showWalletAdjustment" class="admin-operations__form mt-1">
                <h5>{{ $i18n.t('superadmin_wallet_adjustment') }}</h5>
                <label for="wallet-adjustment-action">{{ $i18n.t('superadmin_wallet_action') }}</label>
                <select id="wallet-adjustment-action" v-model="walletAdjustment.action" class="form-control mb-1">
                  <option value="credit">{{ $i18n.t('superadmin_credit') }}</option>
                  <option value="debit">{{ $i18n.t('superadmin_debit') }}</option>
                </select>
                <label for="wallet-adjustment-amount">{{ $i18n.t('superadmin_amount') }}</label>
                <input
                  id="wallet-adjustment-amount"
                  v-model.trim="walletAdjustment.amount"
                  class="form-control mb-1"
                  type="number"
                  min="0.0001"
                  step="0.0001"
                  inputmode="decimal"
                >
                <label for="wallet-adjustment-reason">{{ $i18n.t('superadmin_reason') }}</label>
                <textarea
                  id="wallet-adjustment-reason"
                  v-model.trim="walletAdjustment.reason"
                  class="form-control mb-1"
                  maxlength="1000"
                  rows="3"
                ></textarea>
                <button
                  type="button"
                  class="btn btn-primary"
                  :disabled="walletAdjustmentBusy"
                  @click="submitWalletAdjustment(selectedUser.user)"
                >
                  {{ $i18n.t('superadmin_confirm') }}
                </button>
                <button
                  type="button"
                  class="btn btn-outline-secondary ml-1"
                  :disabled="walletAdjustmentBusy"
                  @click="cancelWalletAdjustment"
                >
                  {{ $i18n.t('superadmin_cancel') }}
                </button>
              </div>
            </div>
            <div class="col-md-6">
              <div v-if="can('users.manage')" class="admin-operations__form">
                <h5>{{ $i18n.t('superadmin_edit_user') }}</h5>
                <input v-model.trim="editUser.name" class="form-control mb-1" maxlength="120">
                <input v-model.trim="editUser.email" type="email" class="form-control mb-1">
                <input v-model.trim="editUser.reason" class="form-control mb-1" maxlength="1000" :placeholder="$i18n.t('superadmin_reason')">
                <button type="button" class="btn btn-primary" :disabled="busy" @click="saveUser">{{ $i18n.t('superadmin_save') }}</button>
              </div>
            </div>
          </div>
          <div class="table-responsive mt-2">
            <h5>{{ $i18n.t('superadmin_recent_finance') }}</h5>
            <table class="table table-sm">
              <thead><tr><th>{{ $i18n.t('superadmin_type') }}</th><th>{{ $i18n.t('superadmin_amount') }}</th><th>{{ $i18n.t('superadmin_status') }}</th><th>{{ $i18n.t('superadmin_reference') }}</th></tr></thead>
              <tbody>
                <tr v-for="deposit in selectedUser.deposits" :key="'d'+deposit.id"><td>{{ $i18n.t('superadmin_deposit') }}</td><td>{{ deposit.amount }}</td><td>{{ deposit.status }}</td><td>{{ deposit.reference || deposit.gateway_reference || '—' }}</td></tr>
                <tr v-for="withdrawal in selectedUser.withdrawals" :key="'w'+withdrawal.id"><td>{{ $i18n.t('superadmin_withdrawal') }}</td><td>{{ withdrawal.amount }}</td><td>{{ withdrawal.status }}</td><td>{{ withdrawal.transaction_reference || '—' }}</td></tr>
              </tbody>
            </table>
            <h5>{{ $i18n.t('superadmin_recent_trades') }}</h5>
            <table class="table table-sm">
              <thead><tr><th>{{ $i18n.t('superadmin_date') }}</th><th>{{ $i18n.t('superadmin_amount') }}</th><th>{{ $i18n.t('superadmin_profit') }}</th><th>{{ $i18n.t('superadmin_market') }}</th></tr></thead>
              <tbody><tr v-for="trade in selectedUser.trades" :key="'t'+trade.id"><td>{{ formatDate(trade.open_at || trade.created_at) }}</td><td>{{ trade.amount }}</td><td>{{ trade.profit }}</td><td>{{ trade.symbol_id }}</td></tr></tbody>
            </table>
          </div>
        </div>
      </section>

      <section v-if="tab === 'admins' && can('admin.manage')" class="card">
        <div class="card-header"><h4 class="card-title">{{ $i18n.t('superadmin_role_management') }}</h4></div>
        <div class="card-body">
          <p class="text-muted">{{ $i18n.t('superadmin_role_notice') }}</p>
          <div class="admin-operations__filters">
            <input v-model.number="roleForm.user_id" type="number" min="1" class="form-control" :placeholder="$i18n.t('superadmin_user_id')">
            <select v-model="roleForm.role" class="form-control" @change="applyRolePreset">
              <option value="admin">Admin</option><option value="finance_admin">Finance Admin</option>
              <option value="support_admin">Support Admin</option><option value="super_admin">Super Admin</option>
            </select>
            <input v-model.trim="roleForm.reason" class="form-control" maxlength="1000" :placeholder="$i18n.t('superadmin_reason')">
            <button type="button" class="btn btn-primary" :disabled="busy" @click="saveRole">{{ $i18n.t('superadmin_assign_role') }}</button>
          </div>
          <div class="admin-operations__permissions">
            <label v-for="permission in permissionCatalog" :key="permission">
              <input v-model="roleForm.permissions" type="checkbox" :value="permission"> {{ permission }}
            </label>
          </div>
          <div class="table-responsive mt-2">
            <table class="table">
              <thead><tr><th>ID</th><th>{{ $i18n.t('superadmin_user') }}</th><th>{{ $i18n.t('superadmin_role') }}</th><th>{{ $i18n.t('superadmin_permissions') }}</th></tr></thead>
              <tbody><tr v-for="admin in admins" :key="admin.id">
                <td>{{ admin.id }}</td><td>{{ admin.email }}</td><td>{{ admin.role_name }}</td><td>{{ (admin.permissions || []).join(', ') }}</td>
              </tr></tbody>
            </table>
          </div>
        </div>
      </section>

      <section v-if="tab === 'audit' && can('audit.view')" class="card">
        <div class="card-header admin-operations__toolbar">
          <h4 class="card-title mb-0">{{ $i18n.t('superadmin_audit') }}</h4>
          <button type="button" class="btn btn-outline-primary" @click="loadAudit(1)">{{ $i18n.t('superadmin_refresh') }}</button>
        </div>
        <div class="card-body table-responsive">
          <table class="table table-sm">
            <thead><tr><th>{{ $i18n.t('superadmin_date') }}</th><th>{{ $i18n.t('superadmin_actor') }}</th><th>{{ $i18n.t('superadmin_action') }}</th><th>{{ $i18n.t('superadmin_affected_user') }}</th><th>{{ $i18n.t('superadmin_reason') }}</th><th>{{ $i18n.t('superadmin_reference') }}</th></tr></thead>
            <tbody><tr v-for="entry in audit.data" :key="entry.id">
              <td>{{ formatDate(entry.created_at) }}</td><td>{{ entry.actor ? entry.actor.email : $i18n.t('superadmin_system') }}</td>
              <td>{{ entry.action }}</td><td>{{ entry.affected_user ? entry.affected_user.email : entry.affected_user_id || '—' }}</td>
              <td>{{ entry.reason }}</td><td>{{ entry.reference || '—' }}</td>
            </tr></tbody>
          </table>
          <div class="d-flex justify-content-between">
            <button class="btn btn-sm btn-outline-secondary" :disabled="!audit.current_page || audit.current_page <= 1" @click="loadAudit(audit.current_page - 1)">{{ $i18n.t('superadmin_previous') }}</button>
            <span>{{ audit.current_page || 1 }} / {{ audit.last_page || 1 }}</span>
            <button class="btn btn-sm btn-outline-secondary" :disabled="!audit.last_page || audit.current_page >= audit.last_page" @click="loadAudit(audit.current_page + 1)">{{ $i18n.t('superadmin_next') }}</button>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script>
import Swal from '../../../vendors/js/extensions/sweetalert2.all.min.js';

export default {
  name: 'SuperAdmin',
  data() {
    return {
      permissions: [],
      permissionCatalog: [],
      isSuperAdmin: false,
      dashboard: {},
      users: {},
      admins: [],
      audit: {},
      selectedUser: null,
      editUser: { name: '', email: '', reason: '' },
      newUser: { name: '', email: '', reason: '' },
      roleForm: { user_id: null, role: 'admin', permissions: [], reason: '' },
      reportType: 'deposits',
      search: '',
      status: '',
      from: '',
      to: '',
      tab: 'users',
      showCreate: false,
      busy: false,
      loadingDashboard: false,
      loadingUserId: null,
      walletAdjustmentBusy: false,
      showWalletAdjustment: false,
      walletAdjustment: { action: 'credit', amount: '', reason: '' },
      error: '',
      notice: '',
      summaryCards: [
        { key: 'users', label: 'superadmin_total_users' },
        { key: 'active_users', label: 'superadmin_active_users' },
        { key: 'blocked_users', label: 'superadmin_blocked_users' },
        { key: 'deposits', label: 'superadmin_deposit_count' },
        { key: 'deposit_amount', label: 'superadmin_verified_deposits', currency: true },
        { key: 'withdrawals', label: 'superadmin_withdrawal_count' },
        { key: 'withdrawal_amount', label: 'superadmin_processed_withdrawals', currency: true },
        { key: 'wallet_balances', label: 'superadmin_wallet_balances', currency: true },
        { key: 'trades', label: 'superadmin_trade_count' },
      ],
    };
  },
  mounted() {
    this.initialize();
  },
  methods: {
    can(permission) {
      return this.permissions.indexOf(permission) !== -1;
    },
    exportReport() {
      const params = new URLSearchParams({
        type: this.reportType,
        from: this.from,
        to: this.to,
      });
      window.location.href = '/admin/data/operations/reports/financial?' + params.toString();
    },
    initialize() {
      axios.get('/admin/data/operations/access').then((response) => {
        this.permissions = response.data.permissions || [];
        this.permissionCatalog = response.data.permission_catalog || [];
        this.isSuperAdmin = response.data.is_super_admin === true;
        if (!this.can('users.view')) {
          this.tab = this.isSuperAdmin ? 'admins' : (this.can('audit.view') ? 'audit' : '');
        }
        if (this.can('dashboard.view')) this.loadDashboard();
        if (this.can('users.view')) this.loadUsers(1);
        if (this.isSuperAdmin) this.loadAdmins();
        if (this.can('audit.view')) this.loadAudit(1);
      }).catch(this.handleError);
    },
    requestParams(page) {
      return {
        page: page,
        search: this.search || undefined,
        status: this.status || undefined,
        from: this.from || undefined,
        to: this.to || undefined,
      };
    },
    loadDashboard() {
      this.loadingDashboard = true;
      axios.get('/admin/data/operations/dashboard', { params: { from: this.from || undefined, to: this.to || undefined } })
        .then((response) => { this.dashboard = response.data; })
        .catch(this.handleError)
        .finally(() => { this.loadingDashboard = false; });
    },
    loadUsers(page) {
      axios.get('/admin/data/operations/users', { params: this.requestParams(page) })
        .then((response) => { this.users = response.data; })
        .catch(this.handleError);
    },
    loadAdmins() {
      axios.get('/admin/data/operations/admins').then((response) => {
        this.admins = response.data.accounts || [];
        this.permissionCatalog = response.data.permissions || this.permissionCatalog;
      }).catch(this.handleError);
    },
    loadAudit(page) {
      axios.get('/admin/data/operations/audit', { params: { page: page, from: this.from || undefined, to: this.to || undefined } })
        .then((response) => { this.audit = response.data; })
        .catch(this.handleError);
    },
    selectTab(tab) {
      this.tab = tab;
      if (tab === 'admins') this.loadAdmins();
      if (tab === 'audit') this.loadAudit(1);
    },
    inspectUser(user) {
      this.loadingUserId = user.id;
      this.error = '';
      axios.get('/admin/data/operations/users/' + user.id).then((response) => {
        this.selectedUser = response.data;
        this.editUser = {
          name: response.data.user.name || '',
          email: response.data.user.email || '',
          reason: '',
        };
        this.$nextTick(() => {
          if (this.$refs.userDetails) {
            this.$refs.userDetails.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        });
      }).catch(this.handleError).finally(() => {
        this.loadingUserId = null;
      });
    },
    createUser() {
      this.busy = true;
      this.error = '';
      axios.post('/admin/data/operations/users', this.newUser).then((response) => {
        this.notice = response.data.message;
        this.newUser = { name: '', email: '', reason: '' };
        this.showCreate = false;
        this.loadUsers(1);
        this.loadAudit(1);
      }).catch(this.handleError).finally(() => { this.busy = false; });
    },
    saveUser() {
      if (!this.selectedUser) return;
      this.busy = true;
      axios.put('/admin/data/operations/users/' + this.selectedUser.user.id, this.editUser)
        .then(() => {
          this.notice = this.$i18n.t('superadmin_user_saved');
          this.inspectUser(this.selectedUser.user);
          this.loadUsers(this.users.current_page || 1);
          this.loadAudit(1);
        }).catch(this.handleError).finally(() => { this.busy = false; });
    },
    isBlocked(user) {
      return user.banned !== null && Number(user.banned) !== 0;
    },
    activateUser(user) {
      this.askReason(this.$i18n.t('admin_control_activate_confirm'))
        .then((reason) => {
          if (!reason) return;
          this.busy = true;
          return axios.post('/admin/data/verifyAccount', { id: user.id, reason: reason });
        }).then((response) => {
          if (response) {
            this.notice = response.data.message;
            this.loadUsers(this.users.current_page || 1);
            this.loadAudit(1);
          }
        }).catch(this.handleError).finally(() => { this.busy = false; });
    },
    toggleBlock(user) {
      const nextStatus = this.isBlocked(user) ? 'active' : 'blocked';
      this.askReason(this.$i18n.t(nextStatus === 'blocked' ? 'superadmin_block_confirm' : 'superadmin_unblock_confirm'))
        .then((reason) => {
          if (!reason) return;
          return axios.post('/admin/data/operations/users/' + user.id + '/status', { status: nextStatus, reason: reason });
        }).then((response) => {
          if (response) {
            this.notice = this.$i18n.t('superadmin_status_saved');
            this.loadUsers(this.users.current_page || 1);
            this.loadAudit(1);
          }
        }).catch(this.handleError);
    },
    deactivateUser(user) {
      Swal.fire({
        title: this.$i18n.t('superadmin_delete_title'),
        text: this.$i18n.t('superadmin_delete_retains_records'),
        input: 'textarea',
        inputAttributes: { maxlength: 1000 },
        inputValidator: (value) => !value || value.trim().length < 10 ? this.$i18n.t('admin_reason_required') : null,
        showCancelButton: true,
        confirmButtonText: this.$i18n.t('superadmin_deactivate'),
        cancelButtonText: this.$i18n.t('superadmin_cancel'),
      }).then((result) => {
        if (result.dismiss) return;
        return axios.delete('/admin/data/operations/users/' + user.id, {
          data: { confirmation: true, reason: result.value },
        });
      }).then((response) => {
        if (response) {
          this.notice = response.data.message;
          this.loadUsers(this.users.current_page || 1);
          this.loadAudit(1);
        }
      }).catch(this.handleError);
    },
    requestPasswordReset(user) {
      if (!window.confirm(this.$i18n.t('superadmin_password_reset_confirm'))) return;
      axios.post('/admin/data/operations/users/' + user.id + '/password-reset')
        .then((response) => { this.notice = response.data.message; this.loadAudit(1); })
        .catch(this.handleError);
    },
    adjustWallet(user) {
      this.error = '';
      this.walletAdjustment = { action: 'credit', amount: '', reason: '' };
      this.showWalletAdjustment = true;
    },
    cancelWalletAdjustment() {
      this.showWalletAdjustment = false;
      this.walletAdjustment = { action: 'credit', amount: '', reason: '' };
    },
    submitWalletAdjustment(user) {
      const amount = this.walletAdjustment.amount;
      const reason = this.walletAdjustment.reason;
      if (!/^\d+(?:\.\d{1,4})?$/.test(amount) || Number(amount) <= 0) {
        this.error = this.$i18n.t('superadmin_amount_required');
        return;
      }
      if (!reason || reason.trim().length < 10) {
        this.error = this.$i18n.t('admin_reason_required');
        return;
      }
      if (!window.confirm(this.$i18n.t('admin_confirm_action'))) return;
      this.walletAdjustmentBusy = true;
      this.error = '';
      axios.post('/admin/data/operations/users/' + user.id + '/wallet-adjustments', {
        action: this.walletAdjustment.action,
        amount: amount,
        reason: reason.trim(),
        reference: this.makeReference(),
        confirmation: true,
      }).then((response) => {
        if (!response.data || response.data.success !== true) {
          this.error = response.data && response.data.message
            ? response.data.message
            : this.$i18n.t('superadmin_error');
          return;
        }
        this.notice = this.$i18n.t('superadmin_wallet_updated');
        this.showWalletAdjustment = false;
        this.walletAdjustment = { action: 'credit', amount: '', reason: '' };
        if (response.data.balance !== null && response.data.balance !== undefined) {
          const listedUser = (this.users.data || []).find((item) => Number(item.id) === Number(user.id));
          if (listedUser) this.$set(listedUser, 'balance', response.data.balance);
          if (this.selectedUser && Number(this.selectedUser.user.id) === Number(user.id)) {
            this.$set(this.selectedUser.user, 'balance', response.data.balance);
          }
        }
        this.loadUsers(this.users.current_page || 1);
        this.inspectUser(user);
        this.loadDashboard();
        this.loadAudit(1);
      }).catch(this.handleError).finally(() => {
        this.walletAdjustmentBusy = false;
      });
    },
    saveRole() {
      if (!this.roleForm.user_id) return;
      this.busy = true;
      axios.put('/admin/data/operations/admins/' + this.roleForm.user_id, {
        role: this.roleForm.role,
        permissions: this.roleForm.permissions,
        reason: this.roleForm.reason,
      }).then(() => {
        this.notice = this.$i18n.t('superadmin_role_saved');
        this.roleForm = { user_id: null, role: 'admin', permissions: [], reason: '' };
        this.loadAdmins();
        this.loadAudit(1);
      }).catch(this.handleError).finally(() => { this.busy = false; });
    },
    applyRolePreset() {
      const presets = {
        admin: ['dashboard.view', 'users.view'],
        finance_admin: [
          'dashboard.view', 'users.view', 'finance.deposits.view', 'finance.deposits.manage',
          'finance.withdrawals.view', 'finance.withdrawals.manage', 'finance.wallet.view',
          'finance.wallet.adjust', 'reports.export',
        ],
        support_admin: ['dashboard.view', 'users.view', 'trading.view', 'referrals.view'],
        super_admin: this.permissionCatalog.slice(),
      };
      this.roleForm.permissions = presets[this.roleForm.role] || [];
    },
    askReason(title) {
      return Swal.fire({
        title: title,
        input: 'textarea',
        inputAttributes: { maxlength: 1000 },
        inputValidator: (value) => !value || value.trim().length < 10 ? this.$i18n.t('admin_reason_required') : null,
        showCancelButton: true,
        confirmButtonText: this.$i18n.t('superadmin_confirm'),
        cancelButtonText: this.$i18n.t('superadmin_cancel'),
      }).then((result) => result.dismiss ? null : result.value);
    },
    makeReference() {
      if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID();
      return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.floor(Math.random() * 16);
        return (character === 'x' ? random : (random & 3 | 8)).toString(16);
      });
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleString() : '—';
    },
    formatAmount(value) {
      const amount = Number(value);
      return Number.isFinite(amount)
        ? amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : '0.00';
    },
    handleError(error) {
      this.error = error && error.response && error.response.data && error.response.data.message
        ? error.response.data.message
        : this.$i18n.t('superadmin_error');
    },
  },
};
</script>

<style scoped>
.admin-operations__heading,
.admin-operations__toolbar,
.admin-operations__date-filter,
.admin-operations__filters {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  justify-content: space-between;
}
.admin-operations__date-filter .form-control { max-width: 190px; }
.admin-operations__summary {
  background: rgba(90, 141, 238, 0.08);
  border-radius: 0.6rem;
  display: flex;
  flex-direction: column;
  min-height: 78px;
  padding: 0.8rem;
}
.admin-operations__summary strong { font-size: 1.35rem; }
.admin-operations__alerts { align-items: center; display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
.admin-operations__export { align-items: center; display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: 1rem; }
.admin-operations__export .form-control { max-width: 280px; }
.admin-operations__tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; overflow-x: auto; }
.admin-operations__tabs button {
  background: transparent;
  border: 1px solid #d6dce5;
  border-radius: 0.4rem;
  color: inherit;
  padding: 0.55rem 1rem;
  white-space: nowrap;
}
.admin-operations__tabs button.active { background: #5a8dee; border-color: #5a8dee; color: #fff; }
.admin-operations__filters { justify-content: flex-start; margin-bottom: 1rem; }
.admin-operations__filters > .form-control { max-width: 280px; }
.admin-operations__actions { min-width: 210px; }
.admin-operations__actions .btn { margin: 0.1rem; }
.admin-operations__form { background: rgba(138, 153, 181, 0.08); border-radius: 0.5rem; padding: 1rem; }
.admin-operations__user-details { scroll-margin-top: 1rem; }
.admin-operations__details { display: grid; grid-template-columns: minmax(100px, 0.5fr) 1fr; gap: 0.35rem; }
.admin-operations__details dd { overflow-wrap: anywhere; }
.admin-operations__permissions { display: grid; gap: 0.5rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top: 1rem; }
.admin-operations__permissions label { overflow-wrap: anywhere; }
@media (max-width: 575.98px) {
  .admin-operations__heading { align-items: flex-start; flex-direction: column; }
  .admin-operations__date-filter, .admin-operations__date-filter .form-control { width: 100%; max-width: none; }
  .admin-operations__filters > .form-control { max-width: none; width: 100%; }
}
</style>
