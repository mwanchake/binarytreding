<template>
    <div class="content-wrapper">
        <div class="content-body deposit-wallet">
            <div v-for="(value, index) in errors" :key="'error-' + index" class="alert bg-rgba-danger alert-dismissible mb-2" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close" @click="errors.splice(index, 1)">
                    <span aria-hidden="true">&times;</span>
                </button>
                <div class="d-flex align-items-center">
                    <i class="bx bx-error"></i>
                    <span>{{ value }}</span>
                </div>
            </div>

            <div v-if="pendingDeposit" class="alert mb-2" :class="paymentAlertClass" role="status" aria-live="polite">
                <div class="d-flex align-items-center mb-1">
                    <span v-if="paymentStatus === 'waiting'" class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>
                    <i v-else-if="paymentStatus === 'completed'" class="bx bx-check-circle mr-1" aria-hidden="true"></i>
                    <i v-else class="bx bx-error-circle mr-1" aria-hidden="true"></i>
                    <strong>{{ $i18n.t(paymentMessageKey, paymentMessageParams) }}</strong>
                </div>
                <p v-if="paymentStatus === 'waiting'" class="mb-1">{{ pendingDeposit.message }}</p>
                <p v-if="paymentStatus === 'waiting'" class="mb-1">{{ $i18n.t('deposit_payhero_waiting_countdown', { seconds: paymentRemainingSeconds }) }}</p>
                <p v-if="paymentStatus === 'timed_out'" class="mb-1">{{ $i18n.t('deposit_payhero_timeout_note') }}</p>
                <p v-if="paymentCheckError && paymentStatus === 'waiting'" class="mb-1">{{ $i18n.t('deposit_payhero_status_check_error') }}</p>
                <p>{{ $i18n.t('deposit_usd_amount') }}: ${{ pendingDeposit.usd_amount }}</p>
                <p>{{ $i18n.t('deposit_current_rate') }}: {{ Number(pendingDeposit.exchange_rate).toFixed(2) }} KES/USD</p>
                <p>{{ $i18n.t('deposit_kes_paid') }}: KES {{ Number(pendingDeposit.kes_amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                <p>{{ $i18n.t('deposit_reference') }}: {{ pendingDeposit.reference }}</p>
            </div>

            <section class="card deposit-wallet__main">
                <div class="card-header deposit-wallet__header">
                    <div>
                        <h4 class="card-title mb-50">{{ $i18n.t('deposit_title') }}</h4>
                        <p class="text-muted mb-0">{{ $i18n.t('deposit_wallet_subtitle') }}</p>
                    </div>
                    <div v-if="!isAdmin" class="deposit-wallet__balance">
                        <span>{{ $i18n.t('deposit_wallet_balance') }}</span>
                        <strong>${{ walletBalance }}</strong>
                    </div>
                </div>

                <div class="card-body">
                    <div v-if="!isAdmin" class="row deposit-wallet__methods" role="tablist" :aria-label="$i18n.t('deposit_wallet_methods')">
                        <div class="col-md-6 mb-1">
                            <button type="button" class="deposit-wallet__method" :class="{ 'is-selected': selectedMethod === 'mpesa' }"
                                    role="tab" :aria-selected="selectedMethod === 'mpesa'" @click="selectedMethod = 'mpesa'">
                                <span class="deposit-wallet__logo deposit-wallet__logo--mpesa" aria-hidden="true">
                                    <svg viewBox="0 0 48 48" focusable="false">
                                        <path d="M24 4a20 20 0 1 0 20 20A20 20 0 0 0 24 4Zm0 6a14 14 0 1 1-14 14 14 14 0 0 1 14-14Zm-1 5v9l7 4 2-3-5-3v-7Z" fill="currentColor"/>
                                    </svg>
                                </span>
                                <span class="deposit-wallet__method-copy">
                                    <strong>M-PESA</strong>
                                    <small>{{ $i18n.t('deposit_wallet_mpesa_description') }}</small>
                                </span>
                                <i class="bx bx-chevron-right" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="col-md-6 mb-1">
                            <button type="button" class="deposit-wallet__method" :class="{ 'is-selected': selectedMethod === 'binance' }"
                                    role="tab" :aria-selected="selectedMethod === 'binance'" @click="selectedMethod = 'binance'">
                                <span class="deposit-wallet__logo deposit-wallet__logo--binance" aria-hidden="true">
                                    <svg viewBox="0 0 48 48" focusable="false">
                                        <path d="m24 3 6 6-6 6-6-6 6-6Zm-12 12 6 6-6 6-6-6 6-6Zm24 0 6 6-6 6-6-6 6-6ZM24 21l6 6-6 6-6-6 6-6Zm-12 12 6 6-6 6-6-6 6-6Zm24 0 6 6-6 6-6-6 6-6ZM24 33l6 6-6 6-6-6 6-6Z" fill="currentColor"/>
                                    </svg>
                                </span>
                                <span class="deposit-wallet__method-copy">
                                    <strong>Binance <span>USDT</span></strong>
                                    <small>{{ $i18n.t('deposit_wallet_binance_description') }}</small>
                                </span>
                                <i class="bx bx-chevron-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div v-show="selectedMethod === 'mpesa' || isAdmin" class="deposit-wallet__panel" role="tabpanel">
                        <p class="text-muted">{{ $i18n.t('deposit_payhero_info') }}</p>
                        <div class="row">
                            <div class="col-md-6">
                                <fieldset class="form-group">
                                    <label for="depositAmount">{{ $i18n.t('deposit_deposit_amount') }} (USD)</label>
                                    <small class="text-muted d-block">{{ $i18n.t('deposit_minimum_hint') }}</small>
                                    <input id="depositAmount" ref="ci" v-model="amount" type="text" class="form-control"
                                           v-currency="{currency: null, autoDecimalMode: true, valueRange: {min: 0.01, max: 10000}}">
                                </fieldset>
                                <div class="row mb-1 mr-0 ml-0">
                                    <div v-for="preset in presets" :key="preset" class="col-3 px-1">
                                        <button type="button" class="btn w-100 btn-sm btn-outline-dark" @click="setAmount(preset)">
                                            ${{ preset }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <fieldset class="form-group">
                                    <label for="payheroPhone">{{ $i18n.t('deposit_mpesa_phone') }}</label>
                                    <input id="payheroPhone" v-model.trim="phoneNumber" type="tel" inputmode="tel"
                                           maxlength="13" class="form-control" placeholder="07XXXXXXXX or 2547XXXXXXXX"
                                           autocomplete="tel">
                                    <small class="form-text text-muted">{{ $i18n.t('deposit_mpesa_phone_format') }}</small>
                                </fieldset>
                                <button type="button" class="btn btn-primary" :disabled="!depositLoaded || isAdmin || paymentStatus === 'waiting'" @click="createDeposit">
                                    <span v-if="!depositLoaded" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                    {{ depositLoaded ? $i18n.t('deposit_payhero_submit') : $i18n.t('deposit_processing') }}
                                </button>
                                <div v-if="paymentStatus === 'waiting'" class="deposit-wallet__confirmation mt-1" role="status" aria-live="polite">
                                    <span class="spinner-border text-primary deposit-wallet__confirmation-spinner" aria-hidden="true"></span>
                                    <div>
                                        <strong>{{ $i18n.t('deposit_payhero_waiting_confirmation') }}</strong>
                                        <p class="mb-0">{{ $i18n.t('deposit_payhero_waiting_countdown', { seconds: paymentRemainingSeconds }) }}</p>
                                    </div>
                                </div>
                                <div v-else-if="paymentStatus === 'timed_out' || paymentStatus === 'failed' || paymentStatus === 'completed'"
                                     class="deposit-wallet__confirmation mt-1"
                                     :class="'deposit-wallet__confirmation--' + paymentStatus"
                                     role="status" aria-live="polite">
                                    <i v-if="paymentStatus === 'completed'" class="bx bx-check-circle" aria-hidden="true"></i>
                                    <i v-else class="bx bx-error-circle" aria-hidden="true"></i>
                                    <strong>{{ $i18n.t(paymentMessageKey) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedMethod === 'binance' && !isAdmin" class="deposit-wallet__panel" role="tabpanel">
                        <div class="deposit-wallet__network">
                            <div>
                                <span>{{ $i18n.t('deposit_wallet_coin') }}</span>
                                <strong>USDT</strong>
                            </div>
                            <div>
                                <span>{{ $i18n.t('deposit_wallet_network') }}</span>
                                <strong>BNB Smart Chain (BEP20) ONLY</strong>
                            </div>
                        </div>

                        <div class="alert alert-danger deposit-wallet__warning" role="alert">
                            <i class="bx bx-error-circle" aria-hidden="true"></i>
                            <span>{{ $i18n.t('deposit_wallet_network_warning') }}</span>
                        </div>

                        <div class="row align-items-center">
                            <div class="col-lg-8">
                                <label for="binanceDepositAddress">{{ $i18n.t('deposit_wallet_address') }}</label>
                                <div class="input-group deposit-wallet__address">
                                    <input id="binanceDepositAddress" type="text" class="form-control" :value="binanceAddress" readonly
                                           @focus="$event.target.select()">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-primary" @click="copyAddress">
                                            <i class="bx bx-copy-alt" aria-hidden="true"></i>
                                            {{ $i18n.t('deposit_wallet_copy') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 text-center mt-2 mt-lg-0">
                                <img v-if="qrImage" class="deposit-wallet__qr" :src="qrImage" :alt="$i18n.t('deposit_wallet_qr_alt')">
                                <p v-else-if="qrError" class="text-danger">{{ $i18n.t('deposit_wallet_qr_error') }}</p>
                                <p v-else class="text-muted">{{ $i18n.t('deposit_wallet_qr_loading') }}</p>
                                <small class="text-muted d-block">USDT · BEP20</small>
                            </div>
                        </div>

                        <p class="deposit-wallet__credit-note text-muted small mt-2 mb-0" role="status">
                            {{ $i18n.t('deposit_wallet_credit_notice') }}
                        </p>
                    </div>
                </div>
            </section>

            <p class="deposit-wallet__history-note text-muted">{{ $i18n.t('deposit_wallet_history_note') }}</p>
            <deposit-history ref="datatables"></deposit-history>
        </div>
    </div>
</template>

<script>
    import { CurrencyDirective, setValue } from 'vue-currency-input'
    import QRCode from 'qrcode'

    const BINANCE_DEPOSIT_ADDRESS = '0x3500fe38586876c5300ec21e00a9e087055b3571'

    export default {
        name: 'Deposit',
        directives: {
            currency: CurrencyDirective
        },
        data: function () {
            return {
                depositLoaded: true,
                amount: '0.01',
                phoneNumber: '',
                errors: [],
                pendingDeposit: null,
                paymentStatus: null,
                paymentRemainingSeconds: 120,
                paymentCheckError: false,
                paymentPollTimer: null,
                paymentStatusCheckInProgress: false,
                paymentStatusCheckReference: null,
                presets: [10, 20, 50, 100],
                selectedMethod: 'mpesa',
                binanceAddress: BINANCE_DEPOSIT_ADDRESS,
                qrImage: '',
                qrError: false,
                walletBalance: Number(window.user_data && window.user_data.balance || 0).toFixed(2),
            }
        },
        computed: {
            isAdmin: function () {
                return this.$route.meta.isAdmin;
            },
            numericAmount: function () {
                return this.$ci.parse(this.amount);
            },
            paymentMessageKey: function () {
                if (this.paymentStatus === 'completed') return 'deposit_payhero_confirmed';
                if (this.paymentStatus === 'failed') return 'deposit_payhero_payment_failed';
                if (this.paymentStatus === 'timed_out') return 'deposit_payhero_timed_out';
                return 'deposit_payhero_waiting_confirmation';
            },
            paymentMessageParams: function () {
                return { seconds: 120 };
            },
            paymentAlertClass: function () {
                if (this.paymentStatus === 'completed') return 'alert-success';
                if (this.paymentStatus === 'failed' || this.paymentStatus === 'timed_out') return 'alert-danger';
                return 'alert-info';
            },
        },
        beforeDestroy: function () {
            this.clearPaymentPollTimer();
        },
        mounted: function () {
            QRCode.toDataURL(this.binanceAddress, { width: 192, margin: 1, errorCorrectionLevel: 'M' })
                .then((image) => {
                    this.qrImage = image;
                })
                .catch((error) => {
                    this.qrError = true;
                    console.error('Failed to generate the Binance deposit address QR code.', error);
                });
        },
        methods: {
            setAmount: function (amount) {
                setValue(this.$refs.ci, amount.toFixed(2));
            },
            copyAddress: function () {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(this.binanceAddress).then(() => {
                        toastr.success(this.$i18n.t('deposit_wallet_copy_success'), this.$i18n.t('deposit_title'));
                    }).catch((error) => {
                        console.error('Could not copy the Binance deposit address.', error);
                        toastr.error(this.$i18n.t('deposit_wallet_copy_error'), this.$i18n.t('deposit_title'));
                    });
                    return;
                }

                const addressInput = document.getElementById('binanceDepositAddress');
                addressInput.focus();
                addressInput.select();
                if (document.execCommand('copy')) {
                    toastr.success(this.$i18n.t('deposit_wallet_copy_success'), this.$i18n.t('deposit_title'));
                } else {
                    toastr.error(this.$i18n.t('deposit_wallet_copy_error'), this.$i18n.t('deposit_title'));
                }
            },
            clearPaymentPollTimer: function () {
                if (this.paymentPollTimer) {
                    clearInterval(this.paymentPollTimer);
                    this.paymentPollTimer = null;
                }
            },
            startPaymentConfirmation: function (reference) {
                this.clearPaymentPollTimer();
                this.paymentStatus = 'waiting';
                this.paymentRemainingSeconds = 120;
                this.paymentCheckError = false;
                this.paymentStatusCheckInProgress = false;
                this.paymentStatusCheckReference = reference;
                this.checkPaymentStatus(reference);
                this.paymentPollTimer = setInterval(() => {
                    this.paymentRemainingSeconds -= 1;
                    if (this.paymentRemainingSeconds <= 0) {
                        this.paymentRemainingSeconds = 0;
                        this.paymentStatus = 'timed_out';
                        this.clearPaymentPollTimer();
                        this.$nextTick(() => this.$refs.datatables.updateDatatables());
                        return;
                    }

                    if (this.paymentRemainingSeconds % 3 === 0) {
                        this.checkPaymentStatus(reference);
                    }
                }, 1000);
            },
            checkPaymentStatus: function (reference) {
                if (this.paymentStatusCheckInProgress || this.paymentStatus !== 'waiting'
                    || !this.pendingDeposit || this.pendingDeposit.reference !== reference) return;
                this.paymentStatusCheckInProgress = true;
                this.paymentStatusCheckReference = reference;
                axios.get('/data/payhero/deposit/' + encodeURIComponent(reference) + '/status')
                    .then((response) => {
                        if (!this.pendingDeposit || this.pendingDeposit.reference !== reference) return;
                        this.paymentCheckError = false;
                        if (Number(response.data.status) === 1) {
                            this.paymentStatus = 'completed';
                            this.clearPaymentPollTimer();
                            this.$nextTick(() => this.$refs.datatables.updateDatatables());
                        } else if (Number(response.data.status) === 2) {
                            this.paymentStatus = 'failed';
                            this.clearPaymentPollTimer();
                            this.$nextTick(() => this.$refs.datatables.updateDatatables());
                        }
                    })
                    .catch((error) => {
                        if (!this.pendingDeposit || this.pendingDeposit.reference !== reference) return;
                        this.paymentCheckError = true;
                        console.error('Could not check the M-Pesa deposit status.', error);
                    })
                    .finally(() => {
                        if (this.paymentStatusCheckReference === reference) {
                            this.paymentStatusCheckInProgress = false;
                        }
                    });
            },
            createDeposit: function () {
                this.clearPaymentPollTimer();
                this.errors = [];
                this.pendingDeposit = null;
                this.paymentStatus = null;
                this.paymentCheckError = false;

                const amount = Number(this.numericAmount);
                const phoneNumber = this.phoneNumber.replace(/[\s-]/g, '');
                if (!Number.isFinite(amount) || amount < 0.01 || amount > 10000) {
                    this.errors.push(this.$i18n.t('deposit_payhero_invalid_amount'));
                    return;
                }
                if (!/^(?:\+?254|0)(?:7|1)\d{8}$/.test(phoneNumber)) {
                    this.errors.push(this.$i18n.t('deposit_mpesa_phone_invalid'));
                    return;
                }

                this.depositLoaded = false;
                axios.post('/data/payhero/deposit', {
                    amount: amount,
                    phone_number: phoneNumber,
                }).then((response) => {
                    if (response.data.success !== true) {
                        this.errors.push(response.data.message || this.$i18n.t('deposit_payhero_failed'));
                        return;
                    }

                    this.pendingDeposit = response.data;
                    this.startPaymentConfirmation(response.data.reference);
                    this.$nextTick(() => this.$refs.datatables.updateDatatables());
                    toastr.info(this.$i18n.t('deposit_payhero_sent'), this.$i18n.t('deposit_title'), {
                        positionClass: 'toast-bottom-left',
                        containerId: 'toast-bottom-left'
                    });
                }).catch((error) => {
                    const message = error.response && error.response.data && error.response.data.message
                        ? error.response.data.message
                        : this.$i18n.t('deposit_payhero_failed');
                    this.errors.push(message);
                }).finally(() => {
                    this.depositLoaded = true;
                });
            }
        },
    }
</script>

<style scoped>
.deposit-wallet__header {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 1rem;
}

.deposit-wallet__balance {
    background: rgba(90, 141, 238, 0.1);
    border-radius: 0.6rem;
    display: flex;
    flex-direction: column;
    padding: 0.65rem 1rem;
}

.deposit-wallet__balance span,
.deposit-wallet__network span {
    color: #8a99b5;
    font-size: 0.8rem;
}

.deposit-wallet__balance strong {
    font-size: 1.1rem;
}

.deposit-wallet__methods {
    margin-bottom: 1.25rem;
}

.deposit-wallet__method {
    align-items: center;
    background: transparent;
    border: 1px solid rgba(138, 153, 181, 0.3);
    border-radius: 0.7rem;
    color: inherit;
    cursor: pointer;
    display: flex;
    min-height: 88px;
    padding: 1rem;
    text-align: left;
    transition: border-color 150ms ease, background-color 150ms ease;
    width: 100%;
}

.deposit-wallet__method:hover,
.deposit-wallet__method.is-selected {
    background: rgba(90, 141, 238, 0.08);
    border-color: #5a8dee;
}

.deposit-wallet__logo {
    align-items: center;
    border-radius: 0.6rem;
    display: inline-flex;
    flex: 0 0 48px;
    height: 48px;
    justify-content: center;
    margin-right: 0.9rem;
    width: 48px;
}

.deposit-wallet__logo svg {
    height: 32px;
    width: 32px;
}

.deposit-wallet__logo--mpesa {
    background: rgba(76, 175, 80, 0.12);
    color: #4caf50;
}

.deposit-wallet__logo--binance {
    background: rgba(240, 185, 11, 0.14);
    color: #f0b90b;
}

.deposit-wallet__method-copy {
    display: flex;
    flex: 1;
    flex-direction: column;
}

.deposit-wallet__method-copy strong {
    font-size: 1rem;
}

.deposit-wallet__method-copy strong span {
    color: #f0b90b;
    font-size: 0.8rem;
    margin-left: 0.25rem;
}

.deposit-wallet__method-copy small {
    color: #8a99b5;
    margin-top: 0.2rem;
}

.deposit-wallet__network {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem 3rem;
    margin-bottom: 1.2rem;
}

.deposit-wallet__network > div {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.deposit-wallet__warning {
    align-items: flex-start;
    display: flex;
    gap: 0.65rem;
}

.deposit-wallet__warning i {
    font-size: 1.25rem;
    flex: 0 0 auto;
}

.deposit-wallet__address input {
    min-width: 0;
}

.deposit-wallet__qr {
    background: white;
    border-radius: 0.4rem;
    height: 192px;
    max-width: 100%;
    padding: 0.4rem;
    width: 192px;
}

.deposit-wallet__confirmation {
    align-items: center;
    background: rgba(90, 141, 238, 0.1);
    border: 1px solid rgba(90, 141, 238, 0.35);
    border-radius: 0.4rem;
    color: #5a8dee;
    display: flex;
    gap: 0.8rem;
    padding: 0.8rem;
}

.deposit-wallet__confirmation-spinner {
    border-width: 0.25rem;
    flex: 0 0 2rem;
    height: 2rem;
    width: 2rem;
}

.deposit-wallet__confirmation p {
    color: inherit;
    margin-top: 0.2rem;
}

.deposit-wallet__confirmation--completed {
    background: rgba(40, 199, 111, 0.1);
    border-color: rgba(40, 199, 111, 0.4);
    color: #28c76f;
}

.deposit-wallet__confirmation--failed,
.deposit-wallet__confirmation--timed_out {
    background: rgba(234, 84, 85, 0.1);
    border-color: rgba(234, 84, 85, 0.4);
    color: #ea5455;
}

@media (max-width: 575.98px) {
    .deposit-wallet__header {
        align-items: flex-start;
        flex-direction: column;
    }

    .deposit-wallet__address .input-group-append button {
        padding-left: 0.65rem;
        padding-right: 0.65rem;
    }
}
</style>
