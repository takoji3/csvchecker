<?php

class CsvCheckException extends RuntimeException 
{
    public function __construct($message = '', $file = '', $check = '')
    {
        $msg = '';
        if ($file != '') {
            $msg = "File:$file ";
        }
        if ($check != '') {
            $msg .= "Check:$check ";
        }
        parent::__construct("$msg $message");
    }
}

/**
 * CSVデータに対してのチェックライブラリ
 *  
 */
class CsvChecker
{
    /** 
     * チェック内容定義
     *
     * - unique ユニーク
     * - not_empty 空値を許可しない
     * - order_asc 昇順
     * - only_numbers 数値表記のみ
     */
    const CHECK_UNIQUE = 'unique';
    const CHECK_NOT_EMPTY = 'not_empty';
    const CHECK_ORDER_ASC = 'order_asc';
    const CHECK_ORDER_DESC = 'order_desc';
    const CHECK_ONLY_NUMBERS = 'only_numbers';
    const CHECK_DATETIME_FORMAT = 'datetime_format';

    /** 
     * ルール定義一覧の配列
     *
     * @var array 
     */
    private $rule_definitions = array(
        self::CHECK_UNIQUE,
        self::CHECK_NOT_EMPTY,
        self::CHECK_ORDER_ASC,
        self::CHECK_ORDER_DESC,
        self::CHECK_ONLY_NUMBERS,
        self::CHECK_DATETIME_FORMAT,
    );

    /** @var array チェックするルール一覧 */
    private $check_rules = array();

    /** @var array csvカラム */
    private $columns = array();

    /** @var array csvデータ */
    private $contents = array();

    /** @var string チェック中のファイルパス */
    private $currentFile = '';

    /** @var 日付フォーマット */
    const DATETIME_FORMAT = '%Y-%m-%d %H:%I:%S';


    /**
     * チェック処理実行
     * パス指定がある場合は指定ファイルのみ実行
     *
     * @param string $file_path 
     * @reutrn bool 
     */
    public function check($file_path = '')
    {
        $files = $file_path != '' ? array($file_path) : array_keys($this->check_rules);

        try {
            foreach ($files as $file) {
                $this->currentFile = $file;
                $this->validRules();
                $this->readContents();
                $this->execCheck();
            }
        } catch (CsvCheckException $e) {
            echo "Failed!!\n";
            echo $e->getMessage() . "\n";
            return false;
        }
        echo "Success!!\n";
        echo "No error has found.\n";
        return true;
    }

    /**
     * ファイル名、カラム名、チェックルールの配列
     *
     * @param array $rules 
     */
    public function __construct($rules)
    {
        $this->check_rules = $rules;
    }

    /**
     * ユーザー設定からチェック実行可能か確認
     *
     * @throws new CsvCheckException
     */
    private function validRules()
    {
        if (!file_exists($this->currentFile)) {
            throw new CsvCheckException(
                'File not found', 
                $this->currentFile
            );
        }

        if (!isset($this->check_rules[$this->currentFile])) {
            throw new CsvCheckException('Check csv file path was not defined');
        }

        array_walk_recursive($this->check_rules, function ($rule) {
            if (!in_array($rule, $this->rule_definitions)) {
                throw new CsvCheckException('The ' . $rule . ' rule was not defined');
            }
        });
    }

    /**
     * csvデータからリード
     *
     * @throws new CsvCheckException
     */
    private function readContents()
    {
        $handle = fopen($this->currentFile, 'r');
        if ($handle === false) {
            throw new CsvCheckException(
                'Cannot open file', 
                $this->currentFile
            );
        }

        $this->columns = fgetcsv($handle);
        while ($row = fgetcsv($handle)) {

            // comment out row
            if (strpos($row[0], '#') === 0) {
                continue;
            }

            $this->contents[] = $row;
        }
        fclose($handle);
    }

    /**
     * チェック定義からcamelcaseのメソッド名を組み立てる
     *
     * @param string $checkName
     * @return string
     */
    private function convertCamelMethod($checkName)
    {
        return implode('', array_map('ucfirst', explode('_', $checkName)));
    }

    /**
     * チェック実行
     *
     * @throws new CsvCheckException
     */
    private function execCheck()
    {
        foreach ($this->check_rules[$this->currentFile] as $column => $checks) {
            $index = array_search($column, $this->columns);
            if ($index === false) {
                throw new CsvCheckException(
                    "Column:$column was not found", 
                    $this->currentFile
                );
            }

            foreach ($checks as $check) {
                $method = 'check' . $this->convertCamelMethod($check);
                $this->$method($index);
            }

        }
    }

    /**
     * ユニーク値になっているかどうか
     *
     * @param int $index
     * @throws CsvCheckException
     */
    private function checkUnique($index)
    {
        $vals = array_map(function($content) use ($index) {
            return $content[$index];
        }, $this->contents);

        if (count($vals) !== count(array_unique($vals))) {
            throw new CsvCheckException(
                $this->columns[$index] . ' is not unique', 
                $this->currentFile, 
                __FUNCTION__
            );
        }
    }

    /**
     * 空値を許可しない
     *
     * @param int $index
     * @throws CsvCheckException
     */
    private function checkNotEmpty($index)
    {
        $vals = array_filter($this->contents, function($content) use ($index) {
            return empty($content[$index]);
        });

        if (count($vals) > 0) {
            throw new CsvCheckException(
                'Empty value has exists in ' . $this->columns[$index], 
                $this->currentFile, 
                __FUNCTION__
            );
        }
    }

    /**
     * 昇順確認
     *
     * @param int $index
     * @throws CsvCheckException
     */
    private function checkOrderAsc($index)
    {
        $f = function ($val, $old) {
            return $val >= $old;
        };

        if (!$this->checkOrder($index, $f)) {
            throw new CsvCheckException(
                'Not in ascending order',
                $this->currentFile, 
                __FUNCTION__
            );
        }
    }

    /**
     * 降順確認
     *
     * @param int $index
     * @throws CsvCheckException
     */
    private function checkOrderDesc($index)
    {
        $f = function ($val, $old) {
            return $val <= $old;
        };

        if (!$this->checkOrder($index, $f)) {
            throw new CsvCheckException(
                'Not in descending order',
                $this->currentFile, 
                __FUNCTION__
            );
        }
    }

    /**
     * 昇順降順判定内容
     *
     * @param int $index
     * @param Closure $f
     * @return bool 
     */
    private function checkOrder($index, Closure $f)
    {
        $old;
        foreach ($this->contents as $i => $content) {
            $val = $content[$index];

            if ($i === 0) {
                $old = $val;
                continue;
            }

            if (!$f($val, $old)) {
                return false;
            }
            $old = $val;
        }

        return true;
    }

    /**
     * 数値表記のみ許可
     *
     * @param int $index
     * @throws CsvCheckException
     */
    private function checkOnlyNumbers($index)
    {
        array_walk($this->contents, function($content) use ($index) {
            if (!is_numeric($content[$index])) {
                throw new CsvCheckException(
                    $content[$index] . ' is not numeric string', 
                    $this->currentFile, 
                    'checkOnlyNumbers'
                );
            }
        });
    }

    /**
     * 日付表記確認
     *
     * @param int $index
     * @throws CsvCheckException
     */
    private function checkDatetimeFormat($index)
    {
        array_walk($this->contents, function($content) use ($index) {
            if (strptime($content[$index], self::DATETIME_FORMAT) === false) {
                throw new CsvCheckException(
                    $content[$index] . ' is not datetime formart', 
                    $this->currentFile, 
                    'checkDatetimeFormat'
                );
            }
        });
    }

}
