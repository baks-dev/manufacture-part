<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Forms\PartProductFilter\PartProductFilterDTO;
use BaksDev\Manufacture\Part\Forms\PartProductFilter\PartProductFilterForm;
use BaksDev\Manufacture\Part\Repository\InfoManufacturePart\InfoManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\ProductsByManufacturePartInterface;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity('ROLE_MANUFACTURE_PART')]
final class IndexController extends AbstractController
{
    /**
     * Список добавленной продукции в производственной партии
     */
    #[Route('/admin/manufacture/part/products/{id}/{page<\d+>}', name: 'admin.products.index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        #[MapEntity] ManufacturePart $ManufacturePart,
        ProductsByManufacturePartInterface $productsByManufacturePart,
        InfoManufacturePartInterface $infoManufacturePart,
        int $page = 0,
    ): Response {
        // Поиск
        $search = new SearchDTO($request);
        $searchForm = $this->createForm(SearchForm::class, $search, [
            'action' => $this->generateUrl('manufacture-part:admin.products.index', ['id' => $ManufacturePart->getId()]),
        ]);
        $searchForm->handleRequest($request);


        /** Информация о производственной партии */
        $info = $infoManufacturePart->fetchInfoManufacturePartAssociative(
            $ManufacturePart->getId(),
            $this->getCurrentProfileUid(),
            $this->isGranted('ROLE_MANUFACTURE_PART_OTHER') ? $this->getProfileUid() : null
        );


        /**
         * Фильтр продукции
         */
        $filter = new PartProductFilterDTO(new CategoryProductUid($info['category_id'] ?? null), $request);
        $filterForm = $this->createForm(PartProductFilterForm::class, $filter, [
            'action' => $this->generateUrl('manufacture-part:admin.products.index', ['id' => $ManufacturePart->getId()]),
        ]);
        $filterForm->handleRequest($request);
        !$filterForm->isSubmitted() ?: $this->redirectToReferer();

        // Получаем список продукции в партии
        $ProductsManufacturePart = $productsByManufacturePart
            ->fetchAllProductsByManufacturePartAssociative(
                $search,
                $filter,
                $ManufacturePart->getId(),
                $this->getCurrentProfileUid(),
                $this->getProfileUid(),
                $this->isGranted('ROLE_MANUFACTURE_PART_OTHER')
            );

        return $this->render(
            [
                'query' => $ProductsManufacturePart,
                'search' => $searchForm->createView(),
                'filter' => $filterForm->createView(),
                'info' => $info,
            ]
        );
    }
}
