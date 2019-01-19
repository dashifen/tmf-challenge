<?php
/**
 * Plugin Name: The Motley Fool Challenge
 * Description: My response to a programming challenge provided by the Motley Fool.
 * Plugin URL:  https://github.com/dashifen/tmf-challenge
 * Author URL:  https://dashifen.com
 * Author:      David Dashifen Kees
 * Version:     1.0.0
 */

// if this file is called directly, abort.

if (!defined("WPINC")) {
    die;
}

require "vendor/autoload.php";

use Dashifen\TMFChallenge\TMFChallenge;

// this drags the contents of this file into memory and then looks for
// the version number.  it then places that version number into a constant
// that we can use elsewhere as needed.  since the file is small, we hope
// this doesn't take up too much time.  and, this keeps the version number
// written above in sync with the one in memory without having to edit it
// on two lines.

$content = file_get_contents(__FILE__);
preg_match("/Version: +([\.0-9]+)/", $content, $matches);
define("THE_MOTLEY_FOOL_CHALLENGE_VERSION", $matches[1]);

try {

    // the TMFChallenge object encapsulates our plugin's behaviors.
    // calling it's initialize method hooks those behaviors to the
    // actions and filters of the WordPress ecosystem.

    $tmfChallengePlugin = new TMFChallenge();
    $tmfChallengePlugin->initialize();
} catch (Exception $e) {

    // the catcher method of our plugin object will display on-screen the
    // full exception information if the WP_DEBUG constant is defined and
    // set.  otherwise, this error is written to the log so that we can
    // try to fix it later.

    $tmfChallengePlugin::catcher($e);
}
