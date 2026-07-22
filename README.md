# TigerZF - Zend Framework Reborn for Modern PHP

## The Legend Returns

TigerZF is a powerful continuation of the legendary Zend Framework 1, meticulously updated and optimized for modern PHP applications. Built on the solid foundation of [zf1-future](https://github.com/Shardj/zf1-future), TigerZF brings the battle-tested reliability of ZF1 into the modern era of PHP development.

## Why TigerZF?

When Zend abandoned ZF1, millions of production applications were left behind. But great frameworks don't die - they evolve. TigerZF represents that evolution:

- **Battle-Tested Architecture**: Over 15 years of production proven patterns
- **Modern PHP Support**: Fully compatible with PHP 8.1 through 8.5
- **Enterprise Ready**: Trusted by production applications worldwide
- **Community Driven**: Actively maintained by passionate developers who refuse to let excellence die

## Features

### Classic Power, Modern Performance
- **MVC Architecture**: The rock-solid MVC implementation that made ZF1 famous
- **Database Abstraction**: Powerful Zend_Db with support for modern database systems
- **Form Building**: Comprehensive form generation and validation
- **Authentication & ACL**: Enterprise-grade security components
- **Caching System**: Multiple backend support for optimal performance
- **Internationalization**: Full i18n/l10n support out of the box

### Modern Enhancements
- **PHP 8.1–8.5 Compatibility**: Fully tested and optimized for current PHP versions, deprecation-free
- **Composer Integration**: Modern dependency management
- **PSR Compatibility**: Where it makes sense, without breaking the ZF1 philosophy
- **Security Updates**: All known vulnerabilities patched and maintained
- **Performance Optimizations**: Faster than ever with modern PHP optimizations

## Installation

```bash
composer require webtigers/tigerzf
```

Or clone directly:
```bash
git clone https://github.com/WebTigers/TigerZF.git
```

## Quick Start

```php
<?php
// Bootstrap your application
require_once 'Zend/Application.php';

// Create application
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);

// Bootstrap and run
$application->bootstrap()->run();
```

## System Requirements

- PHP 8.1 or higher (tested through 8.5)
- Composer for dependency management
- Web server with URL rewriting support

## Documentation

- [TigerZF Manual](https://tigerzf.webtigers.com/docs) — the full ZF1 reference manual, modernized (available in English and Spanish)
- [Migration Guide](https://github.com/WebTigers/TigerZF/blob/main/MIGRATION.md) (Coming Soon)

## Why Choose TigerZF Over Other Frameworks?

### Stability Over Trends
While other frameworks chase the latest trends and break compatibility every major version, TigerZF values:
- **Backward Compatibility**: Your ZF1 apps run with minimal changes
- **Predictable APIs**: No surprises, no breaking changes
- **Long-term Support**: We're here for the long haul

### Real-World Focus
- **Production First**: Every change is evaluated for production impact
- **Enterprise Tested**: Used in mission-critical applications
- **Performance Minded**: Optimized for real-world scenarios, not benchmarks

## Community

TigerZF is more than a framework - it's a community of developers who believe in sustainable, reliable software development.

- **Contributing**: See [CONTRIBUTING.md](CONTRIBUTING.md)
- **Issues**: [GitHub Issues](https://github.com/WebTigers/TigerZF/issues)
- **Discussions**: [GitHub Discussions](https://github.com/WebTigers/TigerZF/discussions)

## The TigerZF Philosophy

> "Great software doesn't need to be rewritten every few years. It needs to be refined, optimized, and cherished."

TigerZF embodies this philosophy. We're not here to reinvent the wheel - we're here to make sure the best wheel ever created keeps rolling smoothly on modern roads.

## Sponsors

TigerZF development is sponsored by:

- [WebTigers](https://webtigers.com) - Enterprise PHP Development
- [Your Company Here] - [Become a Sponsor](https://github.com/sponsors/WebTigers)

## License

TigerZF is released under the **BSD 3-Clause License** (see [LICENSE](LICENSE)) — the same license as its heritage. It continues Zend Framework 1 (via [zf1-future](https://github.com/Shardj/zf1-future)), distributed under the New BSD (BSD 3-Clause) License, © 2005-2015 Zend Technologies USA, Inc.; the original notice is preserved verbatim in [LICENSE.txt](LICENSE.txt) and retained in the inherited source files.

## Acknowledgments

TigerZF stands on the shoulders of giants:
- The original Zend Framework team for creating this masterpiece
- The [zf1-future](https://github.com/Shardj/zf1-future) community for keeping it alive
- Every developer who chose stability and reliability over hype

---

**TigerZF - Because Legends Never Die**

*Roar with confidence. Build with TigerZF.*
