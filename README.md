# Store, analyse and retrieve KPI over time in your Laravel App

[![Latest Version on Packagist](https://img.shields.io/packagist/v/finller/laravel-kpi.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-kpi)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/finller/laravel-kpi/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/finller/laravel-kpi/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/finller/laravel-kpi/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/finller/laravel-kpi/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/finller/laravel-kpi.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-kpi)

This package provides a way to store kpis from your app in your database and then retreive them easily in different ways. It is espacially usefull to tracks things related to your models like:

-   the number of users
-   the number of products
-   ...

It's a perfect tool for building dashboard ans display stats/charts.

## Installation

You can install the package via composer:

```bash
composer require finller/laravel-kpi
```

You have to publish and run the migrations with:

```bash
php artisan vendor:publish --tag="kpi-migrations"
php artisan migrate
```

## Usage

This package is **not a query builder**, it's based on a kpi table where you will store all your kpis.
With this approach, your kpis from the past (like the number of users you had a year ago) will not be altered if you permanently delete a model. And retreiving kpis will be much more efficient when asking for cumputed value that often require join like "users who have purchased last week" for example.

### Step 1: Store kpis in you database

As said above, you will have to store the kpis you need in database. Kpis are grouped by `keys` and support different kind of values:

-   number (float) under `number_value` column
-   string under `string_value` column
-   json or array under `json_value` column
-   money under `money_value` and `money_currency` column

In most cases, you would store numbers, like the number of users for example. You are free to choose the key but you could do it like that:

```php
Kpi::create([
    'key' => 'users:count',
    'number_value' => User::count(),
]);
```

Generally kpis are related to models, that's why we provid a trait `HasKpi` with a standardized way to name your kpi key `{namespace}:{key}`. For the User model, it would store your key in the `users` namespace like `users:{key}`.

A standard way to save your kpi values would be in a command that runs every day for example.

You are free to store as much kpis as needed, even multiple times in a day, so you got more recent data.

### Step 2: Retreive you kpis

You can retreive kpis by using usefull scopes and native eloquent Builder methods.

For example, if a want to query kpis under `users:count`, I would use:

```php
// With Kpi model
use Finller\Kpi\Kpi;
Kpi::where('key', "users:count")->get();

// With HasKpi trait
use App\Models\User;
User::kpi('count')->get();

// With HasKpi KpiBuilder
use Finller\Kpi\KpiBuilder;
KpiBuilder::query("users:count")->get();
KpiBuilder::query(Kpi::query()->where("key", "users:count"))->get();
```

#### Query by date

In most cases, you would like to have thoses kpis grouped by date (day, month, year, ...).

For example, to get the number of users grouped by day between 2 dates (usefull to draw a chart), you could do:

```php
User::kpi('count')
    ->between(start: now(), end: now()->subDays(7))
    ->perDay()
    ->get();
```

As we are grouping by date, you could have more than 1 snapshot of the same key for a date. In this situation, this package will give you only the most recent snaphot of each date.

In the previous example, I would get the most recent count of users of each day. This is also true for other kind of supported intervals.

#### Supported intervals:

The supported intervals are: perDay, perWeek, perMonth and perYear.

```php
Kpi::query()->perDay()->get();
Kpi::query()->perWeek()->get();
Kpi::query()->perMonth()->get();
Kpi::query()->perYear()->get();
```

#### Fill gaps between dates

In some cases, you could have miss a snapshot. Let's say that your snapshot kpi command failed or your server was down.

To fill the gaps let by missing values, you can use the `fillGaps` method available on KpiBuilder or available on `KpiCollection`.
By default the placeholders will be a copy of their previous kpi.

For convenience the `KpiBuilder` is the best option as it will give you better typed values and share parameters between fillGaps and between.

```php
KpiBuilder::query('users:blocked:count')
    ->perDay()
    ->between(now()->subWeek(), now())
    ->fillGaps()
    ->get();

Kpi::query()
    ->where('key', 'users:blocked:count')
    ->perDay()
    ->get()
    ->fillGaps( // optional parameters
        start: now(),
        end: now()->subWeek(),
        interval: 'day',
        default: ['number_value' => 0]
    );

Kpi::query()
    ->where('key', 'users:blocked:count')
    ->perDay()
    ->get()
    ->fillGaps(); // if you specify nothing when using KpiCollection: start, end and interval value will be guessed from you dataset
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Quentin Gabriele](https://github.com/QuentinGab)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
