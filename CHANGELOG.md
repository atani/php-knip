# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2024-XX-XX

### Added

- Initial release
- **Unused Class Detection**
  - Detects classes that are never instantiated, extended, or referenced
  - Supports abstract classes (checks for subclasses)
  - Detects via: `new`, `extends`, `implements`, static calls, type hints, instanceof, catch
- **Unused Interface Detection**
  - Detects interfaces that are never implemented or used as type hints
- **Unused Trait Detection**
  - Detects traits that are never used via `use` statement in classes
- **Unused Function Detection**
  - Detects functions that are never called
  - Supports callback-style references (array_map, call_user_func, etc.)
- **Unused Use Statement Detection**
  - Detects `use` statements that import unused classes, functions, or constants
  - Supports aliased imports
- **Encoding Support**
  - Automatic encoding detection (BOM, declare statement, mbstring)
  - UTF-8, EUC-JP, Shift_JIS support
  - Automatic conversion to UTF-8 for analysis
- **Output Formats**
  - Text reporter with color support
  - JSON reporter for machine processing
- **Configuration**
  - JSON configuration file support (php-knip.json)
  - Ignore patterns for symbols and files
  - Rule selection
- **PHP Version Support**
  - PHP 5.6 to PHP 8.3 syntax support
  - Version-specific parser selection

### Dependencies

- nikic/php-parser ^3.0 || ^4.0 || ^5.0
- symfony/console ^3.4 || ^4.0 || ^5.0 || ^6.0 || ^7.0
- symfony/finder ^3.4 || ^4.0 || ^5.0 || ^6.0 || ^7.0

## Future Plans

### [0.2.0]

- Unused dependency detection (composer.json analysis)
- XML and JUnit reporters
- PSR-0/PSR-4/classmap autoload resolution

### [0.3.0]

- Framework plugin system
- Laravel plugin
- Symfony plugin
- WordPress plugin

### [1.0.0]

- Unused method detection
- Unused constant detection
- Unused property detection
- Auto-fix support
- Performance optimizations (caching, incremental analysis)
