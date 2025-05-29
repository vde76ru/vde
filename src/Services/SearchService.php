<?php
// src/Services/SearchService.php
namespace App\Services;

use App\Core\SearchConfig;
use App\Core\Logger;
use App\Core\Cache;
use OpenSearch\ClientBuilder;

/**
 * Единый сервис для поиска товаров
 * 
 * Вместо трех разных файлов, у нас теперь один умный сервис,
 * который знает всё о поиске товаров!
 */
class SearchService
{
    private static ?\OpenSearch\Client $client = null;
    
    /**
     * Поиск товаров с учетом всех параметров
     */
    public static function search(array $params): array
    {
        try {
            // Валидируем параметры
            $params = self::validateSearchParams($params);
            
            // Проверяем кеш
            $cacheKey = self::getCacheKey($params);
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
            
            // Строим запрос к OpenSearch
            $searchBody = self::buildSearchQuery($params);
            
            // Выполняем поиск
            $response = self::getClient()->search([
                'index' => SearchConfig::PRODUCTS_INDEX,
                'body' => $searchBody
            ]);
            
            // Обрабатываем результаты
            $result = self::processSearchResults($response, $params);
            
            // Обогащаем динамическими данными
            if (!empty($result['products'])) {
                $result['products'] = self::enrichWithDynamicData(
                    $result['products'], 
                    $params['city_id'] ?? 1,
                    $params['user_id'] ?? null
                );
            }
            
            // Кешируем результат
            Cache::set($cacheKey, $result, 300); // 5 минут
            
            // Логируем поиск для аналитики
            self::logSearch($params, $result['total']);
            
            return $result;
            
        } catch (\Exception $e) {
            Logger::error('Search failed', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            
            return [
                'products' => [],
                'total' => 0,
                'error' => 'Ошибка поиска'
            ];
        }
    }
    
    /**
     * Автодополнение для поиска
     */
    public static function autocomplete(string $query, int $limit = 10): array
    {
        if (strlen($query) < 2) {
            return [];
        }
        
        try {
            $response = self::getClient()->search([
                'index' => SearchConfig::PRODUCTS_INDEX,
                'body' => [
                    'size' => 0, // Нам не нужны сами документы
                    'suggest' => [
                        'product_suggest' => [
                            'text' => $query,
                            'completion' => [
                                'field' => 'suggest',
                                'size' => $limit,
                                'skip_duplicates' => true,
                                'fuzzy' => [
                                    'fuzziness' => 'AUTO'
                                ]
                            ]
                        ]
                    ]
                ]
            ]);
            
            $suggestions = [];
            
            if (isset($response['suggest']['product_suggest'][0]['options'])) {
                foreach ($response['suggest']['product_suggest'][0]['options'] as $option) {
                    $suggestions[] = [
                        'text' => $option['text'],
                        'score' => $option['_score'],
                        'type' => self::detectSuggestionType($option['text'])
                    ];
                }
            }
            
            return $suggestions;
            
        } catch (\Exception $e) {
            Logger::error('Autocomplete failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Получить один товар по ID
     */
    public static function getProduct($id, int $cityId = 1, ?int $userId = null): ?array
    {
        try {
            // Ищем по external_id или product_id
            $response = self::getClient()->search([
                'index' => SearchConfig::PRODUCTS_INDEX,
                'body' => [
                    'size' => 1,
                    'query' => [
                        'bool' => [
                            'should' => [
                                ['term' => ['product_id' => $id]],
                                ['term' => ['external_id.keyword' => $id]]
                            ],
                            'minimum_should_match' => 1
                        ]
                    ]
                ]
            ]);
            
            if (empty($response['hits']['hits'])) {
                return null;
            }
            
            $product = $response['hits']['hits'][0]['_source'];
            
            // Обогащаем динамическими данными
            $products = self::enrichWithDynamicData([$product], $cityId, $userId);
            
            return $products[0] ?? null;
            
        } catch (\Exception $e) {
            Logger::error('Get product failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    // === Приватные методы ===
    
    /**
     * Валидация параметров поиска
     */
    private static function validateSearchParams(array $params): array
    {
        $defaults = [
            'query' => '',
            'page' => 1,
            'limit' => 20,
            'sort' => 'relevance',
            'city_id' => 1,
            'filters' => []
        ];
        
        $params = array_merge($defaults, $params);
        
        // Проверяем лимиты
        $params['page'] = max(1, min(1000, (int)$params['page']));
        $params['limit'] = max(1, min(100, (int)$params['limit']));
        $params['city_id'] = max(1, (int)$params['city_id']);
        
        // Очищаем поисковый запрос
        $params['query'] = trim(strip_tags($params['query']));
        
        return $params;
    }
    
    /**
     * Построение поискового запроса для OpenSearch
     */
    private static function buildSearchQuery(array $params): array
    {
        $from = ($params['page'] - 1) * $params['limit'];
        
        $body = [
            'size' => $params['limit'],
            'from' => $from,
            'track_total_hits' => true
        ];
        
        // Основной запрос
        $must = [];
        $filter = [];
        
        if (!empty($params['query'])) {
            $queryType = self::detectQueryType($params['query']);
            
            switch ($queryType) {
                case 'code':
                    $must[] = self::buildCodeQuery($params['query']);
                    break;
                case 'brand':
                    $must[] = self::buildBrandQuery($params['query']);
                    break;
                default:
                    $must[] = self::buildTextQuery($params['query']);
            }
            
            // Подсветка результатов
            $body['highlight'] = self::getHighlightConfig();
        }
        
        // Фильтры
        if (!empty($params['filters'])) {
            $filter = self::buildFilters($params['filters']);
        }
        
        // Формируем финальный запрос
        if (!empty($must) || !empty($filter)) {
            $body['query'] = ['bool' => []];
            if (!empty($must)) $body['query']['bool']['must'] = $must;
            if (!empty($filter)) $body['query']['bool']['filter'] = $filter;
        } else {
            $body['query'] = ['match_all' => new \stdClass()];
        }
        
        // Сортировка
        $body['sort'] = self::buildSort($params['sort'], !empty($params['query']));
        
        // Агрегации для фильтров
        $body['aggs'] = self::getAggregations();
        
        return $body;
    }
    
    /**
     * Определение типа запроса
     */
    private static function detectQueryType(string $query): string
    {
        // Код товара
        if (preg_match('/^[A-Za-z0-9\-\.\/\_\s]+$/u', $query) && strlen($query) <= 30) {
            return 'code';
        }
        
        // Бренд
        $brands = ['schneider', 'legrand', 'abb', 'iek', 'ekf', 'dkc'];
        $queryLower = mb_strtolower($query);
        
        foreach ($brands as $brand) {
            if (strpos($queryLower, $brand) !== false) {
                return 'brand';
            }
        }
        
        return 'text';
    }
    
    /**
     * Запрос для поиска по коду
     */
    private static function buildCodeQuery(string $code): array
    {
        return [
            'bool' => [
                'should' => [
                    ['term' => ['external_id.keyword' => ['value' => strtolower($code), 'boost' => 1000]]],
                    ['term' => ['sku.keyword' => ['value' => strtolower($code), 'boost' => 900]]],
                    ['prefix' => ['external_id.keyword' => ['value' => strtolower($code), 'boost' => 500]]],
                    ['match' => ['external_id.autocomplete' => ['query' => $code, 'boost' => 100]]],
                    ['match_phrase' => ['name' => ['query' => $code, 'boost' => 50, 'slop' => 1]]]
                ],
                'minimum_should_match' => 1
            ]
        ];
    }
    
    /**
     * Запрос для поиска по бренду
     */
    private static function buildBrandQuery(string $query): array
    {
        return [
            'multi_match' => [
                'query' => $query,
                'fields' => ['brand_name^10', 'name^3', 'description'],
                'type' => 'best_fields',
                'operator' => 'and',
                'fuzziness' => 'AUTO'
            ]
        ];
    }
    
    /**
     * Обычный текстовый поиск
     */
    private static function buildTextQuery(string $query): array
    {
        return [
            'multi_match' => [
                'query' => $query,
                'fields' => [
                    'name^10',
                    'name.autocomplete^5',
                    'description^3',
                    'brand_name^8',
                    'series_name^6',
                    'categories^4',
                    'external_id^7',
                    'sku^6'
                ],
                'type' => 'best_fields',
                'operator' => 'or',
                'minimum_should_match' => '70%',
                'fuzziness' => 'AUTO'
            ]
        ];
    }
    
    /**
     * Построение фильтров
     */
    private static function buildFilters(array $filters): array
    {
        $result = [];
        
        if (!empty($filters['brand_name'])) {
            $result[] = ['term' => ['brand_name.keyword' => $filters['brand_name']]];
        }
        
        if (!empty($filters['category'])) {
            $result[] = ['match' => ['categories' => $filters['category']]];
        }
        
        if (!empty($filters['price_min']) || !empty($filters['price_max'])) {
            $range = ['range' => ['base_price' => []]];
            if (!empty($filters['price_min'])) {
                $range['range']['base_price']['gte'] = (float)$filters['price_min'];
            }
            if (!empty($filters['price_max'])) {
                $range['range']['base_price']['lte'] = (float)$filters['price_max'];
            }
            $result[] = $range;
        }
        
        if (isset($filters['in_stock'])) {
            $result[] = ['term' => ['in_stock' => (bool)$filters['in_stock']]];
        }
        
        return $result;
    }
    
    /**
     * Построение сортировки
     */
    private static function buildSort(string $sort, bool $hasQuery): array
    {
        switch ($sort) {
            case 'name':
                return [['name.keyword' => 'asc']];
            case 'price_asc':
                return [['base_price' => 'asc']];
            case 'price_desc':
                return [['base_price' => 'desc']];
            case 'popularity':
                return [['orders_count' => 'desc'], ['name.keyword' => 'asc']];
            case 'relevance':
            default:
                return $hasQuery 
                    ? [['_score' => 'desc'], ['name.keyword' => 'asc']]
                    : [['name.keyword' => 'asc']];
        }
    }
    
    /**
     * Конфигурация подсветки результатов
     */
    private static function getHighlightConfig(): array
    {
        return [
            'pre_tags' => ['<mark>'],
            'post_tags' => ['</mark>'],
            'fields' => [
                'name' => ['number_of_fragments' => 0],
                'description' => ['fragment_size' => 150, 'number_of_fragments' => 2],
                'brand_name' => ['number_of_fragments' => 0],
                'categories' => ['number_of_fragments' => 0]
            ]
        ];
    }
    
    /**
     * Агрегации для фильтров
     */
    private static function getAggregations(): array
    {
        return [
            'brands' => [
                'terms' => [
                    'field' => 'brand_name.keyword',
                    'size' => 50,
                    'order' => ['_count' => 'desc']
                ]
            ],
            'categories' => [
                'terms' => [
                    'field' => 'categories.keyword',
                    'size' => 30
                ]
            ],
            'price_stats' => [
                'stats' => ['field' => 'base_price']
            ]
        ];
    }
    
    /**
     * Обработка результатов поиска
     */
    private static function processSearchResults(array $response, array $params): array
    {
        $products = [];
        
        foreach ($response['hits']['hits'] as $hit) {
            $product = $hit['_source'];
            $product['_score'] = $hit['_score'] ?? 0;
            
            if (isset($hit['highlight'])) {
                $product['_highlight'] = $hit['highlight'];
            }
            
            $products[] = $product;
        }
        
        return [
            'products' => $products,
            'total' => $response['hits']['total']['value'] ?? 0,
            'page' => $params['page'],
            'limit' => $params['limit'],
            'pages' => ceil(($response['hits']['total']['value'] ?? 0) / $params['limit']),
            'aggregations' => self::formatAggregations($response['aggregations'] ?? [])
        ];
    }
    
    /**
     * Форматирование агрегаций
     */
    private static function formatAggregations(array $aggregations): array
    {
        $result = [];
        
        if (isset($aggregations['brands']['buckets'])) {
            $result['brands'] = array_map(function($bucket) {
                return [
                    'name' => $bucket['key'],
                    'count' => $bucket['doc_count']
                ];
            }, $aggregations['brands']['buckets']);
        }
        
        if (isset($aggregations['categories']['buckets'])) {
            $result['categories'] = array_map(function($bucket) {
                return [
                    'name' => $bucket['key'],
                    'count' => $bucket['doc_count']
                ];
            }, $aggregations['categories']['buckets']);
        }
        
        if (isset($aggregations['price_stats'])) {
            $result['price_range'] = [
                'min' => $aggregations['price_stats']['min'] ?? 0,
                'max' => $aggregations['price_stats']['max'] ?? 0
            ];
        }
        
        return $result;
    }
    
    /**
     * Обогащение товаров динамическими данными
     */
    private static function enrichWithDynamicData(array $products, int $cityId, ?int $userId): array
    {
        if (empty($products)) {
            return $products;
        }
        
        $productIds = array_column($products, 'product_id');
        
        $dynamicService = new DynamicProductDataService();
        $dynamicData = $dynamicService->getProductsDynamicData($productIds, $cityId, $userId);
        
        foreach ($products as &$product) {
            $pid = $product['product_id'];
            
            if (isset($dynamicData[$pid])) {
                $product['price'] = $dynamicData[$pid]['price'] ?? null;
                $product['stock'] = $dynamicData[$pid]['stock'] ?? ['quantity' => 0];
                $product['delivery'] = $dynamicData[$pid]['delivery'] ?? ['text' => 'Уточняйте'];
                $product['available'] = $dynamicData[$pid]['available'] ?? false;
            }
        }
        
        return $products;
    }
    
    /**
     * Определение типа автодополнения
     */
    private static function detectSuggestionType(string $text): string
    {
        if (preg_match('/^[A-Za-z0-9\-\.\/\_]+$/', $text)) {
            return 'code';
        }
        
        $brands = ['schneider', 'legrand', 'abb', 'iek'];
        foreach ($brands as $brand) {
            if (stripos($text, $brand) !== false) {
                return 'brand';
            }
        }
        
        return 'text';
    }
    
    /**
     * Логирование поиска для аналитики
     */
    private static function logSearch(array $params, int $resultsCount): void
    {
        QueueService::push('metrics', [
            'type' => MetricsService::METRIC_SEARCH,
            'data' => [
                'query' => $params['query'],
                'filters' => $params['filters'],
                'results_count' => $resultsCount,
                'city_id' => $params['city_id']
            ],
            'value' => $resultsCount
        ], QueueService::PRIORITY_LOW);
    }
    
    /**
     * Получить ключ кеша
     */
    private static function getCacheKey(array $params): string
    {
        return 'search:' . md5(json_encode($params));
    }
    
    /**
     * Получить клиент OpenSearch
     */
    private static function getClient(): \OpenSearch\Client
    {
        if (self::$client === null) {
            self::$client = SearchConfig::getClient();
        }
        
        return self::$client;
    }
}