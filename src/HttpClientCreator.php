<?php

namespace Askancy\HowLongToBeat;

use GuzzleHttp\Client;

class HttpClientCreator
{
    public static function create(): Client
    {
        return new Client([
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
                'referer' => 'https://howlongtobeat.com/'
            ]
        ]);
    }
}
