<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Manufacture\Part\UseCase\Admin\AddProduct;


use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ManufacturePartProductsForm extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder->add('product', HiddenType::class);

        $builder->get('product')->addModelTransformer(
            new CallbackTransformer(
                function($product) {
                    return $product instanceof ProductEventUid ? $product->getValue() : $product;
                },
                function($product) {
                    return $product ? new ProductEventUid($product) : null;
                }
            )
        );


        $builder->add('offer', HiddenType::class);

        $builder->get('offer')->addModelTransformer(
            new CallbackTransformer(
                function($offer) {
                    return $offer instanceof ProductOfferUid ? $offer->getValue() : $offer;
                },
                function($offer) {
                    return $offer ? new ProductOfferUid($offer) : null;
                }
            )
        );


        $builder->add('variation', HiddenType::class);

        $builder->get('variation')->addModelTransformer(
            new CallbackTransformer(
                function($variation) {
                    return $variation instanceof ProductVariationUid ? $variation->getValue() : $variation;
                },
                function($variation) {
                    return $variation ? new ProductVariationUid($variation) : null;
                }
            )
        );


        $builder->add('modification', HiddenType::class);

        $builder->get('modification')->addModelTransformer(
            new CallbackTransformer(
                function($modification) {
                    return $modification instanceof ProductModificationUid ? $modification->getValue() : $modification;
                },
                function($modification) {
                    return $modification ? new ProductModificationUid($modification) : null;
                }
            )
        );


        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event): void {

            /** @var ManufacturePartProductsDTO $data */
            $data = $event->getData();

            $limit['min'] = 1;

            if($data?->getTotal())
            {
                $limit['max'] = $data->getTotal();
            }

            $form = $event->getForm();
            $form->add('total', TextType::class, ['attr' => $limit]);

        });


        /* Сохранить ******************************************************/

        /** Чтобы скрывать submit в форме добавления selection товаров */
        $show_submit = $builder->getOption('show_submit');

        /** Если в ManufactureSelectionPartProductsForm в entry_options задана эта опция со значением true, то скроем этот элемент */
        if ($show_submit)
        {
            $builder->add(
                'manufacture_part_products',
                SubmitType::class,
                ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary text-nowrap']]
            );
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ManufacturePartProductsDTO::class,
            'method' => 'POST',
            'attr' => ['class' => 'w-100'],
            'show_submit' => true,
        ]);
    }
}