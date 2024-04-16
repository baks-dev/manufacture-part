<?php
/*
 *  Copyright 2022.  Baks.dev <admin@baks.dev>
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *   limitations under the License.
 *
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
    private RequestStack $request;

    private OfferFieldsCategoryChoiceInterface $offerChoice;
    private VariationFieldsCategoryChoiceInterface $variationChoice;
    private ModificationFieldsCategoryChoiceInterface $modificationChoice;
    private FieldsChoice $choice;

    public function __construct(
        RequestStack $request,

        OfferFieldsCategoryChoiceInterface $offerChoice,
        VariationFieldsCategoryChoiceInterface $variationChoice,
        ModificationFieldsCategoryChoiceInterface $modificationChoice,
        FieldsChoice $choice,

    )
    {
        $this->request = $request;
        $this->offerChoice = $offerChoice;
        $this->variationChoice = $variationChoice;
        $this->modificationChoice = $modificationChoice;
        $this->choice = $choice;
    }

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

                    $offerField = $this->offerChoice->findByCategory($Category);

                    if($offerField)
                    {
                        $inputOffer = $this->choice->getChoice($offerField->getField());

                        if($inputOffer)
                        {
                            $builder->add('offer',

                                $inputOffer->form(),
                                [
                                    'label' => $offerField->getOption(),
                                    'priority' => 200,
                                    'required' => false,
                                ]
                            );


                            /** Множественные варианты торгового предложения */

                            $variationField = $this->variationChoice->getVariationFieldType($offerField);

                            if($variationField)
                            {

                                $inputVariation = $this->choice->getChoice($variationField->getField());

                                if($inputVariation)
                                {
                                    $builder->add('variation',
                                        $inputVariation->form(),
                                        [
                                            'label' => $variationField->getOption(),
                                            'priority' => 199,
                                            'required' => false,
                                        ]
                                    );

                                    /** Модификации множественных вариантов торгового предложения */

                                    $modificationField = $this->modificationChoice->findByVariation($variationField);


                                    if($modificationField)
                                    {
                                        $inputModification = $this->choice->getChoice($modificationField->getField());

                                        if($inputModification)
                                        {
                                            $builder->add('modification',
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
        $resolver->setDefaults
        (
            [
                'data_class' => PartProductFilterDTO::class,
                'validation_groups' => false,
                'method' => 'POST',
                'attr' => ['class' => 'w-100'],
            ]
        );
    }

}
