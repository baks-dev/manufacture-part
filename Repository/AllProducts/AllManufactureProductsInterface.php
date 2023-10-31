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

namespace BaksDev\Manufacture\Part\Repository\AllProducts;

use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Manufacture\Part\Type\Complete\ManufacturePartComplete;
use BaksDev\Products\Product\Forms\ProductFilter\ProductFilterInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

interface AllManufactureProductsInterface
{
    public function getAllManufactureProducts(
        SearchDTO $search,
        ?UserProfileUid $profile,
        ProductFilterInterface $filter,
        ?ManufacturePartComplete $complete = null
    ): PaginatorInterface;
}