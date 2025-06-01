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
     * @var string|null
     */
    private $apiKey;

    /**
     * @var string|null
     */
    private static $cachedApiKey = null;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? HttpClientCreator::create();
        $this->apiKey = self::$cachedApiKey ?? $this->fetchApiKey();
    }

    /**
     * Fetch the API key dynamically from the HowLongToBeat website.
     *
     * @return string|null
     */
    private function fetchApiKey(): ?string
    {
        try {
            $response = $this->client->get('https://www.howlongtobeat.com');
            $html = $response->getBody()->getContents();

            $crawler = new Crawler($html);
            $scripts = $crawler->filter('script[src]');

            foreach ($scripts as $script) {
                if ($script instanceof \DOMElement) {
                    $src = $script->getAttribute('src');
                    if ($src && strpos($src, '_app-') !== false) {
                        $scriptUrl = 'https://www.howlongtobeat.com' . $src;

                        $scriptResponse = $this->client->get($scriptUrl)->getBody()->getContents();

                        $userIdPattern = '/users\s*:\s*{\s*id\s*:\s*"([^"]+)"/';
                        if (preg_match($userIdPattern, $scriptResponse, $matches)) {
                            self::$cachedApiKey = $matches[1];
                            return $matches[1];
                        }

                        $concatPattern = '/\/api\/find\/(?:\.concat\("[^"]*"\))*/';
                        if (preg_match($concatPattern, $scriptResponse, $matches)) {
                            $parts = explode('.concat', $matches[0]);
                            $key = '';
                            foreach ($parts as $part) {
                                $key .= preg_replace('/["\(\)\[\]\']/', '', $part);
                            }
                            self::$cachedApiKey = $key;
                            return $key;
                        }
                    }
                }
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
        if (!$this->apiKey) {
            throw new \RuntimeException('Unable to fetch API key.');
        }

        try {
            $response = $this->client->post('https://howlongtobeat.com' . $this->apiKey, [
                'json' =>
                [
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
                            'id' => $this->apiKey,
                        ],
                        'lists' => [
                            'sortCategory' => 'follows',
                        ],
                        'filter' => '',
                        'sort' => 0,
                        'randomizer' => 0,
                    ],
                    'useCache' => true,
                ]
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
            if ($e->getCode() === 404) {
                $this->apiKey = $this->fetchApiKey();
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
