<template>
    <div class="content-wrapper">
        <div class="content-body">
            <div class="row">
              <div class="col-md-12">
                <div v-for="value in errors" class="alert bg-rgba-danger alert-dismissible mb-2 w-100" role="alert">
                  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">×</span>
                  </button>
                  <div class="d-flex align-items-center">
                    <i class="bx bx-error"></i>
                    <span>
                      {{ value }}
                    </span>
                  </div>
                </div>

                <div v-for="value in success" class="alert bg-rgba-success alert-dismissible mb-2 w-100" role="alert">
                  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">×</span>
                  </button>
                  <div class="d-flex align-items-center">
                    <i class="bx bx-error"></i>
                    <span>
                      {{ value }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
                <div class="col-md-7">
                    <section class="card">
                                        <div class="card-header withdraw-wallet__header">
                            <h4 class="card-title">{{ $i18n.t('payout_title') }}</h4>
                                            <div v-if="account" class="withdraw-wallet__balance">
                                                <small>{{ $i18n.t('withdraw_wallet_available') }}</small>
                                                <strong>${{ Number(account.balance).toFixed(2) }}</strong>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div v-if="account && !account.withdrawal_verified && !isAdmin" class="alert alert-warning" role="alert">
                                                {{ $i18n.t('withdraw_wallet_verification_required') }}
                                                <router-link to="/profile">{{ $i18n.t('withdraw_wallet_complete_verification') }}</router-link>
                                            </div>
                                            <div class="row withdraw-wallet__methods">
                                                <div class="col-sm-6 mb-1">
                                                    <button type="button" class="withdraw-wallet__method" :class="{ 'is-selected': method === 'mpesa' }"
                                                            @click="selectMethod('mpesa')" :aria-pressed="method === 'mpesa'">
                                                        <span class="withdraw-wallet__logo withdraw-wallet__logo--mpesa" aria-hidden="true">
                                                            <strong><span>M</span>-<b>PESA</b></strong>
                                                        </span>
                                                        <span><strong>M-Pesa</strong><small>{{ $i18n.t('withdraw_wallet_mpesa_description') }}</small></span>
                                                    </button>
                                                </div>
                                                <div class="col-sm-6 mb-1">
                                                    <button type="button" class="withdraw-wallet__method" :class="{ 'is-selected': method === 'binance_usdt_bep20' }"
                                                            @click="selectMethod('binance_usdt_bep20')" :aria-pressed="method === 'binance_usdt_bep20'">
                                                        <span class="withdraw-wallet__logo withdraw-wallet__logo--binance" aria-hidden="true">
                                                            <svg viewBox="0 0 48 48" focusable="false">
                                                                <path d="m24 3 6 6-6 6-6-6 6-6Zm-12 12 6 6-6 6-6-6 6-6Zm24 0 6 6-6 6-6-6 6-6ZM24 21l6 6-6 6-6-6 6-6Zm-12 12 6 6-6 6-6-6 6-6Zm24 0 6 6-6 6-6-6 6-6ZM24 33l6 6-6 6-6-6 6-6Z" fill="currentColor"/>
                                                            </svg>
                                                        </span>
                                                        <span><strong>Binance <small>USDT · BEP20</small></strong><small>{{ $i18n.t('withdraw_wallet_binance_description') }}</small></span>
                                                    </button>
                                                </div>
                                            </div>

                                            <div v-if="method === 'mpesa'" class="withdraw-wallet__network-warning">
                                                <label for="withdrawMpesaPhone">{{ $i18n.t('withdraw_wallet_phone') }}</label>
                                                <div class="input-group mb-2">
                                                    <div class="input-group-prepend"><span class="input-group-text">+254</span></div>
                                                    <input id="withdrawMpesaPhone" v-model.trim="phoneNumber" type="tel" inputmode="numeric"
                                                           autocomplete="tel-national" maxlength="9" class="form-control" placeholder="7XXXXXXXX">
                                                </div>
                                            </div>
                                            <div v-else class="alert alert-warning withdraw-wallet__network-warning" role="alert">
                                                <strong>BNB Smart Chain (BEP20)</strong>
                                                <p class="mb-0">{{ $i18n.t('withdraw_wallet_network_warning') }}</p>
                                                <label for="withdrawUsdtAddress" class="mt-1">{{ $i18n.t('withdraw_wallet_address') }}</label>
                                                <input id="withdrawUsdtAddress" v-model.trim="walletAddress" type="text" class="form-control"
                                                       autocomplete="off" spellcheck="false" placeholder="0x...">
                                            </div>

                                            <div class="form-group mt-2">
                                                <label for="withdrawAmount">{{ $i18n.t('payout_amount') }} (USD)</label>
                                                <input id="withdrawAmount" ref="ci" type="text" class="form-control" v-model="amount"
                                                       v-currency="{currency: null, autoDecimalMode: true, valueRange: {min: minimumAmount, max: maximumAmount}}">
                                                <small class="text-muted">{{ $i18n.t('withdraw_wallet_min_max', {min: minimumAmount, max: maximumAmount}) }}</small>
                                            </div>

                                            <div class="withdraw-wallet__totals">
                                                <div><span>{{ $i18n.t('withdraw_wallet_amount') }}</span><strong>${{ amount_formatted.toFixed(2) }}</strong></div>
                                                <div><span>{{ $i18n.t('withdraw_wallet_fee') }}</span><strong>${{ feeAmount.toFixed(2) }}</strong></div>
                                                <div class="withdraw-wallet__payout"><span>{{ $i18n.t('withdraw_wallet_payout') }}</span><strong>{{ method === 'mpesa' ? 'KES' : 'USDT' }} {{ payoutAmount.toFixed(2) }}</strong></div>
                                                <small v-if="method === 'mpesa' && options && options.usd_kes_rate" class="text-muted d-block text-right">
                                                    {{ $i18n.t('withdraw_wallet_exchange_rate') }}: 1 USD = {{ Number(options.usd_kes_rate).toFixed(2) }} KES
                                                </small>
                                            </div>
                                            <p class="text-muted small mt-1">{{ $i18n.t('withdraw_wallet_review_note') }}</p>
                                            <button type="button" class="btn btn-primary" :disabled="continueButtonDisabled" @click="openReview">
                                                {{ $i18n.t('withdraw_wallet_review_button') }}
                                            </button>
                                        </div>
                                    </section>

                                    <div v-if="reviewOpen" class="withdraw-wallet__modal-backdrop" role="presentation">
                                        <section class="withdraw-wallet__review card" role="dialog" aria-modal="true" :aria-label="$i18n.t('withdraw_wallet_review_title')">
                                            <div class="card-header"><h4 class="card-title mb-0">{{ $i18n.t('withdraw_wallet_review_title') }}</h4></div>
                                            <div class="card-body">
                                                <dl>
                                                    <dt>{{ $i18n.t('withdraw_wallet_destination') }}</dt>
                                                    <dd class="withdraw-wallet__destination">{{ destination }}</dd>
                                                    <dt>{{ $i18n.t('withdraw_wallet_amount') }}</dt>
                                                    <dd>${{ amount_formatted.toFixed(2) }}</dd>
                                                    <dt>{{ $i18n.t('withdraw_wallet_fee') }}</dt>
                                                    <dd>${{ feeAmount.toFixed(2) }}</dd>
                                                    <dt>{{ $i18n.t('withdraw_wallet_payout') }}</dt>
                                                    <dd><strong>{{ method === 'mpesa' ? 'KES' : 'USDT' }} {{ payoutAmount.toFixed(2) }}</strong></dd>
                                                </dl>
                                                <p class="small text-muted">{{ $i18n.t('withdraw_wallet_admin_review') }}</p>
                                                <div class="d-flex justify-content-end">
                                                    <button type="button" class="btn btn-outline-secondary mr-1" :disabled="submitting" @click="reviewOpen = false">
                                                        {{ $i18n.t('payout_close') }}
                                                    </button>
                                                    <button type="button" class="btn btn-primary" :disabled="submitting" @click="submitPayout">
                                                        <span v-if="submitting" class="spinner-border spinner-border-sm" role="status"></span>
                                                        {{ $i18n.t('withdraw_wallet_confirm') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </section>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-md-5">
                    <section class="card">
                        <div class="card-header">
                            <h4 class="card-title">{{ $i18n.t('payout_your_turnover') }}</h4>
                        </div>
                        <div class="card-content">
                            <div class="card-body">
                                <div class="card-text">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="d-flex justify-content-between align-items-end">
                                                        <div class="dashboard-content-left">
                                                            <span v-show="!account" role="status" aria-hidden="true" class="mb-2 spinner-border spinner-grow-sm text-primary" style="width:3rem;height:3rem;"></span>
                                                            <h1 v-if="account" class="text-primary font-large-2 text-bold-500">$ {{ account_left_turnover }}</h1>
                                                            <p>{{ $i18n.t('payout_left_turnover') }}
                                                                <span v-show="!account" role="status" aria-hidden="true" class="spinner-border spinner-grow-sm"></span>
                                                                <code v-if="account">{{ left_turnover }} %</code>
                                                              {{ $i18n.t('payout_from_all_bonus_amount') }}.</p>
                                                            <button v-bind:disabled="discardButtunDisabled" @click="discardBonus" type="button" class="btn btn-primary glow">{{ $i18n.t('payout_cancel_bonus') }}</button>
                                                            <small v-if="account && account_left_turnover > 0" v-show="account" class="ml-1">{{ $i18n.t('payout_balance_be') }} <code>{{ afterDiscardBonus }} $</code> {{ $i18n.t('payout_after_cancel') }}</small>
                                                        </div>
                                                        <div class="dashboard-content-right">
                                                            <img src="/images/icon/cup.png" height="155" width="155" class="img-fluid" style="float:right;">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section class="card">
                        <div class="card-content">
                            <div class="card-body">
                                <div class="card-text">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="row">
                                                <div class="col-md-12">
                                                  {{ $i18n.t('payout_conditions') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div class="content-body">
            <div class="row">
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-header">
                            <h4 class="card-title">{{ $i18n.t('payout_history_title') }}</h4>
                        </div>
                        <div class="card-content">
                            <div class="card-body">
                                <div class="card-text">
                                  <div class="table-responsive">
                                      <table class="table" id="withdrawalHistory" style="width:100%">
                                          <thead>
                                          <tr>
                                              <th>{{ $i18n.t('payout_amount_simple') }}</th>
                                              <th>{{ $i18n.t('payout_system') }}</th>
                                              <th>{{ $i18n.t('payout_props') }}</th>
                                              <th>{{ $i18n.t('payout_status') }}</th>
                                              <th id="hide">{{ $i18n.t('payout_action') }}</th>
                                              <th>{{ $i18n.t('payout_date') }}</th>
                                          </tr>
                                          </thead>
                                      </table>
                                  </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import dateformat from "dateformat";
    import Swal from '../../../vendors/js/extensions/sweetalert2.all.min.js';
    import {getCookie} from "../../js/functions";

    export default {
        name: "Deposit",
        data: function () {
            return {
                amount: '10',
                account: null,
                method: 'mpesa',
                success: [],
                errors: [],
                phoneNumber: '',
                walletAddress: '',
                options: null,
                reviewOpen: false,
                submitting: false,
                requestKey: '',
            }
        },
        methods: {
            getAccountData: function(){
                let self = this;
                let url = '/data/getAccountData';
                if(this.isAdmin){
                  url = '/data/getAccountData/'+this.userId;
                }
                axios.post(url).then(function(response){
                    self.account = response.data;
                }).catch(function(error) {
                    self.errors.push(error.response && error.response.data && error.response.data.message
                        ? error.response.data.message
                        : self.$i18n.t('withdraw_wallet_load_error'));
                });
            },
            getWithdrawalOptions: function () {
                axios.get('/data/withdrawal/options').then((response) => {
                    this.options = response.data;
                }).catch((error) => {
                    this.errors.push(error.response && error.response.data && error.response.data.message
                        ? error.response.data.message
                        : this.$i18n.t('withdraw_wallet_load_error'));
                });
            },
            selectMethod: function (method) {
                this.method = method;
                this.reviewOpen = false;
                this.errors = [];
            },
            discardBonus: function () {
                let self = this;
                this.success = [];
                this.errors = [];
                let url = '/data/discardBonus';
                let payload = {};
                if(this.isAdmin){
                  url = '/data/discardBonus/'+this.userId;
                  let reason = window.prompt(this.$i18n.t('admin_action_reason'));
                  if (!reason || reason.trim().length < 10 || !window.confirm(this.$i18n.t('admin_confirm_action'))) return;
                  payload = { id: this.userId, reason: reason.trim() };
                }
                axios.post(url, payload).then(function(response){
                    self.getAccountData();
                    if(response.data.success === true){
                        self.success.push(response.data.message);
                    } else {
                        self.errors.push(response.data.message);
                    }
                });
            },
            openReview: function () {
                this.errors = [];
                if (!this.isAdmin && this.account && !this.account.withdrawal_verified) {
                    this.errors.push(this.$i18n.t('withdraw_wallet_verification_required'));
                    return;
                }
                if (!this.options) {
                    this.errors.push(this.$i18n.t('withdraw_wallet_load_error'));
                    return;
                }
                if (this.method === 'mpesa' && !this.options.usd_kes_rate) {
                    this.errors.push(this.$i18n.t('withdraw_wallet_rate_unavailable'));
                    return;
                }
                if (this.amount_formatted < this.minimumAmount || this.amount_formatted > this.maximumAmount) {
                    this.errors.push(this.$i18n.t('withdraw_wallet_invalid_amount'));
                    return;
                }
                if (!this.account || this.amount_formatted > Number(this.account.balance)) {
                    this.errors.push(this.$i18n.t('withdraw_wallet_insufficient_balance'));
                    return;
                }
                if (this.method === 'mpesa' && !/^(?:7|1)\d{8}$/.test(this.phoneNumber)) {
                    this.errors.push(this.$i18n.t('withdraw_wallet_invalid_phone'));
                    return;
                }
                if (this.method === 'binance_usdt_bep20' && !/^0x[a-fA-F0-9]{40}$/.test(this.walletAddress)) {
                    this.errors.push(this.$i18n.t('withdraw_wallet_invalid_address'));
                    return;
                }
                this.reviewOpen = true;
            },
            makeRequestKey: function () {
                if (window.crypto && window.crypto.randomUUID) {
                    return window.crypto.randomUUID();
                }
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
                    const random = Math.floor(Math.random() * 16);
                    return (character === 'x' ? random : (random & 3 | 8)).toString(16);
                });
            },
            submitPayout: function () {
                if (this.submitting) {
                    return;
                }
                let reason = '';
                if (this.isAdmin) {
                    reason = window.prompt(this.$i18n.t('admin_action_reason'));
                    if (!reason || reason.trim().length < 10 || !window.confirm(this.$i18n.t('admin_confirm_action'))) return;
                }
                this.submitting = true;
                this.errors = [];
                const url = this.isAdmin ? '/data/processPayout/' + this.userId : '/data/withdrawal';
                this.requestKey = this.requestKey || this.makeRequestKey();
                const payload = {
                    method: this.method,
                    amount: this.amount_formatted.toFixed(2),
                    request_key: this.requestKey,
                };
                if (this.isAdmin) {
                    payload.reason = reason.trim();
                }
                if (this.method === 'mpesa') {
                    payload.phone_number = '+254' + this.phoneNumber;
                } else {
                    payload.wallet_address = this.walletAddress;
                }
                if (this.isAdmin) {
                    payload.id = this.userId;
                }

                axios.post(url, payload).then((response) => {
                    if (response.data.success !== true) {
                        this.errors.push(response.data.message || this.$i18n.t('withdraw_wallet_submit_error'));
                        return;
                    }
                    this.requestKey = '';
                    this.reviewOpen = false;
                    this.success = [response.data.message];
                    this.getAccountData();
                    this.refreshHistory();
                }).catch((error) => {
                    this.errors.push(error.response && error.response.data && error.response.data.message
                        ? error.response.data.message
                        : this.$i18n.t('withdraw_wallet_submit_error'));
                }).finally(() => {
                    this.submitting = false;
                });
            },
            refreshHistory: function () {
                if ($.fn.DataTable.isDataTable('#withdrawalHistory')) {
                    $('#withdrawalHistory').DataTable().ajax.reload(null, false);
                }
            },
            adminUpdateWithdrawal: function (id, text, status, transactionReference, comment) {
                axios.post('/admin/data/processWithdrawal', {
                    id: id,
                    text: text,
                    status: status,
                    transaction_reference: transactionReference,
                    comment: comment,
                }).then((response) => {
                    if (response.data.success) {
                        toastr.success(response.data.message, this.$i18n.t('payout_success'));
                        this.refreshHistory();
                        this.getAccountData();
                    } else {
                        toastr.error(response.data.message, this.$i18n.t('payout_error'));
                    }
                }).catch((error) => {
                    const message = error.response && error.response.data && error.response.data.message
                        ? error.response.data.message
                        : this.$i18n.t('withdraw_wallet_submit_error');
                    toastr.error(message, this.$i18n.t('payout_error'));
                });
            },
            initDatatable: function () {
              let url = '/data/withdrawalHistory';
              if(this.isAdmin){
                url = '/data/withdrawalHistory/'+this.userId;
              }
              let self = this;
                $('#withdrawalHistory').DataTable({
                    "iDisplayLength": 10,
                    "processing": true,
                    "serverSide": true,
                    "order": [[self.isAdmin ? 5 : 4, "desc"]],
                    "drawCallback": function(settings) {
                        $('[data-toggle="popover"]').popover({ html : true });
                        $('[data-toggle="tooltip"]').popover({ html : true });
                        $('.decline-withdrawal').on('click', function () {
                          const id = $(this).attr('data-id');
                          const text = $(this).attr('data-text');
                          Swal.fire({
                            title: self.$i18n.t('withdraw_wallet_reject_title'),
                            input: 'text',
                            inputAttributes: { autocapitalize: 'off' },
                            showCancelButton: true,
                            confirmButtonText: self.$i18n.t('payout_cancel_payout'),
                            cancelButtonText: self.$i18n.t('payout_close'),
                          }).then((result) => {
                            if (!result.dismiss) {
                              self.adminUpdateWithdrawal(id, text, 2, '', result.value || '');
                            }
                          });
                          return false;
                        });

                        $('.fail-withdrawal').on('click', function () {
                          const id = $(this).attr('data-id');
                          const text = $(this).attr('data-text');
                          Swal.fire({
                            title: self.$i18n.t('withdraw_wallet_fail_title'),
                            text: self.$i18n.t('withdraw_wallet_fail_prompt'),
                            input: 'text',
                            inputAttributes: { autocapitalize: 'off' },
                            showCancelButton: true,
                            confirmButtonText: self.$i18n.t('withdraw_wallet_mark_failed'),
                            cancelButtonText: self.$i18n.t('payout_close'),
                          }).then((result) => {
                            if (!result.dismiss) {
                              self.adminUpdateWithdrawal(id, text, 4, '', result.value || '');
                            }
                          });
                          return false;
                        });

                        $('.process-withdrawal').on('click', function () {
                          const id = $(this).attr('data-id');
                          const text = $(this).attr('data-text');
                          Swal.fire({
                            title: self.$i18n.t('withdraw_wallet_processing_reason_title'),
                            input: 'textarea',
                            inputAttributes: { maxlength: 1000 },
                            inputValidator: (value) => !value || value.trim().length < 10
                                ? self.$i18n.t('admin_reason_required')
                                : null,
                            showCancelButton: true,
                            confirmButtonText: self.$i18n.t('withdraw_wallet_start_processing'),
                            cancelButtonText: self.$i18n.t('payout_close'),
                          }).then((result) => {
                            if (!result.dismiss) {
                              self.adminUpdateWithdrawal(id, text, 3, '', result.value);
                            }
                          });
                          return false;
                        });

                        $('.complete-withdrawal').on('click', function () {
                          const id = $(this).attr('data-id');
                          const text = $(this).attr('data-text');
                          Swal.fire({
                            title: self.$i18n.t('withdraw_wallet_reference_title'),
                            text: self.$i18n.t('withdraw_wallet_reference_prompt'),
                            input: 'text',
                            inputAttributes: { autocapitalize: 'off' },
                            inputValidator: (value) => !value && self.$i18n.t('withdraw_wallet_reference_required'),
                            showCancelButton: true,
                            confirmButtonText: self.$i18n.t('withdraw_wallet_mark_completed'),
                            cancelButtonText: self.$i18n.t('payout_close'),
                          }).then((result) => {
                            if (!result.dismiss) {
                              self.adminUpdateWithdrawal(id, text, 1, result.value, '');
                            }
                          });
                          return false;
                        });
                    },
                    "ajax": {
                        url: url,
                        type: "POST"
                    },
                    "language": {
                      "url": "/locales/"+ (getCookie('currentLanguage') ? getCookie('currentLanguage') : 'en') +".json"
                    },
                    "createdRow": function (row, data, index) {
                      if(!self.isAdmin) {
                        $('td', row).eq(4).addClass("d-none");
                        $('#hide').hide();
                      }
                    },
                    columns: [
                        {
                            data: 'amount',
                            name: 'amount',
                            render: function(data, type, row) {
                                const fee = Number(row.fee || 0).toFixed(2);
                                const payout = row.payout_amount === null || row.payout_amount === undefined
                                    ? '-'
                                    : Number(row.payout_amount).toFixed(2) + ' ' + (row.payout_currency || 'USD');
                                return '$' + Number(data).toFixed(2) +
                                    '<small class="d-block text-muted">' + self.$i18n.t('withdraw_wallet_fee') + ': $' + fee +
                                    ' · ' + self.$i18n.t('withdraw_wallet_payout') + ': ' + payout + '</small>';
                            }
                        },
                        {
                            data: 'method',
                            name: 'method',
                            orderable: false,
                            searchable: false,
                            render: function(data, type, row) {
                                const methodNames = {
                                    mpesa: 'M-Pesa',
                                    binance_usdt_bep20: 'Binance USDT (BEP20)',
                                };
                                const label = methodNames[data] || (row.withdraw_system && row.withdraw_system.text) || '-';
                                return '<div class="badge badge-primary">' + $('<div>').text(label).html() + '</div>';
                            }
                        },
                        {
                          data: 'address',
                          name: 'address',
                          render: function (data) {
                              return $('<div>').text(data || '-').html();
                          }
                        },
                        {
                            data: 'status',
                            name: 'status',
                            orderable: false,
                            searchable: false,
                            render: function(data, type, row) {
                                let text = '<div class="badge badge-primary">' + self.$i18n.t('withdraw_wallet_pending') + '</div>';
                                if (type === 'display') {
                                    if (data == 1) {
                                        text = '<div class="badge badge-success">' + self.$i18n.t('withdraw_wallet_completed') + '</div>';
                                    } else if (data == 2 || data == 4) {
                                        let message = self.$i18n.t('payout_cause_not_found');
                                        if(row.message){
                                          message = row.message;
                                        }
                                        text = '<div class="badge badge-danger cursor-pointer" data-trigger="hover" data-toggle="popover" data-placement="top" data-container="body" data-original-title="' + self.$i18n.t('payout_cause') + '" data-content="'+$('<div>').text(message).html()+'">' + self.$i18n.t(data == 2 ? 'withdraw_wallet_rejected' : 'withdraw_wallet_failed') + ' <i class="bx bx-help-circle cursor-pointer" style="font-size: 12px;"></i></div>';
                                    } else if (data == 3) {
                                        text = '<div class="badge badge-info">' + self.$i18n.t('withdraw_wallet_processing') + '</div>';
                                    }
                                }
                                return text;
                            }
                        },
                        {
                          data: 'id',
                          name: 'id',
                          orderable: false,
                          searchable: false,
                          render: function(data, type, row) {
                            if (!self.isAdmin || type !== 'display' || ![0, 3].includes(Number(row.status))) {
                                return '';
                            }
                            const safeId = Number(row.id);
                            const safeText = Number(row.system_id) || 0;
                            const decline = '<button type="button" class="btn btn-sm btn-outline-danger mr-1 decline-withdrawal" data-text="'+safeText+'" data-id="'+safeId+'">'+self.$i18n.t('withdraw_wallet_reject')+'</button>';
                            const process = row.status == 0
                                ? '<button type="button" class="btn btn-sm btn-outline-primary mr-1 process-withdrawal" data-text="'+safeText+'" data-id="'+safeId+'">'+self.$i18n.t('withdraw_wallet_start_processing')+'</button>'
                                : '<button type="button" class="btn btn-sm btn-outline-success mr-1 complete-withdrawal" data-text="'+safeText+'" data-id="'+safeId+'">'+self.$i18n.t('withdraw_wallet_mark_completed')+'</button>' +
                                  '<button type="button" class="btn btn-sm btn-outline-warning mr-1 fail-withdrawal" data-text="'+safeText+'" data-id="'+safeId+'">'+self.$i18n.t('withdraw_wallet_mark_failed')+'</button>';
                            return decline + process;
                          }
                        },
                        {
                            data: 'created_at',
                            name: 'created_at',
                            render: function(data, type) {
                                let date = new Date();
                                if (type === 'display') {
                                    date = new Date(data);
                                }
                                return dateformat(date, 'dd-mm-yyyy');
                            }
                        },
                    ]
                });
            }
        },
        mounted() {
            this.getAccountData();
            this.getWithdrawalOptions();
            this.initDatatable();
        },
        // watch:
        computed: {
            account_left_turnover: function (){
              if (!this.account) {
                return '0.00';
              }
              return parseFloat(this.account.left_turnover).toFixed(2) >= 0 ? parseFloat(this.account.left_turnover).toFixed(2) : '0.00';
            },
            isAdmin: function (){
              return this.$route.meta.isAdmin;
            },
            userId: function (){
              return this.$route.params.id;
            },
            amount_formatted: function () {
                let amount = this.$ci.parse(this.amount);
                if(isNaN(amount)){
                    amount = 0;
                }
                return amount;
            },
            minimumAmount: function () {
                return this.options ? Number(this.options.minimum_amount) : 10;
            },
            maximumAmount: function () {
                return this.options ? Number(this.options.maximum_amount) : 100000;
            },
            feeAmount: function () {
                const methodOptions = this.options && this.options.methods
                    ? this.options.methods[this.method]
                    : null;
                if (!methodOptions) {
                    return 0;
                }
                return Math.round((
                    this.amount_formatted * Number(methodOptions.fee_percent || 0) / 100 +
                    Number(methodOptions.fee_fixed || 0)
                ) * 100) / 100;
            },
            payoutAmount: function () {
                const netAmount = Math.max(0, this.amount_formatted - this.feeAmount);
                if (this.method === 'mpesa') {
                    return this.options && this.options.usd_kes_rate
                        ? netAmount * Number(this.options.usd_kes_rate)
                        : 0;
                }
                return netAmount;
            },
            destination: function () {
                return this.method === 'mpesa'
                    ? '+254' + this.phoneNumber
                    : this.walletAddress;
            },
            afterDiscardBonus: function () {
                if(!this.account){
                    return 0;
                }
                let percent = (1 - (this.account.left_turnover / this.account.all_turnover)).toFixed(4);
                let final = (parseFloat(this.account.balance) + (this.account.bonus * percent - this.account.bonus)).toFixed(2);
                return final <= 0 ? 0 : final;
            },
            left_turnover: function () {
                if(!this.account){
                    return 0;
                }
                let percent = ((this.account.left_turnover / this.account.all_turnover)*100).toFixed(2);
                if(isNaN(percent)){
                    return 0;
                }
                return percent;
            },
            discardButtunDisabled: function () {
                if(!this.account){
                    return true;
                }
                return this.account.all_turnover == 0;
            },
            continueButtonDisabled: function () {
                if(!this.account || !this.options || (!this.isAdmin && !this.account.withdrawal_verified)){
                    return true;
                }
                if (this.amount_formatted < this.minimumAmount || this.amount_formatted > this.maximumAmount ||
                    this.amount_formatted > Number(this.account.balance) || this.payoutAmount <= 0) {
                    return true;
                }
                if (this.method === 'mpesa') {
                    return !this.options.usd_kes_rate || !/^(?:7|1)\d{8}$/.test(this.phoneNumber);
                }
                return !/^0x[a-fA-F0-9]{40}$/.test(this.walletAddress);
            }
        }
    }
</script>

<style scoped>
.withdraw-wallet__header {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: space-between;
}

.withdraw-wallet__balance {
    background: rgba(90, 141, 238, 0.1);
    border-radius: 0.6rem;
    display: flex;
    flex-direction: column;
    padding: 0.6rem 1rem;
}

.withdraw-wallet__balance small {
    color: #8a99b5;
}

.withdraw-wallet__balance strong {
    font-size: 1.1rem;
}

.withdraw-wallet__methods {
    margin-bottom: 1rem;
}

.withdraw-wallet__method {
    align-items: center;
    background: transparent;
    border: 1px solid rgba(138, 153, 181, 0.3);
    border-radius: 0.7rem;
    color: inherit;
    cursor: pointer;
    display: flex;
    gap: 0.8rem;
    min-height: 82px;
    padding: 0.85rem;
    text-align: left;
    width: 100%;
}

.withdraw-wallet__method:hover,
.withdraw-wallet__method.is-selected {
    background: rgba(90, 141, 238, 0.08);
    border-color: #5a8dee;
}

.withdraw-wallet__method > span:last-child {
    display: flex;
    flex-direction: column;
}

.withdraw-wallet__method small {
    color: #8a99b5;
    font-size: 0.78rem;
}

.withdraw-wallet__logo {
    align-items: center;
    border-radius: 0.55rem;
    display: inline-flex;
    flex: 0 0 46px;
    height: 46px;
    justify-content: center;
    width: 46px;
}

.withdraw-wallet__logo--mpesa {
    background: rgba(76, 175, 80, 0.12);
    color: #43a047;
    font-size: 0.75rem;
}

.withdraw-wallet__logo--mpesa span {
    color: #43a047;
    font-size: 1.1rem;
}

.withdraw-wallet__logo--mpesa b {
    color: #e53935;
}

.withdraw-wallet__logo--binance {
    background: rgba(240, 185, 11, 0.14);
    color: #f0b90b;
}

.withdraw-wallet__logo--binance svg {
    height: 30px;
    width: 30px;
}

.withdraw-wallet__network-warning {
    border-radius: 0.5rem;
    padding: 1rem;
}

.withdraw-wallet__totals {
    border-top: 1px solid rgba(138, 153, 181, 0.2);
    margin-top: 1rem;
    padding-top: 0.5rem;
}

.withdraw-wallet__totals > div {
    display: flex;
    justify-content: space-between;
    padding: 0.3rem 0;
}

.withdraw-wallet__totals .withdraw-wallet__payout {
    font-size: 1.05rem;
    font-weight: 600;
}

.withdraw-wallet__modal-backdrop {
    align-items: center;
    background: rgba(0, 0, 0, 0.68);
    display: flex;
    inset: 0;
    justify-content: center;
    padding: 1rem;
    position: fixed;
    z-index: 1100;
}

.withdraw-wallet__review {
    max-width: 520px;
    width: 100%;
}

.withdraw-wallet__review dt {
    margin-top: 0.65rem;
}

.withdraw-wallet__review dd {
    margin-bottom: 0.35rem;
}

.withdraw-wallet__destination {
    overflow-wrap: anywhere;
}

@media (max-width: 575.98px) {
    .withdraw-wallet__header {
        align-items: flex-start;
        flex-direction: column;
    }
}

</style>
