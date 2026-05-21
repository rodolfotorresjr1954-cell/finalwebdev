<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture implements FixtureGroupInterface
{
    public const MENU_CATEGORIES = ['burger', 'fries', 'milktea'];

    public static function getGroups(): array
    {
        return ['category'];
    }

    public function load(ObjectManager $manager): void
    {
        $repo = $manager->getRepository(Category::class);

        foreach (self::MENU_CATEGORIES as $name) {
            $existing = $repo->findOneBy(['name' => $name]);
            if ($existing !== null) {
                continue;
            }

            $category = new Category();
            $category->setName($name);
            $manager->persist($category);
        }

        $manager->flush();
    }
}
