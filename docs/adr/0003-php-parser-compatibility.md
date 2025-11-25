# ADR-0003: php-parser v3/v4互換性対応

## ステータス

採用済み (Accepted)

## コンテキスト

nikic/php-parserはPHP ASTを解析するためのデファクトスタンダードライブラリである。しかし、v3系とv4系でAPIに破壊的変更があり、古いPHPバージョン（5.6-7.0）をサポートするにはv3系が必要となる。

```
php-parser v3: PHP 5.4+ 対応
php-parser v4: PHP 7.0+ 対応
php-parser v5: PHP 7.4+ 対応
```

## 決定

v3とv4の両方をサポートし、実行時に適切なAPIを使用する。

### 具体的な対応

1. **composer.json** で幅広いバージョンを許容:
   ```json
   "nikic/php-parser": "^3.0|^4.0|^5.0"
   ```

2. **API差分への対応**:

   | 機能 | v3 | v4+ |
   |------|-----|-----|
   | 行番号取得 | `getLine()` | `getStartLine()` |
   | 終了行取得 | `getAttribute('endLine')` | `getEndLine()` |
   | 名前の型 | `string` or `Name` | `Identifier` or `Name` |
   | 特殊クラス判定 | 手動判定 | `isSpecialClassName()` |

3. **互換レイヤーの実装**:
   - `getNameString()` ヘルパーで文字列/オブジェクトの両方を処理
   - `is_string()` チェックで型を確認してから処理

## 理由

### 両バージョンサポートを選んだ理由

| 選択肢 | 評価 |
|--------|------|
| **v3+v4サポート（採用）** | PHP 5.6対応可能、実装コストは許容範囲 |
| v4以降のみ | PHP 5.6/7.0ユーザーを切り捨て |
| v3のみ | PHP 8.x新機能（属性等）の解析不可 |
| 独自パーサー | 開発コスト大、品質リスク |

### 主な互換性対応パターン

```php
// v3/v4互換の名前取得
private function getNameString($name)
{
    if (is_string($name)) {
        return $name;
    }
    if (method_exists($name, 'toString')) {
        return $name->toString();
    }
    return (string) $name;
}

// v3/v4互換の行番号取得
$line = method_exists($node, 'getStartLine')
    ? $node->getStartLine()
    : $node->getLine();
```

## 影響

### ポジティブ
- PHP 5.6〜8.3の全バージョンをサポート
- 古いプロジェクトでも新しいプロジェクトでも動作

### ネガティブ
- コード複雑性の増加
- テストマトリクスの拡大
- 将来的なv3サポート終了時の対応コスト

### 緩和策
- Dockerによるマルチバージョンテスト環境
- 互換レイヤーの集約（変更箇所の局所化）

## 参考

- [php-parser Changelog](https://github.com/nikic/PHP-Parser/blob/master/CHANGELOG.md)
- [php-parser v4 Migration Guide](https://github.com/nikic/PHP-Parser/blob/master/UPGRADE-4.0.md)
