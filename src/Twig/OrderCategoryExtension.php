<?php

namespace App\Twig;

use App\Entity\Order;
use App\Service\ProductMenuCategoryService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class OrderCategoryExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProductMenuCategoryService $productMenuCategoryService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('order_category_labels', [$this->productMenuCategoryService, 'getLabelsForOrder']),
        ];
    }
}
