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

namespace BaksDev\Manufacture\Part\UseCase\Admin\NewEdit;


use BaksDev\Manufacture\Part\Type\Complete\ManufacturePartComplete;
use BaksDev\Products\Category\Repository\CategoryChoice\CategoryChoiceInterface;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Users\UsersTable\Repository\Actions\UsersTableActionsChoice\UsersTableActionsChoiceInterface;
use BaksDev\Users\UsersTable\Type\Actions\Event\UsersTableActionsEventUid;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ManufacturePartForm extends AbstractType
{
    public function __construct(
        private readonly UsersTableActionsChoiceInterface $usersTableActionsChoice,
        private readonly CategoryChoiceInterface $categoryChoice,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /**
         * Категория производства
         */

        $builder
            ->add('category', ChoiceType::class, [
                'choices' => $this->categoryChoice->findAll(),
                'choice_value' => function(?CategoryProductUid $category) {
                    return $category?->getValue();
                },
                'choice_label' => function(CategoryProductUid $category) {
                    return (is_int($category->getAttr()) ? str_repeat(' - ', $category->getAttr() - 1) : '').$category->getOptions();
                },

                'label' => false,
                'expanded' => false,
                'multiple' => false,
                'required' => true,
            ]);


        $builder->get('category')->addModelTransformer(
            new CallbackTransformer(
                function($category) {
                    return $category instanceof CategoryProductUid ? $category->getValue() : $category;
                },
                function($category) {
                    return $category instanceof CategoryProductUid ? $category : new CategoryProductUid($category);
                }
            )
        );

        $formModifier = function(FormInterface $form, ?CategoryProductUid $category = null): void {

            /** @var ManufacturePartDTO $ManufacturePartDTO */
            $ManufacturePartDTO = $form->getData();

            $choice = !$category ? [] : $this->usersTableActionsChoice
                ->forCategory($category)
                ->getCollection();

            $form
                ->add('action', ChoiceType::class, [
                    'choices' => $choice,
                    'choice_value' => function(?UsersTableActionsEventUid $action) {
                        return $action?->getValue();
                    },
                    'choice_label' => function(UsersTableActionsEventUid $action) {

                        return $action->getAttr();
                    },

                    'expanded' => false,
                    'multiple' => false,
                    'required' => true,
                ]);
        };


        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) use ($formModifier): void {

            /** @var ManufacturePartDTO $ManufacturePartDTO */
            $ManufacturePartDTO = $event->getData();

            if($ManufacturePartDTO->getFixed())
            {
                $form = $event->getForm();

                if($ManufacturePartDTO->getCategory())
                {
                    $formModifier($event->getForm(), $ManufacturePartDTO->getCategory());
                }
                else
                {
                    $form
                        ->add('action', ChoiceType::class, [
                            'choices' => [],
                            'expanded' => false,
                            'multiple' => false,
                            'required' => true,
                            'disabled' => true
                        ]);
                }

            }
        });


        $builder->get('category')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) use ($formModifier): void {
                $data = $event->getData();
                $formModifier($event->getForm()->getParent(), $data ? new CategoryProductUid($data) : null);
            }
        );


        $builder
            ->add('complete', ChoiceType::class, [
                'choices' => ManufacturePartComplete::cases(),
                'choice_value' => function(?ManufacturePartComplete $complete) {
                    return $complete?->getActionCompleteValue();
                },
                'choice_label' => function(ManufacturePartComplete $complete) {
                    return $complete->getActionCompleteValue();
                },
                'translation_domain' => 'manufacture.complete',
                'expanded' => false,
                'multiple' => false,
                'required' => true,
            ]);


        $builder->add('comment', TextareaType::class, ['required' => false]);

        /* Сохранить ******************************************************/
        $builder->add(
            'manufacture_part',
            SubmitType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ManufacturePartDTO::class,
            'method' => 'POST',
            'attr' => ['class' => 'w-100'],
        ]);
    }
}