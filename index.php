<?php

require_once './src/CsvChecker.php';

/**
 * This is a simple way to define check rules
 *
 * $rules = array(
 *     './data/dev.csv' => array(
 *         'id' => array(
 *             CsvChecker::CHECK_UNIQUE, 
 *         ),
 *     )
 * );
 *
 */

// better way
$rules = require_once './test/config/csv_check_rules.php';

$checker = new CsvChecker($rules);

$checker->check();

// If you wanna check specific file, 
// you can give any file path
$file = './test/data/dev.csv';
$checker->check($file);
