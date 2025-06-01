# 🎮 HowLongToBeat PHP Wrapper

> A modern PHP wrapper to fetch game duration data from [HowLongToBeat.com](https://howlongtobeat.com)  
> Perfect for gaming apps, backlog tools, or completionist dashboards.

<div align="center">

<a href="https://packagist.org/packages/askancy/howlongtobeat">
    <img src="https://img.shields.io/packagist/v/askancy/howlongtobeat?style=for-the-badge" alt="Packagist Version">
</a>
<a href="https://packagist.org/packages/askancy/howlongtobeat">
    <img src="https://img.shields.io/packagist/dt/askancy/howlongtobeat?style=for-the-badge" alt="Packagist Downloads">
</a>
<a href="https://packagist.org/packages/askancy/howlongtobeat">
    <img src="https://img.shields.io/github/license/askancy/howlongtobeat?style=for-the-badge" alt="License">
</a>

</div>

---

## ✨ Features

- 🔍 Search games by title
- ⏱ Get main/extra/completionist durations
- 🧩 Fetch rich game details including platforms, stats, and genres
- ⚡ Fully PSR-4, Guzzle-powered, clean and extendable

---

## 📦 Installation

Install via Composer:

```bash
composer require askancy/howlongtobeat
```

---

## 🚀 Usage

### 🔎 Search games

```php
use Askancy\HowLongToBeat\HowLongToBeat;

$hl2b = new HowLongToBeat();
$results = $hl2b->search('Lego');
```

Sample response:

```json
{
  "Results": [
    {
      "ID": "5265",
      "Title": "LEGO The Lord of the Rings",
      "Image": "https://howlongtobeat.com/gameimages/220px-Lego_Lord_of_the_Rings_cover.jpg",
      "Summary": {
        "Main Story": "10 Hours",
        "Main + Extra": "16 Hours",
        "Completionist": "33 Hours"
      }
    }
  ],
  "Pagination": {
    "Current Page": 1,
    "Last Page": 4
  }
}
```

You can also paginate:

```php
$hl2b->search('Lego', 2);
```

---

### 🧠 Get game details by ID

```php
$details = $hl2b->get(5265);
```

## 🚤 Laravel Example

You can easily integrate this wrapper into a Laravel controller or service class:

**Controller example:**

```php
use Askancy\HowLongToBeat\HowLongToBeat;

class GameController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('title');
        $hl2b = new HowLongToBeat();
        $results = $hl2b->search($query);

        return view('games.results', ['results' => $results]);
    }

    public function show($id)
    {
        $hl2b = new HowLongToBeat();
        $game = $hl2b->get($id);

        return view('games.show', ['game' => $game]);
    }
}
```

Then route it:

```php
Route::get('/games/search', [GameController::class, 'search']);
Route::get('/games/{id}', [GameController::class, 'show']);
```

Includes:

- Full summary and description
- Developer & publisher info
- Platform stats
- Time breakdowns (Main, 100%, Speedrun, Multiplayer, etc.)

---

## 🤝 Contributing

Got a bug or an idea?  
Open an issue or send a PR — contributions are welcome!

```bash
git clone https://github.com/askancy/howlongtobeat.git
composer install
vendor/bin/phpunit
```

---

## 📜 License

Released under the [MIT License](https://choosealicense.com/licenses/mit/).
