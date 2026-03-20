<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Manufacture\Part\Messenger\BarcodeScanner;

use BaksDev\Auth\Telegram\Repository\ActiveProfileByAccountTelegram\ActiveProfileByAccountTelegramInterface;
use BaksDev\Barcode\Messenger\ScannerMessage;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\Invariable\ManufacturePartInvariable;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Repository\ActiveWorkingManufacturePart\ActiveWorkingManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\AllWorkingByManufacturePart\AllWorkingByManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\CurrentManufacturePartEvent\CurrentManufacturePartEventInterface;
use BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\ProductsByManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\ProductsByManufacturePartResult;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionForm;
use BaksDev\Telegram\Api\TelegramSendMessages;
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Request\Type\TelegramRequestIdentifier;
use BaksDev\Users\UsersTable\Type\Actions\Working\UsersTableActionsWorkingUid;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final class ScannerManufacturePartWorking
{
    public const string KEY = 'UfjQCCzp';

    private TelegramRequestIdentifier $request;

    public function __construct(
        #[AutowireIterator('baks.reference.choice')] private readonly iterable $reference,
        #[Target('manufacturePartTelegramLogger')] private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveProfileByAccountTelegramInterface $activeProfileByAccountTelegram,
        private readonly ActiveWorkingManufacturePartInterface $activeWorkingManufacturePart,
        private readonly TelegramSendMessages $telegramSendMessage,
        private readonly AllWorkingByManufacturePartInterface $allWorkingByManufacturePart,
        private readonly ProductsByManufacturePartInterface $ProductsByManufacturePart,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly CurrentManufacturePartEventInterface $CurrentManufacturePartEventRepository,
        private readonly FormFactoryInterface $formFactory,
        private readonly RouterInterface $router,
        private readonly Environment $twig,
    ) {}


    /**
     * Получаем состояние партии отправляем соответствующие действия
     */
    public function __invoke(ScannerMessage $message): void
    {
        if(false === empty($message->getContent()))
        {
            return;
        }


        /**
         * Получаем заявку на производство
         */

        $ManufacturePartUid = new ManufacturePartUid($message->getIdentifier());

        $CurrentManufacturePartEvent = $this->CurrentManufacturePartEventRepository
            ->forPart($ManufacturePartUid)
            ->find();

        if(false === ($CurrentManufacturePartEvent instanceof ManufacturePartEvent))
        {
            $this->logger->critical(
                'manufacture-part: Событие производственной партии на найдено',
                [self::class.':'.__LINE__, 'ManufacturePartUid' => (string) $ManufacturePartUid],
            );
        }

        /**
         * Получаем этап производства партии, который необходимо выполнить
         */
        $working = $this->activeWorkingManufacturePart
            ->findNextWorkingByManufacturePart($ManufacturePartUid);


        /**
         * Если все этапы выполнены - получаем все выполненные этапы
         */
        if(false === ($working instanceof UsersTableActionsWorkingUid))
        {
            return;
        }

        $ManufacturePartActionDTO = new ManufacturePartActionDTO();
        $CurrentManufacturePartEvent->getDto($ManufacturePartActionDTO);

        $ManufacturePartActionDTO
            ->getWorking()
            ->setWorking($working)
            ->setProfile(null);

        // Форма
        $form = $this->formFactory
            ->create(
                type: ManufacturePartActionForm::class,
                data: $ManufacturePartActionDTO,
                options: [
                    'action' => $this->router->generate(
                        name: 'manufacture-part:admin.scan',
                        parameters: ['id' => $ManufacturePartUid]),
                ],
            );


        /**
         * Получаем все этапы данной категории производства
         */
        $all = $this->allWorkingByManufacturePart
            ->forPart($ManufacturePartUid)
            ->findAll();


        $content = $this->twig->render(
            name: '@manufacture-part/admin/scan/content.html.twig',
            context: [
                'form' => $form->createView(),
                'part' => $CurrentManufacturePartEvent,
                'current' => $working,
                'all' => $all,
            ],
        );


        $message->setContent($content);


        return;


        $ManufacturePartActionDTO = new ManufacturePartActionDTO();
        $CurrentManufacturePartEvent->getDto($ManufacturePartActionDTO);

        $ManufacturePartActionDTO
            ->getWorking()
            ->setWorking($working)
            ->setProfile(null);


        /** @var ManufacturePart $ManufacturePart */
        $ManufacturePart = $this->entityManager
            ->getRepository(ManufacturePart::class)
            ->find($ManufacturePartUid);


        if(false === ($ManufacturePart instanceof ManufacturePart))
        {
            return;
        }


        /** @var ManufacturePartInvariable $ManufacturePartInvariable */
        $ManufacturePartInvariable = $this->entityManager
            ->getRepository(ManufacturePartInvariable::class)
            ->find($ManufacturePartUid);


        /* Получаем активное рабочее состояние производственной партии которое необходимо выполнить */
        $UsersTableActionsWorkingUid = $this->activeWorkingManufacturePart
            ->findNextWorkingByManufacturePart($ManufacturePart->getId());

        if(false === ($UsersTableActionsWorkingUid instanceof UsersTableActionsWorkingUid))
        {
            /* Получаем информацию о выполненных этапах и отправляем сообщение о выполненной заявке */
            $this->partCompleted($ManufacturePart->getId());

            return;
        }


        /** Получаем этапы производства указанной производственной партии  */
        $ManufacturePartWorking = $this->allWorkingByManufacturePart
            ->forPart($ManufacturePart->getId())
            ->findAll();

        $caption = '<b>Производственная партия:</b>';
        $caption .= "\n";
        $caption .= "\n";

        $caption .= 'Номер: <b>'.$ManufacturePartInvariable->getNumber().'</b>';
        $caption .= "\n";
        $caption .= 'Всего продукции: <b>'.$ManufacturePartInvariable->getQuantity().' шт.</b>';
        $caption .= "\n";
        $caption .= "\n";

        /** Получаем продукцию в производственной партии и присваиваем к сообщению */
        $caption = $this->captionProducts($ManufacturePart->getId(), $caption);

        /** Символ выполненного процесса  */
        $char = "\u2611\ufe0f";
        $decoded = json_decode('["'.$char.'"]');
        $done = mb_convert_encoding($decoded[0], 'UTF-8');

        /** Символ активного процесса  */
        $char = "\u25b6\ufe0f";
        $decoded = json_decode('["'.$char.'"]');
        $right = mb_convert_encoding($decoded[0], 'UTF-8');

        /** Символ НЕ выполненного процесса  */
        $char = "\u2705";
        $decoded = json_decode('["'.$char.'"]');
        $muted = mb_convert_encoding($decoded[0], 'UTF-8');

        $currentWorkingName = null;


        $caption .= '<b>Этапы производства:</b>';
        $caption .= "\n";

        /**
         * Все действия сотрудников, которые он может выполнить
         */
        foreach($ManufacturePartWorking as $working)
        {
            $icon = $currentWorkingName ? $done : $muted;

            if($UsersTableActionsWorkingUid->equals($working['working_id']))
            {
                $currentWorkingName = $working['working_name'];
                $icon = $right;
            }

            $caption .= $icon;
            $caption .= ' '.$working['working_name'];

            if($UsersTableActionsWorkingUid->equals($working['working_id']))
            {
                $caption .= ' <b>'.$ManufacturePartInvariable->getQuantity().' шт </b>';
            }

            $caption .= "\n";
        }

        $CurrentManufacturePart = current($ManufacturePartWorking);

        /* Комментарий к заявке */
        if($CurrentManufacturePart['part_comment'])
        {
            $caption .= "\n";
            $caption .= $CurrentManufacturePart['part_comment'];
            $caption .= "\n";

        }

        $caption .= "\n";
        $caption .= "\n";
        $caption .= 'Если Вами был найден брак - обратитесь к ответственному за данную производственную партию.';


        //        /** @see TelegramManufacturePartCancel */
        //
        //        $menu[] = [
        //            'text' => '❌ Удалить сообщение', // Удалить сообщение
        //            'callback_data' => 'telegram-delete-message'
        //        ];
        //
        //        /** @see TelegramManufacturePartDone */
        //
        //        $menu[] = [
        //            'text' => sprintf('Выполнить "%s" все %s шт.',
        //                $currentWorkingName,
        //                $ManufacturePartInvariable->getQuantity()
        //            ),
        //            'callback_data' => sprintf('%s|%s', TelegramManufacturePartDone::KEY, $ManufacturePartUid)
        //        ];


        $message->setContent($caption);


        //        /** Отправляем сообщение */
        //
        //        $markup = json_encode([
        //            'inline_keyboard' => array_chunk($menu, 1),
        //        ], JSON_THROW_ON_ERROR);


        //        $this->telegramSendMessage
        //            ->delete([$TelegramRequest->getId()])
        //            ->message($caption)
        //            ->markup($markup)
        //            ->send();
    }

    /**
     * Получаем информацию о выполненных этапах
     */
    public function partCompleted(ManufacturePartUid $ManufacturePartUid): void
    {
        $CompleteWorking = $this->activeWorkingManufacturePart
            ->fetchCompleteWorkingByManufacturePartAssociative($ManufacturePartUid);

        $caption = "<b>Производственная партия выполнена</b>";
        $caption .= "\n";
        $caption .= "\n";

        if($CompleteWorking)
        {
            $currentComplete = current($CompleteWorking);

            $caption .= 'Номер: <b>'.$currentComplete['part_number'].'</b>';
            $caption .= "\n";
            $caption .= 'Всего продукции: <b>'.$currentComplete['part_quantity'].' шт.</b>';
            $caption .= "\n";
            $caption .= "\n";

            /** Получаем продукцию в производственной партии и присваиваем к сообщению */
            $caption = $this->captionProducts($ManufacturePartUid, $caption);

            $caption .= '<b>Этапы производства:</b>';
            $caption .= "\n";
            foreach($CompleteWorking as $complete)
            {
                /* Пользователь выполнивший производственный этап */
                $caption .= $complete['working_name'].': <b>'.$complete['users_profile_username'].'</b>';
                $caption .= "\n";
            }
        }

        /** Отправляем сообщение о выполненной заявке */
        $this->telegramSendMessage
            ->delete($this->request->getId())
            ->message($caption)
            ->send();
    }

    public function captionProducts(ManufacturePartUid $part, string $caption): string
    {
        $caption .= '<b>Продукция:</b>';
        $caption .= "\n";

        $products = $this->ProductsByManufacturePart
            ->forPart($part)
            ->findAll();

        /** @var ProductsByManufacturePartResult $product */
        foreach($products as $key => $product)
        {
            if($key >= 50)
            {
                $caption .= "\n";
                $caption .= '<b>Подробный список производственной партии более 50 позиций только в CRM!</b>';
                $caption .= "\n";
                break;
            }

            $caption .= ($key + 1).'. '.$product->getArticle().' ';

            //$caption .= $product['product_name'].' ';


            if($product->getProductOfferReference())
            {
                foreach($this->reference as $reference)
                {
                    if($reference->type() === $product->getProductOfferReference())
                    {
                        $caption .= $this->translator->trans($product->getProductOfferValue(), domain: $reference->domain()).' ';
                    }
                }
            }
            else
            {
                empty($product->getProductOfferValue()) ?: $caption .= $product->getProductOfferValue().' ';
            }

            if($product->getProductVariationReference())
            {
                foreach($this->reference as $reference)
                {
                    if($reference->type() === $product->getProductVariationReference())
                    {
                        $caption .= $this->translator->trans($product->getProductVariationValue(), domain: $reference->domain()).' ';
                    }
                }
            }
            else
            {
                empty($product->getProductVariationValue()) ?: $caption .= $product->getProductVariationValue().' ';
            }


            if($product->getProductModificationReference())
            {
                foreach($this->reference as $reference)
                {
                    if($reference->type() === $product->getProductModificationReference())
                    {
                        $caption .= $this->translator->trans($product->getProductModificationValue(), domain: $reference->domain()).' ';
                    }
                }
            }
            else
            {
                empty($product->getProductModificationValue()) ?: $caption .= $product->getProductModificationValue().' ';
            }

            $caption .= ' | <b>'.$product->getTotal().' шт.</b>';

            $caption .= "\n";
        }

        $caption .= "\n";

        return $caption;
    }

}