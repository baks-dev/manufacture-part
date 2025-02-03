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


use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\UseCase\Admin\AddProduct\ManufacturePartProductsDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\AddProduct\ManufacturePartProductsForm;
use BaksDev\Manufacture\Part\UseCase\Admin\AddProduct\ManufacturePartProductsHandler;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByUidInterface;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_MANUFACTURE_PART_ADD')]
final class AddProductsController extends AbstractController
{
    /**
     * Добавить продукцию в производственную партию
     */
    #[Route('/admin/manufacture/part/product/add/{total}', name: 'admin.products.add', methods: ['GET', 'POST'])]
    public function news(
        Request $request,
        ManufacturePartProductsHandler $ManufacturePartProductHandler,
        ProductDetailByUidInterface $productDetail,
        CentrifugoPublishInterface $CentrifugoPublish,
        #[ParamConverter(ProductEventUid::class)] $product = null,
        #[ParamConverter(ProductOfferUid::class)] $offer = null,
        #[ParamConverter(ProductVariationUid::class)] $variation = null,
        #[ParamConverter(ProductModificationUid::class)] $modification = null,
        ?int $total = null,
    ): Response
    {
        $ManufacturePartProductDTO = new ManufacturePartProductsDTO($this->getProfileUid());

        if($request->isMethod('GET'))
        {
            $ManufacturePartProductDTO
                ->setProduct($product)
                ->setOffer($offer)
                ->setVariation($variation)
                ->setModification($modification)
                ->setTotal($total);
        }


        /* Скрываем у других продукт */
        $CentrifugoPublish
            ->addData(['identifier' => $ManufacturePartProductDTO->getIdentifier()]) // ID продукта
            ->addData(['profile' => (string) $this->getCurrentProfileUid()])
            ->send('remove');


        // Форма
        $form = $this
            ->createForm(
                type: ManufacturePartProductsForm::class,
                data: $ManufacturePartProductDTO,
                options: ['action' => $this->generateUrl('manufacture-part:admin.products.add')]
            )
            ->handleRequest($request);

        $details = $productDetail
            ->event($ManufacturePartProductDTO->getProduct())
            ->offer($ManufacturePartProductDTO->getOffer())
            ->variation($ManufacturePartProductDTO->getVariation())
            ->modification($ManufacturePartProductDTO->getModification())
            ->find();

        if($form->isSubmitted() && $form->isValid() && $form->has('manufacture_part_products'))
        {
            $this->refreshTokenForm($form);

            $handle = $ManufacturePartProductHandler
                ->handle($ManufacturePartProductDTO, $this->getCurrentProfileUid());

            //            /** Если была открыта новая партия - делаем редирект */
            //            if($handle instanceof ManufacturePart)
            //            {
            //                $this->addFlash(
            //                    'admin.page.new',
            //                    'admin.success.new',
            //                    'admin.manufacture.part'
            //                );
            //
            //                return $this->redirectToRoute('manufacture-part:admin.index');
            //            }

            /** Если был добавлен продукт в открытую партию отправляем сокет */
            if($handle instanceof ManufacturePartProduct)
            {
                $details['product_total'] = $ManufacturePartProductDTO->getTotal();

                $CentrifugoPublish
                    // HTML продукта
                    ->addData(['product' => $this->render(['card' => $details],
                        file: 'centrifugo.html.twig')->getContent()]) // шаблон
                    ->addData(['total' => $ManufacturePartProductDTO->getTotal()]) // количество для суммы всех товаров
                    //->addData(['identifier' => $ManufacturePartProductDTO->getIdentifier()]) // ID продукта
                    ->send((string) $handle->getEvent());

                /* Скрываем у всех продукт */
                $CentrifugoPublish
                    ->addData(['identifier' => $ManufacturePartProductDTO->getIdentifier()]) // ID продукта
                    ->addData(['profile' => false])
                    ->send('remove');


                $return = $this->addFlash(
                    type: 'admin.page.add',
                    message: 'admin.success.add',
                    domain: 'admin.manufacture.part',
                    status: $request->isXmlHttpRequest() ? 200 : 302 // не делаем редирект в случае AJAX
                );

                return $request->isXmlHttpRequest() ? $return : $this->redirectToRoute('manufacture-part:admin.index');
            }

            $this->addFlash(
                'admin.page.add',
                'admin.danger.add',
                'admin.manufacture.part
                ', $handle);

            return $this->redirectToRoute('manufacture-part:admin.index');
        }

        return $this->render([
            'form' => $form->createView(),
            'card' => $details
        ]);
    }
}