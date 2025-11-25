# 設定リファレンス

## 設定ファイル

PHP-Knipは `php-knip.json` ファイルで設定を行います。

### 設定ファイルの検索順序

1. `--config` オプションで指定されたパス
2. カレントディレクトリの `php-knip.json`
3. プロジェクトルート（composer.jsonがある場所）の `php-knip.json`

## 設定項目

### include

解析対象のファイルパターンを指定します。

```json
{
    "include": [
        "src/**/*.php",
        "lib/**/*.php",
        "app/**/*.php"
    ]
}
```

- globパターンを使用可能
- `**` は任意の深さのディレクトリにマッチ
- 指定しない場合はカレントディレクトリ以下の全`.php`ファイル

### exclude

解析から除外するファイルパターンを指定します。

```json
{
    "exclude": [
        "vendor/**",
        "tests/**",
        "node_modules/**",
        "cache/**"
    ]
}
```

### rules

有効にするルールを指定します。

```json
{
    "rules": {
        "unused-classes": true,
        "unused-interfaces": true,
        "unused-traits": true,
        "unused-functions": true,
        "unused-use-statements": true,
        "unused-dependencies": true
    }
}
```

| ルール | 説明 | デフォルト |
|--------|------|-----------|
| `unused-classes` | 未使用クラスの検出 | true |
| `unused-interfaces` | 未使用インターフェースの検出 | true |
| `unused-traits` | 未使用トレイトの検出 | true |
| `unused-functions` | 未使用関数の検出 | true |
| `unused-use-statements` | 未使用use文の検出 | true |
| `unused-dependencies` | 未使用Composer依存の検出 | true |

### encoding

ファイルエンコーディングの指定。

```json
{
    "encoding": "auto"
}
```

| 値 | 説明 |
|----|------|
| `auto` | 自動検出（デフォルト） |
| `utf-8` | UTF-8を強制 |
| `euc-jp` | EUC-JPを強制 |
| `shift_jis` | Shift_JISを強制 |

### ignore

特定のシンボルやファイルを無視します。

```json
{
    "ignore": {
        "symbols": [
            "App\\Legacy\\*",
            "*Controller",
            "App\\Providers\\*"
        ],
        "files": [
            "bootstrap.php",
            "src/legacy/*"
        ]
    }
}
```

#### symbols

無視するシンボル（クラス、関数等）のパターン。

- `*` は任意の文字列にマッチ
- FQN（完全修飾名）で指定

#### files

無視するファイルのパターン。

- globパターンを使用可能

### phpVersion

パース時のPHPバージョンを指定。

```json
{
    "phpVersion": "7.4"
}
```

指定しない場合は、実行環境のPHPバージョンを使用。

### plugins

有効にするプラグインを指定。

```json
{
    "plugins": {
        "laravel": true,
        "symfony": false
    }
}
```

指定しない場合は、自動検出されたプラグインがすべて有効になります。

## 完全な設定例

```json
{
    "include": [
        "app/**/*.php",
        "src/**/*.php"
    ],
    "exclude": [
        "vendor/**",
        "tests/**",
        "storage/**",
        "bootstrap/cache/**"
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
    "phpVersion": "7.4",
    "ignore": {
        "symbols": [
            "App\\Providers\\*",
            "App\\Console\\Kernel",
            "App\\Http\\Kernel",
            "App\\Exceptions\\Handler"
        ],
        "files": [
            "app/helpers.php"
        ]
    },
    "plugins": {
        "laravel": true
    }
}
```

## コマンドラインオプションとの関係

コマンドラインオプションは設定ファイルの値を上書きします。

| オプション | 設定ファイル |
|-----------|-------------|
| `--format` | - |
| `--output` | - |
| `--exclude` | `exclude` に追加 |
| `--encoding` | `encoding` を上書き |
| `--php-version` | `phpVersion` を上書き |
| `--rules` | `rules` を上書き |

## 環境変数

| 変数 | 説明 |
|------|------|
| `PHP_KNIP_NO_COLORS` | カラー出力を無効化 |
| `PHP_KNIP_CONFIG` | 設定ファイルのパス |
