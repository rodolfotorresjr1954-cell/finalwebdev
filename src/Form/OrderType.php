<?php

namespace App\Form;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\Customer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $quickCreate = $options['quick_create'];

        if (!$quickCreate) {
            $builder
                ->add('Name')
                ->add('createAt', DateTimeType::class, [
                    'widget' => 'single_text',
                ]);
        }

        $builder
            ->add('Total')
            ->add('Status', ChoiceType::class, [
                'choices' => [
                    'Pending' => 'Pending',
                    'Completed' => 'Completed',
                ],
                'placeholder' => 'Select status',
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'choices' => [
                    'Cash' => 'cash',
                    'GCash' => 'gcash',
                    'ATM' => 'atm',
                ],
                'placeholder' => 'Select payment method',
                'required' => false,
            ]);

        if (!$quickCreate) {
            $customerFieldOptions = [
                'class' => Customer::class,
                'placeholder' => 'Select a customer',
                'choice_label' => 'name',
                'required' => false,
            ];
            if ($options['customer_disabled']) {
                $customerFieldOptions['disabled'] = true;
            }
            $builder->add('Customer', EntityType::class, $customerFieldOptions);
        }

        $builder->add('products', EntityType::class, [
            'class' => Product::class,
            'multiple' => false,
            'expanded' => false,
            'required' => $quickCreate,
            'placeholder' => $quickCreate ? 'Choose a product (grouped by category)' : 'Select a product',
            'choice_label' => 'name',
            'group_by' => function (?Product $product): string {
                if ($product === null) {
                    return 'Other';
                }
                $cat = $product->getCategory();

                return $cat && $cat->getName() ? (string) $cat->getName() : 'Other';
            },
            'attr' => [
                'data-order-product-select' => '1',
            ],
        ]);

        $builder->get('products')->addModelTransformer(
            new CallbackTransformer(
                function ($products): ?Product {
                    if ($products instanceof Collection) {
                        return $products->first() ?: null;
                    }

                    return null;
                },
                function ($product) {
                    $collection = new ArrayCollection();
                    if ($product) {
                        $collection->add($product);
                    }

                    return $collection;
                }
            )
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
            'quick_create' => false,
            'customer_disabled' => false,
        ]);
        $resolver->setAllowedTypes('quick_create', 'bool');
        $resolver->setAllowedTypes('customer_disabled', 'bool');
    }
}
