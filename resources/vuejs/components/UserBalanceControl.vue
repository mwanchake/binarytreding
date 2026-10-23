<template>
  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <h4 class="card-title">{{ $i18n.t('admin_control_balance') }}</h4>
        </div>
        <div class="card-content">
          <div class="card-body">
            <div class="row">
              <div class="col-md-5">
                <fieldset class="form-group">
                  <label>{{ $i18n.t('admin_control_amount') }}</label>
                  <input type="number" min="0.0001" step="0.0001" class="form-control" v-model="amount">
                  <small class="text-muted">{{ $i18n.t('superadmin_current_balance') }}: ${{ Number(info.balance || 0).toFixed(2) }}</small>
                </fieldset>
              </div>
              <div class="col-md-5">
                <fieldset class="form-group">
                  <label class="align-top">{{ $i18n.t('admin_control_action') }}</label>
                  <select2 v-model="balanceAction" :options="balanceActions" :settings="{ settingOption: 'value', settingOption: 'value', minimumResultsForSearch: Infinity }"/>
                </fieldset>
              </div>
              <div class="col-md-2">
                <label class="align-top">&nbsp;</label>
                <button  @click="balanceClick" type="button" class="btn btn-outline-primary w-100">{{ $i18n.t('admin_control_execute') }}</button>
              </div>
              <div class="col-md-12">
                <fieldset class="form-group">
                  <label>{{ $i18n.t('admin_action_reason') }}</label>
                  <input type="text" maxlength="1000" class="form-control" v-model.trim="reason">
                </fieldset>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "UserBalanceControl",
  props: ['save_url', 'load_url'],
  mounted: function() {
    this.loadUserInfo();
  },
  data: function (){
    return {
      urlSave: this.save_url,
      urlLoad: this.load_url,
      amount: 0,
      balanceAction: 0,
      reason: '',
      balanceActions: [
        { id: "0", text: this.$i18n.t('admin_control_add_to_balance') },
        { id: "1", text: this.$i18n.t('admin_control_sub_from_balance') },
      ],
      info: {},
    }
  },
  methods: {
    balanceClick: function (){
      let self = this;
      if (!self.reason || self.reason.length < 10 || !window.confirm(self.$i18n.t('admin_confirm_action'))) {
        return;
      }
      axios.post(self.urlSave, {
        balance: self.amount,
        action: self.balanceAction,
        reason: self.reason,
        reference: self.makeReference(),
        confirmation: true,
      })
        .then(function (response) {
          self.loadUserInfo();
          self.reason = '';
          if(response.data.success === true) {
            toastr.success(response.data.message, self.$i18n.t('profile_success'), {
              positionClass: 'toast-bottom-left',
              containerId: 'toast-bottom-left'
            });
          } else {
            toastr.error(response.data.message, self.$i18n.t('profile_error'), {
              positionClass: 'toast-bottom-left',
              containerId: 'toast-bottom-left'
            });
          }
        });
    },
    loadUserInfo: function (){
      let self = this;
      axios.post(self.urlLoad)
        .then(function (response) {
          self.info.balance = response.data.balance;
        });
    },
    makeReference: function () {
      if (window.crypto && window.crypto.randomUUID) {
        return window.crypto.randomUUID();
      }
      return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
        const random = Math.floor(Math.random() * 16);
        return (character === 'x' ? random : (random & 3 | 8)).toString(16);
      });
    },
  }

}
</script>

<style scoped>

</style>