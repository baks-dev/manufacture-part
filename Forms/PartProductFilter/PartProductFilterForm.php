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

namespace BaksDev\Manufacture\Part\Forms\PartProductFilter;

use BaksDev\Core\Services\Fields\FieldsChoice;
use BaksDev\Products\Category\Repository\ModificationFieldsCategoryChoice\ModificationFieldsCategoryChoiceInterface;
use BaksDev\Products\Category\Repository\OfferFieldsCategoryChoice\OfferFieldsCategoryChoiceInterface;
use BaksDev\Products\Category\Repository\VariationFieldsCategoryChoice\VariationFieldsCategoryChoiceInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PartProductFilterForm extends AbstractType
{
    public function __construct(
        private readonly RequestStack $request,
        private readonly OfferFieldsCategoryChoiceInterface $offerChoice,
        private readonly VariationFieldsCategoryChoiceInterface $variationChoice,
        private readonly ModificationFieldsCategoryChoiceInterface $modificationChoice,
        private readonly FieldsChoice $choice,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event): void {
                /** @var PartProductFilterDTO $data */
                $data = $event->getData();

                $this->request->getSession()->set(PartProductFilterDTO::offer, $data->getOffer());
                $this->request->getSession()->set(PartProductFilterDTO::variation, $data->getVariation());
                $this->request->getSession()->set(PartProductFilterDTO::modification, $data->getModification());

            }
        );


        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function(FormEvent $event): void {

                /** @var PartProductFilterDTO $data */

                $data = $event->getData();
                $builder = $event->getForm();

                $Category = $data->getCategory();

                if($Category)
                {
                    /** Торговое предложение раздела */

                    $offerField = $this->offerChoice
                        ->category($Category)
                        ->findAllCategoryProductOffers();

                    if($offerField)
                    {
                        $inputOffer = $this->choice->getChoice($offerField->getField());

                        if($inputOffer)
                        {
                            $builder->add(
                                'offer',
                                $inputOffer->form(),
                                [
                                    'label' => $offerField->getOption(),
                                    'priority' => 200,
                                    'required' => false,
                                ]
                            );


                            /** Множественные варианты торгового предложения */
                            $variationField = $this->variationChoice
                                ->offer($offerField)
                                ->findCategoryProductVariation();

                            if($variationField)
                            {

                                $inputVariation = $this->choice->getChoice($variationField->getField());

                                if($inputVariation)
                                {
                                    $builder->add(
                                        'variation',
                                        $inputVariation->form(),
                                        [
                                            'label' => $variationField->getOption(),
                                            'priority' => 199,
                                            'required' => false,
                                        ]
                                    );

                                    /** Модификации множественных вариантов торгового предложения */

                                    $modificationField = $this->modificationChoice
                                        ->variation($variationField)
                                        ->findAllModification();


                                    if($modificationField)
                                    {
                                        $inputModification = $this->choice->getChoice($modificationField->getField());

                                        if($inputModification)
                                        {
                                            $builder->add(
                                                'modification',
                                                $inputModification->form(),
                                                [
                                                    'label' => $modificationField->getOption(),
                                                    'priority' => 198,
                                                    'required' => false,
                                                ]
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                else
                {
                    $data->setOffer(null);
                    $data->setVariation(null);
                    $data->setModification(null);
                }
            }
        );

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => PartProductFilterDTO::class,
                'validation_groups' => false,
                'method' => 'POST',
                'attr' => ['class' => 'w-100'],
            ]
        );
    }

}
