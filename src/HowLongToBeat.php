<?php

namespace Askancy\HowLongToBeat;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;

class HowLongToBeat
{
    /**
     * @var Client|null
     */
    private $client;

    /**
     * @var array|null
     */
    private $apiData;

    /**
     * @var array|null
     */
    private static $cachedApiData = null;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? HttpClientCreator::create();
        $this->apiData = self::$cachedApiData ?? $this->fetchApiData();
    }

    /**
     * Endpoint pairs (token init, search), newest first. HowLongToBeat has moved
     * its search API before, so older routes are kept as a fallback.
     */
    private const ENDPOINTS = [
        ['https://howlongtobeat.com/api/search/site/init', 'https://howlongtobeat.com/api/search/site'],
        ['https://howlongtobeat.com/api/bleed/init', 'https://howlongtobeat.com/api/bleed'],
    ];

    /**
     * Fetch the search token dynamically from the HowLongToBeat website.
     *
     * @return array|null
     */
    private function fetchApiData(): ?array
    {
        foreach (self::ENDPOINTS as [$initUrl, $searchUrl]) {
            try {
                $response = $this->client->get($initUrl . '?t=' . (int) round(microtime(true) * 1000), [
                    'headers' => [
                        'Accept' => '*/*',
                        'Referer' => 'https://howlongtobeat.com/',
                    ],
                ]);
            } catch (GuzzleException $e) {
                continue;
            }

            $data = json_decode($response->getBody()->getContents(), true);
            if (is_array($data) && isset($data['token'])) {
                $data['searchUrl'] = $searchUrl;
                self::$cachedApiData = $data;
                return $data;
            }
        }

        return null;
    }

    /**
     * @throws GuzzleException
     */
    public function search($query, int $page = 1): array
    {
        return $this->doSearch($query, $page, true);
    }

    /**
     * @throws GuzzleException
     */
    private function doSearch($query, int $page, bool $canRetry): array
    {
        if (!$this->apiData || !isset($this->apiData['token'])) {
            throw new \RuntimeException('Unable to fetch API key.');
        }

        $payload = [
            'searchType' => 'games',
            'searchTerms' => explode(' ', trim((string) $query)),
            'searchPage' => $page,
            'size' => 20,
            'searchOptions' => [
                'games' => [
                    'userId' => 0,
                    'platform' => '',
                    'sortCategory' => 'popular',
                    'rangeCategory' => 'main',
                    'rangeTime' => [
                        'min' => 0,
                        'max' => 0,
                    ],
                    'gameplay' => [
                        'perspective' => '',
                        'flow' => '',
                        'genre' => '',
                        'difficulty' => '',
                    ],
                    'rangeYear' => [
                        'min' => '',
                        'max' => '',
                    ],
                    'year' => '',
                    'modifier' => '',
                ],
                'users' => [
                    'sortCategory' => 'postcount',
                ],
                'lists' => [
                    'sortCategory' => 'follows',
                ],
                'filter' => '',
                'sort' => 0,
                'randomizer' => 0,
            ],
            'useCache' => true,
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'x-auth-token' => $this->apiData['token'],
            'Referer' => 'https://howlongtobeat.com/',
            'Origin' => 'https://howlongtobeat.com',
        ];

        if (isset($this->apiData['hpKey']) && isset($this->apiData['hpVal'])) {
            $payload[$this->apiData['hpKey']] = $this->apiData['hpVal'];
            $headers['x-hp-key'] = $this->apiData['hpKey'];
            $headers['x-hp-val'] = $this->apiData['hpVal'];
        }

        try {
            $response = $this->client->post($this->apiData['searchUrl'] ?? self::ENDPOINTS[0][1], [
                'headers' => $headers,
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            // Token expired or endpoint moved: refresh once, then give up.
            if ($canRetry && in_array($e->getCode(), [403, 404], true)) {
                self::$cachedApiData = null;
                $this->apiData = $this->fetchApiData();
                return $this->doSearch($query, $page, false);
            }

            throw $e;
        }

        $searchResult = json_decode($response->getBody()->getContents(), true);

        if (!is_array($searchResult) || !isset($searchResult['data']) || !is_array($searchResult['data'])) {
            throw new \RuntimeException('Unexpected response from HowLongToBeat search API.');
        }

        $games = array_map(
            static function ($game): array {
                return (new JSONExtractor($game))->extract();
            },
            $searchResult['data']
        );

        return [
            'Results' => $games,
            'Pagination' => [
                'Total Results' => $searchResult['count'] ?? count($games),
                'Current Page' => $page,
                'Last Page' => $searchResult['pageTotal'] ?? 1,
            ],
        ];
    }

    /**
     * @throws GuzzleException
     */
    public function get($id): array
    {
        $node = new Crawler(
            $this->client->get('https://howlongtobeat.com/game?id=' . $id)->getBody()->getContents()
        );

        $json = json_decode($node->filter('#__NEXT_DATA__')->html(), true);
        $game = $json['props']['pageProps']['game']['data']['game'][0];

        $jsonExtractor = new JSONExtractor($game);
        $crawlerExtractor = new CrawlerExtractor($node);

        return array_merge($jsonExtractor->extract(), $crawlerExtractor->extract());
    }
}
