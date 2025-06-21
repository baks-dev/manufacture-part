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

namespace BaksDev\Manufacture\Part\Controller\Admin\Products;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Messenger\CentrifugoPublish\ManufacturePartCentrifugoPublishMessage;
use BaksDev\Manufacture\Part\UseCase\Admin\AddProduct\ManufacturePartProductsDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\AddProduct\ManufacturePartProductsHandler;
use BaksDev\Manufacture\Part\UseCase\Admin\AddProduct\ManufactureSelectionPartProductsDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\AddProduct\ManufactureSelectionPartProductsForm;
use BaksDev\Products\Product\Repository\ProductsDetailByUids\ProductsDetailByUidsInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_MANUFACTURE_PART_ADD')]
final class AddSelectedProductsController extends AbstractController
{
    /**
     * Добавить выбранные товары в производственную партию
     */
    #[Route('/admin/manufacture/part/selected-product/add', name: 'admin.selected-products.add', methods: ['GET', 'POST'])]
    public function add(
        Request $request,
        ManufacturePartProductsHandler $ManufacturePartProductHandler,
        ProductsDetailByUidsInterface $productsDetail,
        MessageDispatchInterface $messageDispatch
    ): Response
    {

        $ManufactureSelectionPartProductDTO = new ManufactureSelectionPartProductsDTO($this->getProfileUid());

        $form = $this
            ->createForm(
                type: ManufactureSelectionPartProductsForm::class,
                data: $ManufactureSelectionPartProductDTO,
                options: ['action' => $this->generateUrl('manufacture-part:admin.selected-products.add')],
            )
            ->handleRequest($request);


        /**  Получить массивы UIDs по выбранным продуктам */
        $events = [];
        $offers = [];
        $variations = [];
        $modifications = [];

        /** @var ManufacturePartProductsDTO $ManufacturePartProductDTO */
        foreach($ManufactureSelectionPartProductDTO->getProductFormData() as $key => $ManufacturePartProductDTO)
        {
            /** Скрываем у других продукты для избежания двойного производства */
            $ManufacturePartCentrifugoPublishMessage = new ManufacturePartCentrifugoPublishMessage(
                identifier: $ManufacturePartProductDTO->getIdentifier(),
                profile: $this->getCurrentProfileUid(),
            );

            $messageDispatch
                ->dispatch(
                    message: $ManufacturePartCentrifugoPublishMessage,
                    transport: 'manufacture-part',
                );

            $events[$key] = $ManufacturePartProductDTO->getProduct();
            $offers[$key] = $ManufacturePartProductDTO->getOffer();
            $variations[$key] = $ManufacturePartProductDTO->getVariation();
            $modifications[$key] = $ManufacturePartProductDTO->getModification();

        }

        if($form->isSubmitted() && $form->isValid() && $form->has('product_form_data'))
        {
            $this->refreshTokenForm($form);

            $countTotal = 0;
            $isError = false;

            /**
             * Добавляем в производственную партию каждый товар
             *
             * @var ManufacturePartProductsDTO $value
             */
            foreach($ManufactureSelectionPartProductDTO->getProductFormData() as $ManufacturePartProductDTO)
            {
                /** Указать профиль отв. лица */
                $ManufacturePartProductDTO->setProfile($this->getProfileUid());

                $handle = $ManufacturePartProductHandler->handle($ManufacturePartProductDTO);

                if($handle instanceof ManufacturePartProduct)
                {
                    $ManufacturePartCentrifugoPublishMessage = new ManufacturePartCentrifugoPublishMessage(

                    /** Скрываем у ВСЕХ пользователей продукты после добавления в списке */
                        identifier: $ManufacturePartProductDTO->getIdentifier(),
                        profile: $this->getCurrentProfileUid(),

                        /** Передаем идентификаторы продукта для вставки шаблона */
                        manufacturePartEvent: $handle->getEvent()->getId(),
                        event: $ManufacturePartProductDTO->getProduct(),
                        offer: $ManufacturePartProductDTO->getOffer(),
                        variation: $ManufacturePartProductDTO->getVariation(),
                        modification: $ManufacturePartProductDTO->getModification(),

                        total: $ManufacturePartProductDTO->getTotal(),
                    );

                    $messageDispatch
                        ->dispatch(
                            message: $ManufacturePartCentrifugoPublishMessage,
                            transport: 'manufacture-part',
                        );

                    $countTotal += $ManufacturePartProductDTO->getTotal();

                    continue;
                }

                /** Ошибка при добавлении товара в производственную партию */
                $this->addFlash(
                    type: 'admin.page.add',
                    message: 'admin.danger.add',
                    domain: 'manufacture-part.admin',
                    arguments: $ManufacturePartProductDTO->getTotal(),
                    status: $request->isXmlHttpRequest() ? 200 : 302, // не делаем редирект в случае AJAX
                );

                $isError = true;
            }

            if($countTotal > 0)
            {
                /* В toast передадим аргумент - кол-во добавленного товар */
                $return = $this->addFlash(
                    type: 'admin.page.add',
                    message: 'admin.success.add',
                    domain: 'manufacture-part.admin',
                    arguments: (string) $countTotal,
                    status: $request->isXmlHttpRequest() ? 200 : 302, // не делаем редирект в случае AJAX
                );

                if(false === $isError)
                {
                    return $request->isXmlHttpRequest() ? $return : $this->redirectToRoute('manufacture-part:admin.index');
                }
            }
        }

        /** Получаем информацию о добавленных продуктов */
        $details = $productsDetail
            ->events($events)
            ->offers($offers)
            ->variations($variations)
            ->modifications($modifications)
            ->toArray();

        return $this->render([
            'form' => $form->createView(),
            'cards' => $details,
        ]);
    }
}