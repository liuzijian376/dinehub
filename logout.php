<?php
require __DIR__ . '/lib.php';
$_SESSION = [];
session_destroy();
session_start();
flash('You have been logged out.');
redirect('index.php');
