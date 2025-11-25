# PHP-Knip アーキテクチャ

## 概要

PHP-Knipは、PHPコードベースから未使用コードを検出する静的解析ツールです。

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLI (bin/php-knip)                        │
│                      AnalyzeCommand                              │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                        Application                               │
│  - 設定読み込み (ConfigLoader)                                   │
│  - ファイル検索 (Symfony Finder)                                 │
│  - プラグイン管理 (PluginManager)                                │
└─────────────────────────────────────────────────────────────────┘
                                │
        ┌───────────────────────┼───────────────────────┐
        ▼                       ▼                       ▼
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│    Parser    │       │   Resolver   │       │   Analyzer   │
│              │       │              │       │              │
│ AstBuilder   │──────▶│SymbolCollector│─────▶│ClassAnalyzer │
│ Encoding*    │       │ReferenceCollector│    │FunctionAnalyzer│
│              │       │SymbolTable   │       │UseStatementAnalyzer│
│              │       │              │       │DependencyAnalyzer│
└──────────────┘       └──────────────┘       └──────────────┘
                                                      │
                                                      ▼
                                              ┌──────────────┐
                                              │   Reporter   │
                                              │              │
                                              │TextReporter  │
                                              │JsonReporter  │
                                              │XmlReporter   │
                                              │JunitReporter │
                                              └──────────────┘
```

## コンポーネント

### Parser層

PHPソースコードをAST（抽象構文木）に変換する。

| クラス | 責務 |
|--------|------|
| `AstBuilder` | php-parserを使用してASTを構築 |
| `ParserFactory` | PHPバージョンに応じたパーサー生成 |
| `EncodingDetector` | ファイルエンコーディングの検出 |
| `EncodingConverter` | EUC-JP/Shift_JIS → UTF-8変換 |

### Resolver層

ASTからシンボル（クラス、関数等）と参照を収集する。

| クラス | 責務 |
|--------|------|
| `Symbol` | シンボル情報の表現（名前、型、位置等） |
| `SymbolTable` | 収集されたシンボルのコレクション |
| `SymbolCollector` | ASTからシンボルを収集するVisitor |
| `Reference` | 参照情報の表現（型、参照先、位置等） |
| `ReferenceCollector` | ASTから参照を収集するVisitor |

### Analyzer層

シンボルと参照を分析し、未使用コードを検出する。

| クラス | 検出対象 |
|--------|---------|
| `ClassAnalyzer` | 未使用クラス、インターフェース、トレイト |
| `FunctionAnalyzer` | 未使用関数 |
| `UseStatementAnalyzer` | 未使用use文 |
| `DependencyAnalyzer` | 未使用Composer依存 |

### Reporter層

検出結果を様々な形式で出力する。

| クラス | 出力形式 |
|--------|---------|
| `TextReporter` | 人間可読なテキスト（カラー対応） |
| `JsonReporter` | JSON形式 |
| `XmlReporter` | XML形式 |
| `JunitReporter` | JUnit XML形式（CI連携用） |

### Plugin層

フレームワーク固有のロジックを提供する。

| クラス | 責務 |
|--------|------|
| `PluginInterface` | プラグインの契約定義 |
| `AbstractPlugin` | 共通機能を提供する基底クラス |
| `PluginManager` | プラグインの検出・管理 |
| `LaravelPlugin` | Laravel固有のロジック |

## データフロー

```
1. 入力
   └─ PHPソースファイル (*.php)
   └─ 設定ファイル (php-knip.json)
   └─ composer.json / composer.lock

2. パース
   └─ エンコーディング検出・変換
   └─ AST構築

3. シンボル収集
   └─ クラス、関数、定数等の定義を収集
   └─ SymbolTableに格納

4. 参照収集
   └─ new, extends, implements, 関数呼び出し等を収集
   └─ Reference配列に格納

5. プラグイン処理
   └─ フレームワーク固有の参照を追加
   └─ エントリーポイントを追加

6. 分析
   └─ シンボルと参照を照合
   └─ 未使用シンボルを検出

7. 出力
   └─ 選択された形式でレポート出力
```

## 拡張ポイント

### 新しいAnalyzerの追加

1. `AnalyzerInterface` を実装
2. `analyze(AnalysisContext $context)` で検出ロジック実装
3. `Application` に登録

### 新しいReporterの追加

1. `ReporterInterface` を実装
2. `report(array $issues, OutputInterface $output)` で出力ロジック実装
3. `AnalyzeCommand` に登録

### 新しいPluginの追加

1. `AbstractPlugin` を継承
2. `isApplicable()` でフレームワーク検出ロジック実装
3. 必要なメソッドをオーバーライド
4. `PluginManager::discoverBuiltinPlugins()` に登録

## 設計原則

- **単一責任**: 各クラスは1つの責務のみを持つ
- **依存性逆転**: インターフェースに依存し、具象に依存しない
- **開放閉鎖**: 拡張に開き、修正に閉じる（Plugin, Analyzer, Reporter）
- **PHP 5.6互換**: 型宣言、null合体演算子等は使用しない
