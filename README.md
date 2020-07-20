# Composer NPM audit

This Composer plugin mimicks `npm audit` for packages installed
with [Assets Packagist](https://asset-packagist.org/)
or the [Composer Asset Plugin](https://github.com/fxpio/composer-asset-plugin).

It provides a simple way to know if your NPM dependencies have known vulnerabilities.

## Install

```shell script
composer require insite/composer-npm-audit
```

## Usage

Simply run `composer npm-audit` and it will display a table like this:

```text
 ---------- ---------------- ------------ --------------------- ---------------------------- ----------------------------------
  Severity   Title            Dependency   Vulnerable versions   Recommendation               URL
 ---------- ---------------- ------------ --------------------- ---------------------------- ----------------------------------
  high       Code Injection   js-yaml      <3.13.1               Upgrade to version 3.13.1.   https://npmjs.com/advisories/813
 ---------- ---------------- ------------ --------------------- ---------------------------- ----------------------------------
```

You can also use `composer npm-audit -c` to generate
a Composer command that will update the vulnerable dependencies, for example:

```shell script
composer require npm-asset/js-yaml:>=3.13.1 --update-with-dependencies
```
