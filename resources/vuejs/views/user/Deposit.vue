<template>
    <div class="content-wrapper">
        <div class="content-body">
            <div v-for="(value, index) in errors" :key="'error-' + index" class="alert bg-rgba-danger alert-dismissible mb-2" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close" @click="errors.splice(index, 1)">
                    <span aria-hidden="true">&times;</span>
                </button>
                <div class="d-flex align-items-center">
                    <i class="bx bx-error"></i>
                    <span>{{ value }}</span>
                </div>
            </div>

            <div v-if="pendingDeposit" class="alert alert-info" role="status">
                <p>{{ pendingDeposit.message }}</p>
                <p>{{ $i18n.t('deposit_usd_amount') }}: ${{ pendingDeposit.usd_amount }}</p>
                <p>{{ $i18n.t('deposit_current_rate') }}: {{ Number(pendingDeposit.exchange_rate).toFixed(2) }} KES/USD</p>
                <p>{{ $i18n.t('deposit_kes_paid') }}: KES {{ Number(pendingDeposit.kes_amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                <p>{{ $i18n.t('deposit_reference') }}: {{ pendingDeposit.reference }}</p>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <section class="card">
                        <div class="card-header">
                            <h4 class="card-title">{{ $i18n.t('deposit_title') }}</h4>
                        </div>
                        <div class="card-content">
                            <div class="card-body">
                                <p class="text-muted">{{ $i18n.t('deposit_payhero_info') }}</p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <fieldset class="form-group">
                                            <label for="depositAmount">{{ $i18n.t('deposit_deposit_amount') }} (USD)</label>
                                            <small class="text-muted d-block">{{ $i18n.t('deposit_min') }}. $5</small>
                                            <input id="depositAmount" ref="ci" v-model="amount" type="text" class="form-control"
                                                   v-currency="{currency: null, autoDecimalMode: true, valueRange: {min: 5, max: 10000}}">
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
                                        <button type="button" class="btn btn-primary" :disabled="!depositLoaded || isAdmin" @click="createDeposit">
                                            <span v-if="!depositLoaded" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            {{ depositLoaded ? $i18n.t('deposit_payhero_submit') : $i18n.t('deposit_processing') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
            <deposit-history ref="datatables"></deposit-history>
        </div>
    </div>
</template>

<script>
    import { CurrencyDirective, setValue } from 'vue-currency-input'

    export default {
        name: 'Deposit',
        directives: {
            currency: CurrencyDirective
        },
        data: function () {
            return {
                depositLoaded: true,
                amount: '10',
                phoneNumber: '',
                errors: [],
                pendingDeposit: null,
                presets: [10, 20, 50, 100],
            }
        },
        computed: {
            isAdmin: function () {
                return this.$route.meta.isAdmin;
            },
            numericAmount: function () {
                return this.$ci.parse(this.amount);
            },
        },
        methods: {
            setAmount: function (amount) {
                setValue(this.$refs.ci, amount.toFixed(2));
            },
            createDeposit: function () {
                this.errors = [];
                this.pendingDeposit = null;

                const amount = Number(this.numericAmount);
                const phoneNumber = this.phoneNumber.replace(/[\s-]/g, '');
                if (!Number.isFinite(amount) || amount < 5 || amount > 10000) {
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
