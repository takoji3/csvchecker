## Csv Checker

主に手動入力等で生成したcsv設定ファイルの記述orフォーマットミスを検出する

## 使い方
```php
<?php
/**
 * array(
 *     {filePath} => array(
 *         {column} => array(
 *             checkRule,
 *             ...
 *     )
 * )
 */

$rules = array(
    '***.csv' => array(
        'id' => array(
            CsvChecker::CHECK_UNIQUE,
        ),
    )
);
$checker = new CsvChecker($rules);
$checker->check();

```
