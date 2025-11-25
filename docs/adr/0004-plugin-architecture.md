# ADR-0004: フレームワークプラグインアーキテクチャ

## ステータス

採用済み (Accepted)

## コンテキスト

PHPフレームワーク（Laravel, Symfony, WordPress等）は、規約ベースのクラス参照やマジックメソッドを多用する。標準的な静的解析では、これらの暗黙的な参照を検出できず、誤検出（false positive）が多発する。

例：
- Laravel ServiceProvider: `config/app.php`で文字列として参照
- Laravel Controller: ルートファイルで文字列として参照
- WordPress Hook: `add_action()`で文字列として参照

## 決定

プラグインアーキテクチャを採用し、フレームワーク固有のロジックを分離する。

### アーキテクチャ

```
┌─────────────────────────────────────────────────┐
│                 PluginManager                    │
│  - プラグインの検出・登録                        │
│  - 適用可能なプラグインの有効化                  │
│  - 集約されたignoreパターン/参照の提供           │
└─────────────────────────────────────────────────┘
                        │
        ┌───────────────┼───────────────┐
        ▼               ▼               ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│LaravelPlugin │ │SymfonyPlugin │ │WordPressPlugin│
│              │ │   (将来)     │ │    (将来)    │
└──────────────┘ └──────────────┘ └──────────────┘
```

### PluginInterface

```php
interface PluginInterface
{
    public function getName();
    public function isApplicable($projectRoot, array $composerData);
    public function getIgnorePatterns();
    public function getEntryPoints($projectRoot);
    public function getAdditionalReferences($projectRoot);
    public function processSymbols(SymbolTable $symbolTable, $projectRoot);
}
```

## 理由

### プラグインアーキテクチャを選んだ理由

| 選択肢 | 評価 |
|--------|------|
| **プラグイン（採用）** | 拡張性高、フレームワーク知識を分離可能 |
| 設定ファイルでignore | 柔軟性低、ユーザー負担大 |
| フレームワーク検出をコアに組込 | コア肥大化、テスト困難 |
| サードパーティ依存 | フレームワークSDKへの依存 |

### プラグインの責務

1. **フレームワーク検出**: `isApplicable()` で自動検出
2. **エントリーポイント提供**: Controller, Command等
3. **追加参照の収集**: Route, Config等からのクラス参照
4. **無視パターン**: フレームワーク規約による暗黙使用

### 自動検出の優先順位

1. 特徴的ファイルの存在（`artisan`, `wp-config.php`等）
2. composer.json依存パッケージ
3. ディレクトリ構造

## 影響

### ポジティブ
- フレームワーク対応の容易な追加
- コアロジックの純粋性維持
- サードパーティによるプラグイン開発の可能性

### ネガティブ
- 初期開発コストの増加
- プラグインAPIの安定性維持コスト
- プラグイン間の優先度管理

### 緩和策
- AbstractPlugin基底クラスでデフォルト実装提供
- 優先度（priority）による実行順序制御
- 将来的なプラグイン設定UI/CLIの提供

## 参考

- [knip plugins](https://github.com/webpro/knip)
- [PHPStan extensions](https://phpstan.org/developing-extensions/extension-types)
