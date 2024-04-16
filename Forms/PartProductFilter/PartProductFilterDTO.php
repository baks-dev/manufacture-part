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

use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use Symfony\Component\HttpFoundation\Request;

final class PartProductFilterDTO implements PartProductFilterInterface
{

    public const offer = 'nxgMEhOzdk';
    public const variation = 'jZpKtmPNTk';
    public const modification = 'zabytyxmGW';

    private Request $request;


    /**
     * Категория
     */
    private readonly CategoryProductUid $category;

    /**
     * Торговое предложение
     */
    private ?string $offer = null;

    /**
     * Множественный вариант торгового предложения
     */
    private ?string $variation = null;

    /**
     * Модификатор множественного варианта торгового предложения
     */
    private ?string $modification = null;


    public function __construct(CategoryProductUid $category, Request $request)
    {
        $this->request = $request;
        $this->category = $category;
    }


    /**
     * Категория
     */

    public function getCategory(): CategoryProductUid
    {
        return $this->category;
    }


    /**
     * Торговое предложение
     */

    public function getOffer(): ?string
    {
        return $this->offer ?: $this->request->getSession()->get(self::offer);
    }

    public function setOffer(?string $offer): void
    {
        if(empty($offer) || empty($this->category))
        {
            $this->request->getSession()->remove(self::offer);
        }

        $this->offer = $offer;
    }


    /**
     * Множественный вариант торгового предложения
     */

    public function getVariation(): ?string
    {
        return $this->variation ?: $this->request->getSession()->get(self::variation);
    }

    public function setVariation(?string $variation): void
    {
        if(empty($variation) || empty($this->category) ||  empty($this->offer))
        {
            $this->request->getSession()->remove(self::variation);
        }

        $this->variation = $variation;
    }


    /**
     * Модификатор множественного варианта торгового предложения
     */

    public function getModification(): ?string
    {
        return $this->modification ?: $this->request->getSession()->get(self::modification);
    }

    public function setModification(?string $modification): void
    {
        if(empty($modification) || empty($this->category) ||  empty($this->offer) || empty($this->variation) )
        {
            $this->request->getSession()->remove(self::modification);
        }

        $this->modification = $modification;
    }
	
}

