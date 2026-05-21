<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use App\Repository\CategoryRepository;
use Symfony\Component\Form\Extension\Core\Type\TextType;
// NOTE: Intentionally not using FileConstraint here because some environments
// lack php_fileinfo and Symfony may still try to run the MIME guesser.

class ProductType extends AbstractType
{
    /**
     * Preset catalog titles for auto category mapping (product name is a free-text field).
     */
    public const PRESET_NAME_CHOICES = [
        'Classic Cheeseburger' => 'Classic Cheeseburger',
        'Hamburger' => 'Hamburger',
        'Double Cheeseburger' => 'Double Cheeseburger',
        'Bacon Cheeseburger' => 'Bacon Cheeseburger',
        'Crispy Chicken Burger' => 'Crispy Chicken Burger',
        'Spicy Chicken Burger' => 'Spicy Chicken Burger',
        'Chicken Fillet Burger' => 'Chicken Fillet Burger',
        'Chicken BBQ Burger' => 'Chicken BBQ Burger',
        'Mushroom Swiss Burger' => 'Mushroom Swiss Burger',
        'French Fries' => 'French Fries',
        'Curly Fries' => 'Curly Fries',
        'Cheese Fries' => 'Cheese Fries',
        'Choco Milk Tea' => 'Choco Milk Tea',
        'Milk Tea' => 'Milk Tea',
        'Iced Coffee' => 'Iced Coffee',
        'Lemonade' => 'Lemonade',
        'Soft Drink' => 'Soft Drink',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => [
                    'class' => 'ue-input',
                    'placeholder' => 'Enter product name',
                    'data-product-name-input' => '1',
                ],
            ])
            ->add('description')
            ->add('price')
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a category',
                'required' => false,
                'query_builder' => fn (CategoryRepository $repository) => $repository->createMenuCategoriesQueryBuilder(),
                'attr' => ['data-product-category-select' => '1'],
            ])
            ->add('datetime', DateTimeType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'js-datetimepicker'],
                'required' => false,
            ])
            ->add('image', FileType::class, [
                'label' => 'Upload Product Image',
                'mapped' => false,
                'required' => false,
                // Do not use FileConstraint here.
                // In some environments without php_fileinfo, Symfony may still call the MIME type guesser
                // and throw: "Unable to guess the MIME type as no guessers are available".
                // We'll validate extension/size in the controller instead.
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
