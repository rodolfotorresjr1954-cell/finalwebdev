<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Product;

/**
 * Maps catalog items to menu buckets for order summaries: Burger, Fries, Drinks, or Other.
 */
final class ProductMenuCategoryService
{
    private const LABEL_ORDER = ['Burger' => 0, 'Fries' => 1, 'Drinks' => 2, 'Other' => 3];

    /**
     * Infer Burger / Fries / Drinks / Other from a free-text product title (no DB).
     *
     * @return list<string>
     */
    public function inferLabelsFromTitle(string $title): array
    {
        $name = mb_strtolower(trim($title));

        if ($name === '') {
            return ['Other'];
        }

        $matched = [];

        if (preg_match('/\b(burger|burgers)\b/u', $name)) {
            $matched['Burger'] = true;
        }

        if (preg_match('/\bfries\b|french\s+fries|french-fries/u', $name)) {
            $matched['Fries'] = true;
        }

        if ($this->nameLooksLikeDrink($name)) {
            $matched['Drinks'] = true;
        }

        if ($matched === []) {
            return ['Other'];
        }

        return $this->sortLabelKeys(array_keys($matched));
    }

    /**
     * @return list<string> One or more labels (e.g. combo names may match both Burger and Fries)
     */
    public function categorizeProduct(Product $product): array
    {
        $labels = $this->inferLabelsFromTitle($product->getName() ?? '');
        if ($labels !== ['Other']) {
            return $labels;
        }

        $category = $product->getCategory();
        if ($category !== null) {
            $cn = mb_strtolower(trim($category->getName() ?? ''));
            if ($cn !== '' && preg_match('/drink|beverage|tea|coffee|juice|soda|shake|smoothie|milk/i', $cn)) {
                return ['Drinks'];
            }
            if (preg_match('/fries|side|snack/i', $cn)) {
                return ['Fries'];
            }
            if (preg_match('/burger/i', $cn)) {
                return ['Burger'];
            }
        }

        return ['Other'];
    }

    public function getLabelsForOrder(Order $order): string
    {
        $set = [];
        foreach ($order->getProducts() as $product) {
            foreach ($this->categorizeProduct($product) as $label) {
                $set[$label] = true;
            }
        }

        if ($set === []) {
            return '—';
        }

        return implode(', ', $this->sortLabelKeys(array_keys($set)));
    }

    private function nameLooksLikeDrink(string $name): bool
    {
        return (bool) preg_match(
            '/\b('
            . 'milk\s*tea|bubble\s*tea|iced\s*tea|choco(?:late)?\s+milk\s+tea|milk\s+tea|'
            . 'tea|coffee|latte|espresso|mocha|frapp[eé]|frappe|'
            . 'juice|soda|cola|soft\s*drink|smoothie|shake|shakes|boba|lemonade|'
            . 'drinks?|float|cooler'
            . ')\b/u',
            $name
        );
    }

    /**
     * @param list<string> $labels
     *
     * @return list<string>
     */
    private function sortLabelKeys(array $labels): array
    {
        $labels = array_values(array_unique($labels));
        usort($labels, function (string $a, string $b): int {
            $ia = self::LABEL_ORDER[$a] ?? 99;
            $ib = self::LABEL_ORDER[$b] ?? 99;

            return $ia <=> $ib;
        });

        return $labels;
    }
}
