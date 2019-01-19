<template xmlns="http://www.w3.org/1999/html">
    <section id="the-fool-exchange" aria-labelledby="exchange-title">
        <header>
            <h1 id="exchange-title">The Fool Exchange</h1>
        </header>

        <form>
            <fieldset>
                <legend>
                    <h2>Select Criteria:</h2>
                </legend>

                <ol>
                    <li>
                        <label for="select-symbol">Ticker Symbol:</label>
                        <select id="select-symbol" name="symbol" v-model="symbol">
                            <option value="all">All Symbols</option>
                            <option v-for="symbol in symbols" :value="symbol">{{ symbol }}</option>
                        </select>
                    </li>
                    <li>
                        <label for="select-date">Select Date:</label>
                        <select id="select-date" name="date" v-model="date">
                            <option value="all">All Dates</option>
                            <option v-for="date in dates" :value="date">{{ date | transformDate }}</option>
                        </select>
                    </li>
                </ol>
            </fieldset>
        </form>

        <table>
            <caption>
                {{ caption }}
            </caption>

            <thead>
            <tr>
                <th scope="col" id="symbol">Ticker Symbol</th>
                <th scope="col" id="usd" class="money">USD</th>
                <th scope="col" id="sgd" class="money">SGD</th>
                <th scope="col" id="date">Date</th>
                <th scope="col" id="time">Time</th>
            </tr>
            </thead>

            <tbody>
                <tr v-for="row in rows">
                    <td headers="symbol">{{ row.ticker }}</td>
                    <td headers="usd" class="money">{{ row.price | transformMoney }}</td>
                    <td headers="sgd" class="money">{{ row.price * rates[row.date] | transformMoney }}</td>
                    <td headers="date">{{ row.date | transformDate }}</td>
                    <td headers="time">{{ row.time }}</td>
                </tr>
            </tbody>
        </table>
    </section>
</template>

<script>
    import Axios from "axios";

    const numberFormat = require("locutus/php/strings/number_format");
    const sprintf = require("locutus/php/strings/sprintf");

    export default {
        name: "fool-exchange",

        data() {
            return {
                rates: [],
                prices: [],
                symbol: 'all',
                date: 'all',
            }
        },

        computed: {
            dates() {

                // the unique() function is defined below.  it's used here
                // and in the symbols() method so it seemed best to move it
                // elsewhere to keep things clean.

                return unique(this.prices.map((price) => {
                    return price.date;
                }));
            },

            symbols() {
                return unique(this.prices.map((price) => {
                    return price.ticker;
                }));
            },

            rows() {
                if (!this.isFiltered()) {
                    return this.prices;
                }

                let temp = this.prices;
                if (this.isSymbolFiltered()) {
                    temp = temp.filter((price) => {
                        return price.ticker === this.symbol;
                    });
                }

                if (this.isDateFiltered()) {
                    temp = temp.filter((price) => {
                        return price.date === this.date;
                    })
                }

                return temp;
            },

            caption() {
                const caption = "%s on %s";
                const symbol = this.isSymbolFiltered() ? this.symbol : "All symbols";
                const date = this.isDateFiltered() ? this.date : "all dates";
                return sprintf(caption, symbol, date);
            }
        },

        methods: {
            isFiltered() {
                return this.isSymbolFiltered() || this.isDateFiltered();
            },

            isSymbolFiltered() {
                return this.symbol !== "all";
            },

            isDateFiltered() {
                return this.date !== "all";
            }
        },

        filters: {
            transformDate(date) {

                // dates come to us here as YYYYMMDD values.  we want to
                // return YYYY-MM-DD to make it more readable on-screen.

                return date.substr(0, 4)
                    + "-" + date.substr(4, 2)
                    + "-" + date.substr(6);
            },

            transformMoney(number) {
                return numberFormat(number,2);
            }
        },

        mounted() {

            // when our component mounts, we want to get the information
            // to display in our exchange.  in a production environment,
            // we might have accessed query variables from WordPress to
            // limit things, or we might force the visitor to make
            // selections so that we don't fetch too much here.  for this
            // example, limitations of this sort are out of scope.

            Axios.get(tmfAjax.url, {
                params: {action: "fetch-exchange"}
            }).then((response) => {

                // when we get back from the server, we can save the
                // information it sends us in our data properties. we
                // probably should try to catch errors, check data
                // integrity, etc., but like query limitations, these
                // would be out of scope for the moment.

                this.prices = response.data.prices;
                this.rates = response.data.rates;
            });
        }
    }

    function unique(values) {

        // this filters out repeated values in our parameter.  we could
        // use the ES6 Set object to do that, but IE11 doesn't like to
        // initialize Sets with iterable data.  if we were adding polyfills
        // and all that sort of stuff, then we could have gone that route,
        // but otherwise, this is likely a little more widely supported.

        return values.filter((value, i, self) => {
            return self.indexOf(value) === i;
        });
    }
</script>

<style scoped>
    /*
     * these styles are awful.  ordinarily, I'd focus on re-usable classes
     * or at least not be overriding default browser styles.  luckily, these
     * are scoped to this component, so that's helpful in mitigating any
     * side-effects.  also, since this is just an example, hopefully y'all
     * won't hate me for not making it look super pretty!
     */

    fieldset {
        border-style: none;
        margin-bottom: 2em;
        padding: 0;
    }

    ol,
    legend h2 {
        margin: 0;
        padding: 0;
    }

    ol {
        list-style-type: none;
    }

    li {
        margin-bottom: .25em;
    }

    label {
        display: inline-block;
        width: 7em;
    }

    select {
        font-size: inherit;
    }

    table {
        border: 1px solid black;
        border-collapse: collapse;
        margin-bottom: 2em;
    }

    caption {
        font-weight: bold;
    }

    td, th, caption {
        padding: .5rem .75em;
        text-align: left;
    }

    th:not(:last-child),
    td:not(:last-child) {
        border-right: 1px solid darkgray;
    }

    th,
    tr:not(:last-child) td {
        border-bottom: 1px solid darkgray;
    }
    
    .money {
        text-align: right;
    }
</style>
