<?php

namespace App;

require 'vendor/autoload.php';

class Scrape
{
    private array $products = [];

    public function run(): void
    {
        // Fetch the first page to determine the number of pages
        $document = ScrapeHelper::fetchDocument('https://www.magpiehq.com/developer-challenge/smartphones');
        $pages = $document->filter('#pages a')->count();

        // Scrape products from all pages
        for ($i = 1; $i <= $pages; $i++) {
            $url = 'https://www.magpiehq.com/developer-challenge/smartphones?page=' . $i;
            $document = ScrapeHelper::fetchDocument($url);
            $this->products = array_merge($this->products, ScrapeHelper::scrapePage($document));
        }

        // Remove duplicates
        $this->products = array_map('unserialize', array_unique(array_map('serialize', $this->products)));

        // Save results to a JSON file
        file_put_contents('output.json', json_encode($this->products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

$scrape = new Scrape();
$scrape->run();
