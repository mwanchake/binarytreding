<template>
    <div>
      <Trading v-show="showTradingPanel"></Trading>
      <router-view></router-view>
    </div>
</template>

<script>
    import Trading from "../user/Trading";
    export default {
        name: "App",
        components : {
          Trading
        },
        data(){
          return {
            showTradingPanel: window.location.pathname.startsWith('/trading'),
          };
        },
        watch: {
          $route(to) {
            this.showTradingPanel = to.path.startsWith('/trading');
          }
        }
    }
</script>

<style scoped>

</style>