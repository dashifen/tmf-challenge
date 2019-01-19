<?php

// this is a real crappy template.  in a production environment, I'd never
// mix my PHP and my HTML like this, but for the purposes of this challenge,
// I didn't want to worry about setting up twig or other templating systems
// that solve that problem.

get_header(); ?>

<div id="the-fool-exchange-vue-root">
    <fool-exchange></fool-exchange>
</div>

<?php get_footer(); ?>
