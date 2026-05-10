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
     * Fetch the API data dynamically from the HowLongToBeat website.
     *
     * @return array|null
     */
    private function fetchApiData(): ?array
    {
        try {
            $time = round(microtime(true) * 1000);
            $response = $this->client->get('https://howlongtobeat.com/api/bleed/init?t=' . $time, [
                'headers' => [
                    'Accept' => '*/*',
                    'Referer' => 'https://howlongtobeat.com/'
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['token'])) {
                self::$cachedApiData = $data;
                return $data;
            }
        } catch (GuzzleException $e) {
            return null;
        }

        return null;
    }

    /**
     * @throws GuzzleException
     */
    public function search($query, int $page = 1): array
    {
        if (!$this->apiData || !isset($this->apiData['token'])) {
            throw new \RuntimeException('Unable to fetch API key.');
        }

        try {
            $payload = [
                'searchType' => 'games',
                'searchTerms' => explode(' ', $query),
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
                'Referer' => 'https://howlongtobeat.com/'
            ];

            if (isset($this->apiData['hpKey']) && isset($this->apiData['hpVal'])) {
                $payload[$this->apiData['hpKey']] = $this->apiData['hpVal'];
                $headers['x-hp-key'] = $this->apiData['hpKey'];
                $headers['x-hp-val'] = $this->apiData['hpVal'];
            }

            $response = $this->client->post('https://howlongtobeat.com/api/bleed', [
                'headers' => $headers,
                'json' => $payload
            ]);

            $searchResult = json_decode($response->getBody()->getContents(), true);

            $games = array_map(
                static function ($game): array {
                    return (new JSONExtractor($game))->extract();
                },
                $searchResult['data']
            );

            return [
                'Results' => $games,
                'Pagination' => [
                    'Total Results' => $searchResult['count'],
                    'Current Page' => $page,
                    'Last Page' => $searchResult['pageTotal'],
                ]
            ];
        } catch (GuzzleException $e) {
            if ($e->getCode() === 403 || $e->getCode() === 404) {
                $this->apiData = $this->fetchApiData();
                return $this->search($query, $page);
            }

            throw $e;
        }
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
