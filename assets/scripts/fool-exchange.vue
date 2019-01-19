<template xmlns="http://www.w3.org/1999/html">
    <section id="the-fool-exchange" aria-labelledby="exchange-title" v-show="loaded">
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
                        <select id="select-symbol" name="symbol" v-model="symbol" @change="resetPage">
                            <option value="all">All Symbols</option>
                            <option v-for="symbol in symbols" :value="symbol">
                                {{ symbol }}
                            </option>
                        </select>
                    </li>
                    <li>
                        <label for="select-date">Select Date:</label>
                        <select id="select-date" name="date" v-model="date" @change="resetPage">
                            <option value="all">All Dates</option>
                            <option v-for="date in dates" :value="date">
                                {{ date | transformDate }}
                            </option>
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
            <tr v-for="row in rowSlice">
                <td headers="symbol">{{ row.ticker }}</td>
                <td headers="usd" class="money">{{ row.price | transformMoney }}</td>
                <td headers="sgd" class="money">{{ row.price * rates[row.date] | transformMoney }}</td>
                <td headers="date">{{ row.date | transformDate }}</td>
                <td headers="time">{{ row.time }}</td>
            </tr>
            </tbody>
        </table>

        <footer>
            <button @click="prevPage" :disabled="page === 0">Previous</button>

            <p v-for="i in (lastPage + 1)" class="page" :class="{ current: i === (page + 1)}">
                <span>{{ i }}</span>
            </p>

            <button @click="nextPage" :disabled="page === lastPage">Next</button>
        </footer>
    </section>
</template>

<script>
    import Axios from "axios";

    const sprintf = require("locutus/php/strings/sprintf");
    const numberFormat = require("locutus/php/strings/number_format");

    /*
     * this is a pretty large component.  in a production environment, i
     * may have split it up into three:  the overall container, the form,
     * and the table.  then, the state could be shared between them via
     * Vuex.  but, that seemed like it was overly complicated for the
     * purposes of this challenge at this time.
     */

    export default {
        name: "fool-exchange",

        data() {
            return {
                page: 0,
                rates: [],
                prices: [],
                loaded: false,
                symbol: 'all',
                date: 'all',
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

                // this an extremely poor man's way of hiding our interface
                // until after we get all of our data.  a savvy observer
                // will notice that the page loads and a small moment
                // in time later, the interface appears.  but, that's better
                // than showing an incomplete interface and then having the
                // whole thing pop into view, in my opinion.  with a larger
                // data set, we could use loading graphics or the like to
                // help the visitor feel more comfortable.

                this.loaded = true;
            });
        },

        computed: {
            dates() {

                // the unique() function is defined below.  it's used here
                // and in the symbols() method, so it seemed best to move it
                // elsewhere to keep things clean here.

                return unique(this.prices.map((price) => {
                    return price.date;
                }));
            },

            symbols() {

                // the unique() function is defined below.  it's used here
                // and in the date() method, so it seemed best to move it
                // elsewhere to keep things clean here.

                return unique(this.prices.map((price) => {
                    return price.ticker;
                }));
            },

            rows() {
                let temp = this.prices;

                if (this.isSymbolFiltered()) {
                    temp = temp.filter((price) => {

                        // to filter based on symbol, we see if the ticker
                        // attached to this price matches the one that the
                        // visitor has chosen in the form.  we know they've
                        // chosen one because we checked that in the if-
                        // condition above.

                        return price.ticker === this.symbol;
                    });
                }

                if (this.isDateFiltered()) {
                    temp = temp.filter((price) => {

                        // like our symbol, this filters based on a chosen
                        // date.  as long as the date for this price matches
                        // that choice, we keep these data after our filter
                        // completes.

                        return price.date === this.date;
                    })
                }

                return temp;
            },

            caption() {

                // we used the Locutus library to grab a JS implementation
                // of the PHP sprintf() function.  with it, we can create our
                // table caption based either on the visitor's selections or
                // a default when they've not made some of them.

                const caption = "%s on %s";
                const symbol = this.isSymbolFiltered() ? this.symbol : "All symbols";
                const date = this.isDateFiltered() ? this.date : "all dates";
                return sprintf(caption, symbol, date);
            },

            lastPage() {

                // to calculate the last page of our display, we find the
                // ceiling of the length of the rows we want to display
                // divided by the number of rows per page.  so, if we have
                // 54 rows, 54/4 = 10.8 and we'll need 11 pages to show
                // everything on-screen.  we subtract one from that
                // calculation because computers count from zero.

                return Math.ceil(this.rows.length / 5) - 1;
            },

            rowSlice() {

                // for our pagination, we need to slice the computed rows
                // property into chunks of five.  we do that using the start-
                // and endSlice computed properties (see below).  at first
                // glance, we could compute rows and the current slice at
                // the same time, but that messes up the calculation for our
                // last page index.  also, i think this keeps us from having
                // to recalculate rows until they're re-filtered.

                return this.rows.slice(this.startSlice, this.endSlice);
            },

            startSlice() {
                return this.page * 5;
            },

            endSlice() {
                return this.startSlice + 5;
            }
        },

        methods: {
            isSymbolFiltered() {
                return this.symbol !== "all";
            },

            isDateFiltered() {
                return this.date !== "all";
            },

            prevPage() {

                // this prevents our page counter from going below zero
                // while also decrementing it on request.

                this.page -= this.page === 0 ? 0 : 1;
            },

            nextPage() {

                // this one is like the prior one except we cannot exceed
                // the computed lastPage property.  otherwise, we increment
                // our page on request.

                this.page += this.page === this.lastPage ? 0 : 1;
            },

            resetPage() {
                this.page = 0;
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

                // we also used the Locutus library to get a JavaScript
                // implementation of the PHP number_format() function.
                // with it, we an format our numbers with both thousands
                // separators and force two digits after the decimal
                // point.

                return numberFormat(number, 2);
            }
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
     * or at least not be overriding default browser styles.  best case is a
     * style guide that allows me to re-use the styles from the rest of the
     * site.  but, since this is just an example, hopefully y'all won't hate
     * me for not making it look super pretty!
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
        width: 100%;
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

    footer {
        display: flex;
        justify-content: space-around;
        margin-bottom: 2em;
    }

    footer button {
        width: 7em;
    }

    footer p {
        align-self: center;
        margin: 0;
        text-align: center;
        width: 3.5em;
    }

    footer p.current span {
        background-color: black;
        border: 1px solid black;
        border-radius: 4em;
        color: white;
        display: inline-block;
        padding: .2em;
        width: 2em;
    }
</style>
