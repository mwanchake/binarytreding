<template>
    <div class="content-body">
        <div class="row">
            <div class="col-md-12">
                <section class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{ $i18n.t('deposit_history_title') }}</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <div class="card-text">
                                <div class="table-responsive">
                                    <table class="table" id="historyDeposit" style="width:100%">
                                        <thead>
                                        <tr>
                                            <th>{{ $i18n.t('deposit_usd_amount') }}</th>
                                            <th>{{ $i18n.t('deposit_kes_paid') }}</th>
                                            <th>{{ $i18n.t('deposit_market_rate') }}</th>
                                            <th>{{ $i18n.t('deposit_effective_rate') }}</th>
                                            <th>{{ $i18n.t('deposit_history_status') }}</th>
                                            <th>{{ $i18n.t('deposit_reference') }}</th>
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
</template>

<script>
    import { getCookie } from "../../js/functions";

    require('../../../vendors/js/tables/datatable/datatables.min.js');
    require('../../../vendors/js/tables/datatable/dataTables.bootstrap4.min.js');

    export default {
      name: "DepositHistory",
      mounted() {
        this.updateDatatables();
      },
      computed: {
        isAdmin: function (){
          return this.$route.meta.isAdmin;
        },
        userId: function (){
          return this.$route.params.id;
        },
      },
      methods: {
        updateDatatables: function(){
          $("#historyDeposit").dataTable().fnDestroy();
          let self = this;
          $('#historyDeposit').DataTable({
            "iDisplayLength": 10,
            "processing": true,
            "serverSide": true,
            "order": [],
            "drawCallback": function(settings) {
              $('[data-toggle="popover"]').popover({ html : true });
            },
            "ajax": {
              url: self.isAdmin ? "/data/depositHistory/"+self.userId : "/data/depositHistory",
              type: "POST"
            },
            "language": {
              "url": "/locales/"+ (getCookie('currentLanguage') ? getCookie('currentLanguage') : 'en') +".json"
            },
            columns: [
              {
                orderable: false,
                searchable: false,
                data: null,
                name: 'usd_amount',
                render: function(data) {
                  return parseFloat(data.usd_amount || data.amount).toFixed(2) + ' $';
                }
              },
              {
                orderable: false,
                searchable: false,
                data: 'kes_amount',
                name: 'kes_amount',
                render: function(data) {
                  return data === null ? '-' : 'KES ' + parseFloat(data).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                  });
                }
              },
              {
                orderable: false,
                searchable: false,
                data: null,
                name: 'market_rate',
                render: function(data) {
                  const rate = data.market_rate || data.exchange_rate;
                  if (rate === null || rate === undefined) {
                    return '-';
                  }
                  return '1 USD = ' + parseFloat(rate).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 6
                  }) + ' KES';
                }
              },
              {
                orderable: false,
                searchable: false,
                data: null,
                name: 'effective_rate',
                render: function(data) {
                  const rate = data.effective_rate || data.exchange_rate;
                  if (rate === null || rate === undefined) {
                    return '-';
                  }
                  return '1 USD = ' + parseFloat(rate).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 6
                  }) + ' KES';
                }
              },
              {
                data: 'status',
                name: 'status',
                orderable: false,
                searchable: false,
                render: function(data, type) {
                  let string = '';
                  if (type === 'display') {
                    if(data == 0){
                      string = '<div class="badge badge-primary">' + self.$i18n.t('deposit_history_waiting') + '</div>';
                    } else if(data == 1){
                      string = '<div class="badge badge-success">' + self.$i18n.t('deposit_history_success') + '</div>';
                    } else if(data == 2){
                      string = '<div class="badge badge-danger">' + self.$i18n.t('deposit_history_cancel') + '</div>';
                    }
                  }
                  return string;
                }
              },
              {
                orderable: false,
                searchable: false,
                data: 'reference',
                name: 'reference',
                render: function(data) {
                  return data || '-';
                }
              },
            ]
          });
        }
      }
    }
</script>

<style scoped>

</style>
