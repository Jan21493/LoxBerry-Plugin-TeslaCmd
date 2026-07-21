<?php

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_web.php";
require_once "defines.php";

$navbar[99]['active'] = True;

$L = LBSystem::readlanguage("language.ini");
LBWeb::lbheader($template_title, $helplink, $helptemplate);
?>

<h1>Logfiles</h1>
<?php echo LBWeb::loglist_html(); ?>

<?php
LBWeb::lbfooter();
?>
