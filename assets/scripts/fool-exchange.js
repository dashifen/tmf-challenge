import Vue from "vue";
import FoolExchange from "./fool-exchange.vue";

Vue.config.productionTip = false;

export default {
    initialize() {
        new Vue({
            el: "#the-fool-exchange-vue-root",
            components: { FoolExchange }
        });
    }
};
