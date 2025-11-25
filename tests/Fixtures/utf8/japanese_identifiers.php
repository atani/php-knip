<?php
/**
 * Japanese identifiers fixture (UTF-8)
 *
 * Tests multibyte identifier support
 */

namespace App\日本語;

/**
 * 日本語クラス名のテスト
 */
class ユーザー管理
{
    /**
     * @var string
     */
    private $名前;

    /**
     * @var int
     */
    private $年齢;

    /**
     * コンストラクタ
     *
     * @param string $名前 ユーザー名
     * @param int $年齢 年齢
     */
    public function __construct($名前, $年齢)
    {
        $this->名前 = $名前;
        $this->年齢 = $年齢;
    }

    /**
     * 名前を取得
     *
     * @return string
     */
    public function 名前を取得()
    {
        return $this->名前;
    }

    /**
     * 年齢を取得
     *
     * @return int
     */
    public function 年齢を取得()
    {
        return $this->年齢;
    }
}

/**
 * ヘルパー関数
 *
 * @param string $文字列 入力文字列
 * @return string 処理結果
 */
function 文字列処理($文字列)
{
    return mb_convert_kana($文字列, 'KV');
}

// 定数
define('最大文字数', 100);
