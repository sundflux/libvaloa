libvaloa
========

[![webvaloa](https://github.com/sundflux/libvaloa/blob/master/.vendor.png)](https://github.com/sundflux/libvaloa/blob/master/.vendor.png)

Libvaloa is a small utility library for generating HTML user interfaces using XML+XSL, mainly developed for Webvaloa. 

In addition to reference XSL+XML implementation, the UI Interface can be hooked to alternative template engines.

http://libvaloa.webvaloa.com/

## Installation

Install the latest version with `composer require sundflux/libvaloa`

or include libvaloa in your composer.json

```json
{
    "require": {
        "sundflux/libvaloa": "^1.0.0"
    }
}
```

## Requirements

- PHP 5.6 or newer (PHP 7.0+ recommended).
- XSL support enabled (Ubuntu 14.04 package: php5-xsl, Ubuntu 16.04 package: php7.0-xml).
- DOM support enabled (enabled by default).

## Features

- XSL/XML/DOM/SimpleXML/PHP Object conversion library.
- Fast and flexible PDO database abstraction.
- Generic UI interface for hooking template engines.
- Reference UI interface implementation using XSL+XML.
- Debugging helpers, including DOM debugger.
- Localization interface.
- Standards-compatible: PRS-1, PRS-2, PRS-4, verified and fixed with php-cs-fixer.
- No external dependencies.

## Copyright and license

Copyright (C) 2004 - 2017 Tarmo Alexander Sundström & contributors.

Libvaloa is licensed under the MIT License - see the LICENSE file for details.

## Contact

- ta@sundstrom.io
- http://libvaloa.webvaloa.com/

## Change Log
All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](http://semver.org/).

### [1.1.0] - 2017-07-xx
#### Fixed
- Updated project urls.
- Code cleanups for Xml/Conversion.
- Xml/Conversion now exposes more private methods as public.
- Xml/Xsl now uses Xml/Conversion instead of doing direct transformation
- Updated PHP requirement to 5.6 to simplify maintainance.

### [1.0.1] - 2015-05-14
#### Fixed
- Libvaloa\Debug: Debug prints should output only when error level is E_ALL.
- Libvaloa\Ui\Xml: Fix session_status() check in addMessage().

### [1.0.0] - 2015-05-12
#### Added
- Initial release with stable API.

