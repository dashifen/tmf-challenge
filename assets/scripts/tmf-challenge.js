import Vue from "vue";
import FoolExchange from "./fool-exchange.vue";

Vue.config.productionTip = false;

document.addEventListener("DOMContentLoaded", () => {
    new Vue({
        el: "#the-fool-exchange-vue-root",
        components: {FoolExchange}
    });
});
