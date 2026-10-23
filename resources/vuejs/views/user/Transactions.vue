<template>
  <div class="content-wrapper">
    <div class="content-body">
      <section class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
          <div>
            <h4 class="card-title mb-0">{{ $i18n.t('transaction_history_title') }}</h4>
            <small class="text-muted">{{ $i18n.t('transaction_history_description') }}</small>
          </div>
          <button type="button" class="btn btn-outline-primary" :disabled="loading" @click="loadTransactions(currentPage)">
            {{ $i18n.t('transaction_history_refresh') }}
          </button>
        </div>
        <div class="card-body">
          <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
          <div v-if="loading" class="text-center py-2" role="status">
            <span class="spinner-border text-primary"></span>
          </div>
          <div v-else-if="transactions.length" class="table-responsive">
            <table class="table table-hover transaction-history__table">
              <thead>
                <tr>
                  <th>{{ $i18n.t('transaction_history_type') }}</th>
                  <th>{{ $i18n.t('payout_amount_simple') }}</th>
                  <th>{{ $i18n.t('transaction_history_payout') }}</th>
                  <th>{{ $i18n.t('payout_status') }}</th>
                  <th>{{ $i18n.t('transaction_history_reference') }}</th>
                  <th>{{ $i18n.t('payout_date') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="transaction in transactions" :key="transaction.type + '-' + transaction.id">
                  <td>{{ $i18n.t(transaction.type === 'deposit' ? 'transaction_history_deposit' : 'transaction_history_withdrawal') }}</td>
                  <td>${{ Number(transaction.amount).toFixed(2) }}</td>
                  <td>{{ payoutAmount(transaction) }}</td>
                  <td>
                    <span class="badge transaction-history__status" :class="statusClass(transaction)">
                      {{ $i18n.t(statusLabel(transaction)) }}
                    </span>
                    <small class="d-block transaction-history__description mt-25">{{ $i18n.t(statusDescription(transaction)) }}</small>
                  </td>
                  <td>{{ transaction.reference || '—' }}</td>
                  <td>{{ formatDate(transaction.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else-if="!loading" class="text-muted mb-0">{{ $i18n.t('transaction_history_empty') }}</p>
          <div v-if="total" class="d-flex flex-wrap justify-content-between align-items-center mt-1">
            <small>{{ total }} {{ $i18n.t('superadmin_results') }}</small>
            <div>
              <button type="button" class="btn btn-sm btn-outline-secondary mr-1" :disabled="currentPage <= 1 || loading" @click="loadTransactions(currentPage - 1)">
                {{ $i18n.t('superadmin_previous') }}
              </button>
              <span>{{ currentPage }} / {{ lastPage }}</span>
              <button type="button" class="btn btn-sm btn-outline-secondary ml-1" :disabled="currentPage >= lastPage || loading" @click="loadTransactions(currentPage + 1)">
                {{ $i18n.t('superadmin_next') }}
              </button>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Transactions',
  data() {
    return {
      transactions: [],
      currentPage: 1,
      lastPage: 1,
      total: 0,
      loading: false,
      error: '',
    };
  },
  mounted() {
    this.loadTransactions(1);
  },
  methods: {
    loadTransactions(page) {
      this.loading = true;
      this.error = '';
      axios.get('/data/transactions', { params: { page: page } })
        .then((response) => {
          this.transactions = response.data.data;
          this.currentPage = response.data.current_page;
          this.lastPage = response.data.last_page;
          this.total = response.data.total;
        })
        .catch((error) => {
          this.error = error && error.response && error.response.data && error.response.data.message
            ? error.response.data.message
            : this.$i18n.t('transaction_history_load_error');
        })
        .finally(() => {
          this.loading = false;
        });
    },
    payoutAmount(transaction) {
      if (transaction.payout_amount === null || transaction.payout_amount === undefined) return '—';
      return Number(transaction.payout_amount).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }) + ' ' + transaction.currency;
    },
    statusLabel(transaction) {
      if (transaction.type === 'deposit') {
        const statuses = {
          0: 'transaction_status_deposit_waiting',
          1: 'transaction_status_deposit_completed',
          2: 'transaction_status_deposit_cancelled',
        };
        return statuses[transaction.status] || 'transaction_status_unknown';
      }
      const statuses = {
        0: 'transaction_status_withdrawal_pending',
        1: 'transaction_status_withdrawal_completed',
        2: 'transaction_status_withdrawal_rejected',
        3: 'transaction_status_withdrawal_processing',
        4: 'transaction_status_withdrawal_failed',
      };
      return statuses[transaction.status] || 'transaction_status_unknown';
    },
    statusDescription(transaction) {
      if (transaction.type === 'deposit') {
        const statuses = {
          0: 'transaction_status_deposit_waiting_description',
          1: 'transaction_status_deposit_completed_description',
          2: 'transaction_status_deposit_cancelled_description',
        };
        return statuses[transaction.status] || 'transaction_status_unknown_description';
      }
      const statuses = {
        0: 'transaction_status_withdrawal_pending_description',
        1: 'transaction_status_withdrawal_completed_description',
        2: 'transaction_status_withdrawal_rejected_description',
        3: 'transaction_status_withdrawal_processing_description',
        4: 'transaction_status_withdrawal_failed_description',
      };
      return statuses[transaction.status] || 'transaction_status_unknown_description';
    },
    statusClass(transaction) {
      if (transaction.type === 'deposit') {
        if (Number(transaction.status) === 1) return 'transaction-history__status--completed';
        if (Number(transaction.status) === 2) return 'transaction-history__status--attention';
        return 'transaction-history__status--pending';
      }
      const classes = {
        0: 'transaction-history__status--pending',
        1: 'transaction-history__status--completed',
        2: 'transaction-history__status--attention',
        3: 'transaction-history__status--processing',
        4: 'transaction-history__status--attention',
      };
      return classes[transaction.status] || 'transaction-history__status--pending';
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleString() : '—';
    },
  },
};
</script>

<style scoped>
.transaction-history__table {
  color: #344054;
}

.transaction-history__status {
  display: inline-block;
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.35;
  white-space: normal;
}

.transaction-history__status--pending {
  background-color: #fff0c2 !important;
  color: #664d03 !important;
}

.transaction-history__status--processing {
  background-color: #cfe2ff !important;
  color: #084298 !important;
}

.transaction-history__status--completed {
  background-color: #d1e7dd !important;
  color: #0f5132 !important;
}

.transaction-history__status--attention {
  background-color: #f8d7da !important;
  color: #842029 !important;
}

.transaction-history__description {
  color: #475467;
  font-size: 0.8rem;
  line-height: 1.4;
  max-width: 24rem;
  white-space: normal;
}

.dark-layout .transaction-history__table {
  color: #eef2f7;
}

.semi-dark-layout .transaction-history__table {
  color: #344054;
}

.dark-layout .transaction-history__description {
  color: #c3ccda;
}

.semi-dark-layout .transaction-history__description {
  color: #475467;
}
</style>
