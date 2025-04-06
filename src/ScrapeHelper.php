<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeHelper
{
    private const BASE_IMG_URL = 'https://www.magpiehq.com/developer-challenge';

    public static function fetchDocument(string $url): Crawler
    {
        $client = new Client();

        try {
            $response = $client->get($url);
            return new Crawler($response->getBody()->getContents(), $url);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                echo "Error: URL not found - $url\n";
            } else {
                echo "Error: " . $e->getMessage() . "\n";
            }
            exit;
        }
    }

    public static function scrapePage(Crawler $document): array
    {
        $products = [];

        // Get product data from each product node
        $document->filter('.product')->each(function (Crawler $node) use (&$products) {
            $title = self::getProductTitle($node);
            $price = self::getProductPrice($node);
            $imageUrl = self::getProductImageUrl($node);
            $capacityMB = self::getProductCapacity($node);
            $availabilityText = self::getAvailabilityText($node);
            $isAvailable = self::isProductAvailable($availabilityText);
            $shippingText = self::getShippingText($node);
            $shippingDate = self::getShippingDate($shippingText);

            // Handle color variants
            $node->filter('[data-colour]')->each(function (Crawler $colorNode) use (
                $title,
                $price,
                $imageUrl,
                $capacityMB,
                $availabilityText,
                $isAvailable,
                $shippingText,
                $shippingDate,
                &$products
            ) {
                $colour = $colorNode->attr('data-colour') ?? 'Unknown';
                $products[] = new Product(
                    $title,
                    $price,
                    $imageUrl,
                    $capacityMB,
                    $colour,
                    $availabilityText,
                    $isAvailable,
                    $shippingText,
                    $shippingDate
                );
            });
        });

        return $products;
    }

    private static function getProductTitle(Crawler $node): string
    {
        try {
            $name = $node->filter('.product-name')->text();
            $capacity = $node->filter('.product-capacity')->text();
            return trim($name) . ' ' . trim($capacity);
        } catch (\Exception $e) {
            return 'Unknown Product';
        }
    }

    private static function getProductPrice(Crawler $node): float
    {
        try {
            $priceText = $node->filter('.text-lg')->text();
            return (float) filter_var($priceText, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    private static function getProductImageUrl(Crawler $node): string
    {
        try {
            $src = $node->filter('img')->attr('src');
            $sanitizedSrc = str_replace('../', '', $src);
            return rtrim(self::BASE_IMG_URL, '/') . '/' . ltrim($sanitizedSrc, '/');
        } catch (\Exception $e) {
            return self::BASE_IMG_URL . '/images/default.png';
        }
    }

    private static function getProductCapacity(Crawler $node): int
    {
        try {
            $capacityGB = (int) filter_var($node->filter('.product-capacity')->text(), FILTER_SANITIZE_NUMBER_INT);
            return $capacityGB * 1024;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private static function getAvailabilityText(Crawler $node): string
    {
        try {
            $text = trim($node->filter('.text-sm')->first()->text());
            return preg_replace('/^Availability:\s*/i', '', $text);
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private static function isProductAvailable(string $availabilityText): bool
    {
        return stripos($availabilityText, 'In Stock') !== false;
    }

    private static function getShippingText(Crawler $node): string
    {
        try {
            $text = $node->filter('.text-sm')->last()->text() ?? '';
            return preg_replace('/^Delivery\s*by\s*/i', '', $text);
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private static function getShippingDate(string $shippingText): string
    {
        try {
            if (preg_match('/\d{1,2}(st|nd|rd|th)?\s\w+\s\d{4}/', $shippingText, $matches)) {
                return date('Y-m-d', strtotime($matches[0]));
            }
        } catch (\Exception $e) {
            // Do nothing
        }
        return '';
    }
}
