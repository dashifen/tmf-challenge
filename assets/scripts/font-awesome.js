import Vue from "vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faChevronCircleLeft, faChevronCircleRight } from "@fortawesome/free-solid-svg-icons";

export default {
	initialize() {
		library.add(faChevronCircleLeft, faChevronCircleRight);

		new Vue({
			el: "#tmf-challenge-vue-root",

			components: {
				FontAwesomeIcon
			}
		});
	}
};