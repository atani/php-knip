# PHP-Knip

PHPプロジェクト向けのデッドコード検出ツールです。未使用のクラス、関数、use文などを発見します。

**日本のレガシーPHPプロジェクト向け** - EUC-JP/Shift_JISエンコーディングに完全対応しています。

## 特徴

- **未使用コード検出**
  - 未使用クラス、インターフェース、トレイト
  - 未使用関数
  - 未使用use文
  - 未使用Composer依存パッケージ

- **PHPバージョン対応**
  - PHP 5.6 〜 PHP 8.3

- **エンコーディング対応**
  - UTF-8（BOMあり/なし）
  - EUC-JP
  - Shift_JIS
  - 自動エンコーディング検出

- **出力形式**
  - テキスト（カラー出力対応）
  - JSON
  - XML
  - JUnit XML（CI連携用）
  - CSV
  - GitHub Actions（アノテーション形式）
  - HTML（レポート出力）

- **高速化**
  - ファイル単位のキャッシュ機能
  - 変更ファイルのみ再解析

- **フレームワーク対応**
  - Laravel（ServiceProvider、Controller、Route等の認識）
  - WordPress（フック、テーマ、プラグイン、ウィジェット等の認識）
  - Symfony（Controller、Command、EventSubscriber、Doctrine等の認識）

## インストール

```bash
composer require --dev php-knip/php-knip
```

## 使い方

### 基本的な使い方

```bash
# カレントディレクトリを解析
./vendor/bin/php-knip

# 特定のディレクトリを解析
./vendor/bin/php-knip src/

# 設定ファイルを指定して解析
./vendor/bin/php-knip --config=php-knip.json
```

### コマンドラインオプション

```
Usage:
  php-knip [options] [--] [<paths>...]

Arguments:
  paths                        解析対象パス（デフォルト: カレントディレクトリ）

Options:
  -c, --config=CONFIG          設定ファイルのパス
  -f, --format=FORMAT          出力形式: text, json, xml, junit, csv, github, html（デフォルト: text）
  -o, --output=OUTPUT          出力ファイルパス（デフォルト: 標準出力）
      --rules=RULES            チェックするルール（カンマ区切り）
      --exclude=EXCLUDE        除外パターン（カンマ区切り）
      --encoding=ENCODING      エンコーディング指定（auto, utf-8, euc-jp, shift_jis）
      --php-version=VERSION    パース用PHPバージョン（5.6, 7.0, ..., 8.3）
      --no-colors              カラー出力を無効化
      --no-cache               キャッシュを無効化
      --clear-cache            キャッシュをクリアして実行
      --fix                    検出した問題を自動修正
      --dry-run                修正をプレビュー（--fixと併用）
  -v, --verbose                詳細出力
  -h, --help                   ヘルプ表示
```

### 設定ファイル

プロジェクトルートに `php-knip.json` を作成：

```json
{
    "include": [
        "src/**/*.php",
        "lib/**/*.php"
    ],
    "exclude": [
        "vendor/**",
        "tests/**"
    ],
    "rules": {
        "unused-classes": true,
        "unused-interfaces": true,
        "unused-traits": true,
        "unused-functions": true,
        "unused-use-statements": true,
        "unused-dependencies": true
    },
    "encoding": "auto",
    "ignore": {
        "symbols": [
            "App\\Legacy\\*",
            "*Controller"
        ],
        "files": [
            "bootstrap.php"
        ]
    }
}
```

## 出力例

### テキスト形式

```
未使用クラス (2)
  ✖ App\Services\OldService src/Services/OldService.php:10
  ✖ App\Utils\DeprecatedHelper src/Utils/DeprecatedHelper.php:5

未使用関数 (1)
  ✖ App\helper_function src/helpers.php:15

未使用use文 (3)
  ⚠ DateTime src/Controller/HomeController.php:5
  ⚠ App\Services\UnusedService src/Controller/HomeController.php:6
  ⚠ InvalidArgumentException src/Services/UserService.php:4

6件の問題を検出（エラー: 3, 警告: 3）
```

### JSON形式

```json
{
  "summary": {
    "total": 6,
    "byType": {
      "unused-classes": 2,
      "unused-functions": 1,
      "unused-use-statements": 3
    },
    "bySeverity": {
      "error": 3,
      "warning": 3
    }
  },
  "issues": [
    {
      "type": "unused-classes",
      "severity": "error",
      "message": "Class 'App\\Services\\OldService' is never used",
      "symbol": "App\\Services\\OldService",
      "file": "src/Services/OldService.php",
      "line": 10
    }
  ]
}
```

## エンコーディング対応

PHP-Knipは以下の方法でファイルのエンコーディングを自動検出します：

1. **BOM検出** - UTF-8, UTF-16, UTF-32
2. **declare(encoding=...)** 文
3. **mbstring検出** - mb_detect_encodingによる自動検出

EUC-JPやShift_JISを使用するレガシーPHPプロジェクトでは、解析前に自動的にUTF-8に変換されます。

### エンコーディングの強制指定

```bash
# EUC-JPを強制
./vendor/bin/php-knip --encoding=euc-jp src/legacy/

# Shift_JISを強制
./vendor/bin/php-knip --encoding=shift_jis src/old/
```

## コードの除外

### 設定ファイルで除外

```json
{
    "ignore": {
        "symbols": [
            "App\\Testing\\*",
            "*Interface",
            "App\\Legacy\\OldClass"
        ],
        "files": [
            "src/bootstrap.php",
            "src/legacy/*"
        ]
    }
}
```

### コメントで除外

```php
<?php
// @php-knip-ignore
class IgnoredClass {}

// @php-knip-ignore unused-class
class AnotherIgnoredClass {}
```

## Laravelプロジェクト対応

Laravelプロジェクトを自動検出し、以下を考慮した解析を行います：

- **ServiceProvider** - 自動的に使用済みとして認識
- **Controller** - ルートファイルからの参照を検出
- **Middleware** - Kernelからの参照を検出
- **Model/Job/Event/Listener** - フレームワーク規約に基づいて認識
- **設定ファイル** - config/*.php内のクラス参照を検出

## WordPressプロジェクト対応

WordPressプロジェクト（テーマ・プラグイン）を自動検出し、以下を考慮した解析を行います：

- **フック** - `add_action`/`add_filter` のコールバック関数を参照として検出
- **テーマ** - `functions.php` やテンプレートファイルをエントリポイントとして認識
- **プラグイン** - プラグインヘッダーを持つファイルをエントリポイントとして認識
- **ショートコード** - `add_shortcode` のコールバックを検出
- **ウィジェット** - `WP_Widget` を継承したクラスを自動的に使用済みとして認識
- **REST API** - `register_rest_route` のコールバックを検出
- **WP-CLI** - `WP_CLI::add_command` で登録されたコマンドクラスを検出
- **MU-Plugins** - `wp-content/mu-plugins/` 内のファイルをエントリポイントとして認識
- **Drop-ins** - `object-cache.php`、`db.php` 等のドロップインを認識

### 対応するプロジェクト構成

- 標準的なWordPress構成（`wp-config.php`、`wp-content/`）
- Composer管理のWordPress（`johnpbloch/wordpress`、`roots/wordpress`）
- wpackagist経由のプラグイン/テーマ

## Symfonyプロジェクト対応

Symfonyプロジェクトを自動検出し、以下を考慮した解析を行います：

- **Controller** - `src/Controller/` 内のコントローラークラスを自動的に使用済みとして認識
- **Command** - コンソールコマンドクラスを検出
- **EventSubscriber/Listener** - イベント処理クラスを検出
- **Form Type** - フォームタイプクラスを認識
- **Twig Extension** - Twig拡張クラスを認識
- **Doctrine Entity/Repository** - エンティティとリポジトリを認識
- **Message Handler** - Messengerハンドラーを認識
- **Security Voter** - セキュリティVoterを認識
- **services.yaml** - サービス定義からのクラス参照を検出
- **routes.yaml** - ルート定義からのコントローラー参照を検出
- **bundles.php** - バンドル登録からのクラス参照を検出

### 対応するプロジェクト構成

- Symfony Flex構成（`config/bundles.php`、`bin/console`）
- `symfony/framework-bundle` または `symfony/http-kernel` を使用するプロジェクト

## キャッシュ機能

PHP-Knipは解析結果をキャッシュし、変更されたファイルのみ再解析することで高速化を実現します。

### キャッシュの動作

- キャッシュは `.php-knip-cache/` ディレクトリに保存されます
- ファイルの更新日時とハッシュ値で変更を検出
- 変更がないファイルはキャッシュから結果を取得

### キャッシュの無効化

```bash
# キャッシュを使用せずに実行
./vendor/bin/php-knip --no-cache

# キャッシュをクリアして実行
./vendor/bin/php-knip --clear-cache
```

### 設定ファイルでの設定

```json
{
    "cache": {
        "enabled": true,
        "directory": ".php-knip-cache"
    }
}
```

## 自動修正機能

検出した問題の一部を自動的に修正できます。現在は未使用use文の削除に対応しています。

### 基本的な使い方

```bash
# 修正のプレビュー（dry-run: ファイルは変更されない）
./vendor/bin/php-knip --fix --dry-run

# 実際に修正を適用
./vendor/bin/php-knip --fix
```

### 動作説明

- **通常の解析ではファイルは一切変更されません**
- `--fix` オプションを明示的に指定した場合のみ、ファイルが修正されます
- `--dry-run` と併用すると、修正内容をプレビューできます（推奨）
- 修正された問題はレポートから除外されます

### 対応している修正

| 問題タイプ | 修正内容 |
|-----------|---------|
| 未使用use文 | use文の行を削除 |

### 出力例

```
フェーズ3: 自動修正...

修正を適用:
  ✔ src/Controller/HomeController.php:5 - Removed use statement 'DateTime' from line 5
  ✔ src/Controller/HomeController.php:6 - Removed use statement 'App\Services\UnusedService' from line 6

修正完了: 2 成功, 0 失敗, 1 スキップ
修正されたファイル: 1 件
```

### 注意事項

- 修正前に必ず `--dry-run` で確認することを推奨します
- バージョン管理されていないファイルの修正は慎重に行ってください
- 複数行にまたがるuse文は正しく処理されない場合があります

## CI連携

### GitHub Actions（アノテーション形式）

`--format=github` を使用すると、PRのコード行に直接アノテーションが表示されます：

```yaml
- name: Run PHP-Knip
  run: ./vendor/bin/php-knip --format=github
```

### JUnit XML形式

テスト結果として出力し、各種CIツールと連携できます：

```yaml
- name: Run PHP-Knip
  run: ./vendor/bin/php-knip --format=junit --output=php-knip-report.xml

- name: Upload Test Results
  uses: actions/upload-artifact@v3
  with:
    name: php-knip-results
    path: php-knip-report.xml
```

### HTMLレポート

静的HTMLファイルとしてレポートを生成できます：

```bash
./vendor/bin/php-knip --format=html --output=report.html
```

## 動作要件

- PHP 5.6以上
- ext-mbstring（エンコーディング検出に推奨）

## 開発

```bash
# 依存パッケージのインストール
composer install

# テスト実行
./vendor/bin/phpunit

# Docker環境でのテスト（複数PHPバージョン）
docker-compose run --rm php74 vendor/bin/phpunit
docker-compose run --rm php83 vendor/bin/phpunit
```

## ロードマップ

- [x] 未使用クラス/インターフェース/トレイト検出
- [x] 未使用関数検出
- [x] 未使用use文検出
- [x] 未使用Composer依存検出
- [x] Laravelプラグイン
- [x] 未使用メソッド検出
- [x] 未使用定数検出
- [x] 未使用プロパティ検出
- [x] 未使用ファイル検出
- [x] キャッシュ機能
- [x] WordPressプラグイン
- [x] Symfonyプラグイン
- [x] 自動修正機能

## サポート

[![GitHub Sponsors](https://img.shields.io/badge/Sponsor-%E2%9D%A4-ea4aaa?logo=github)](https://github.com/sponsors/atani)

## ライセンス

MIT License

## クレジット

[knip](https://github.com/webpro/knip)と[PHPMD](https://phpmd.org/)にインスパイアされています。
