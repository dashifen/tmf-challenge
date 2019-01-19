const elixir = require("laravel-elixir");

require("laravel-elixir-webpack-official");
require("laravel-elixir-vue-2");

elixir(function (mix) {
	mix.webpack("./assets/scripts/tmf-challenge.js", "./assets/tmf-challenge.js");
});
