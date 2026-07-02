<?php
$TAB = 'MODULES';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/main.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/zodpanel_modules.php';
render_page($user, $TAB, 'list_modules');
$_SESSION['back'] = $_SERVER['REQUEST_URI'];