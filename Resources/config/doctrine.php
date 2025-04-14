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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BaksDev\Manufacture\Part\BaksDevManufacturePartBundle;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventType;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartType;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Manufacture\Part\Type\Product\ManufacturePartProductType;
use BaksDev\Manufacture\Part\Type\Product\ManufacturePartProductUid;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatusType;
use Symfony\Config\DoctrineConfig;

return static function (ContainerConfigurator $container, DoctrineConfig $doctrine) {

    $doctrine->dbal()->type(ManufacturePartUid::TYPE)->class(ManufacturePartType::class);
    $doctrine->dbal()->type(ManufacturePartEventUid::TYPE)->class(ManufacturePartEventType::class);

    $doctrine->dbal()->type(ManufacturePartStatus::TYPE)->class(ManufacturePartStatusType::class);
    $doctrine->dbal()->type(ManufacturePartProductUid::TYPE)->class(ManufacturePartProductType::class);

    $emDefault = $doctrine->orm()->entityManager('default')->autoMapping(true);


    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /** Value Resolver */

    $services->set(ManufacturePartUid::class)->class(ManufacturePartUid::class);

    $emDefault->mapping('manufacture-part')
        ->type('attribute')
        ->dir(BaksDevManufacturePartBundle::PATH.'Entity')
        ->isBundle(false)
        ->prefix(BaksDevManufacturePartBundle::NAMESPACE.'\\Entity')
        ->alias('manufacture-part');
};
