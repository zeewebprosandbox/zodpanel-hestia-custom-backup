<?php
if (!isset($cfg['Servers'][$i])) { $cfg['Servers'][$i] = []; }
$cfg['Servers'][$i]['auth_type'] = 'signon';
$cfg['Servers'][$i]['SignonSession'] = 'SignonSession';
$cfg['Servers'][$i]['SignonURL'] = 'hestia-sso.php';
$cfg['Servers'][$i]['LogoutURL'] = 'hestia-sso.php?logout';
