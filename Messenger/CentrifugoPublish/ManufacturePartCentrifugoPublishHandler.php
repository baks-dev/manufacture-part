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

namespace BaksDev\Manufacture\Part\Messenger\CentrifugoPublish;

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByUidInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Twig\Environment;

#[AsMessageHandler(priority: 0)]
final class ManufacturePartCentrifugoPublishHandler
{

    public function __construct(
        private readonly CentrifugoPublishInterface $CentrifugoPublish,
        private readonly Environment $Twig,
        private readonly ProductDetailByUidInterface $ProductDetailByUids

    ) {}

    public function __invoke(ManufacturePartCentrifugoPublishMessage $message): void
    {

        if($message->getManufacturePartEvent() === false && $message->getTotal() === false)
        {

            $this->CentrifugoPublish
                ->addData(['identifier' => $message->getIdentifier()]) // ID продукта
                ->addData(['profile' => (string) $message->getProfile()])
                ->send('remove');
        }
        else
        {

            $event = $message->getEvent();
            $offer = $message->getOffer();
            $variation = $message->getVariation();
            $modification = $message->getModification();

            $product = $this->ProductDetailByUids
                ->event($event)
                ->offer($offer)
                ->variation($variation)
                ->modification($modification)
                ->findResult();

            $card = $this->Twig->render(
                name: '@manufacture-part/admin/selected-products/add/centrifugo.html.twig',
                context: ['card' => $product]);

            // HTML продукта
            $this->CentrifugoPublish->addData(['product' => $card]) // шаблон
            ->addData(['total' => $message->getTotal()]) // количество для суммы всех товаров
            ->send($message->getManufacturePartEvent());

            $this->CentrifugoPublish
                ->addData(['identifier' => $message->getIdentifier()]) // ID продукта
                ->addData(['profile' => false])
                ->send('remove');
        }
    }
}
