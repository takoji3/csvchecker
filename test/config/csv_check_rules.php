<?php
return array(
    './test/data/dev.csv' => array(
        'id' => array(
            CsvChecker::CHECK_UNIQUE, 
            CsvChecker::CHECK_ORDER_ASC, 
        ),
        'any_id' => array(
            CsvChecker::CHECK_UNIQUE, 
            CsvChecker::CHECK_NOT_EMPTY, 
            CsvChecker::CHECK_ORDER_DESC, 
            CsvChecker::CHECK_ONLY_NUMBERS, 
        ),
        'start_datetime' => array(
            CsvChecker::CHECK_DATETIME_FORMAT, 
        ),
    ),
);
