Samba PHP stream wrapper
=====
[![Build Status](https://secure.travis-ci.org/crystalservice/samba.png?branch=master)](http://travis-ci.org/crystalservice/samba)
[![Coverage Status](https://coveralls.io/repos/crystalservice/samba/badge.png)](https://coveralls.io/r/crystalservice/samba)
[![Code Climate](https://codeclimate.com/github/crystalservice/samba.png)](https://codeclimate.com/github/crystalservice/samba)

Fork of [SMB4PHP](https://code.google.com/p/smbwebclient/)
 
Requirements
------

**smbclient** should be installed (use `sudo apt-get install smbclient` on ubuntu)  

Installation
-----

Add following to your _composer.json_ file:

```json
{
    "require": {
        "crystalservice/samba": "dev-master"
    },
}
```

Usage
-----

Reqister smb stream wrapper shortcut method:
```php
\Samba\SambaStreamWrapper::register();
```

You can check is wrapper is already registered usign this call:
```php
\Samba\SambaStreamWrapper::is_registered();
```

