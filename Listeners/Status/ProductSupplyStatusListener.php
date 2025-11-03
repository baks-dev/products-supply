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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Supply\Listeners\Status;

use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\ProductSupplyStatusCollection;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatusType;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

#[AsEventListener(event: ControllerEvent::class)]
#[AsEventListener(event: ConsoleCommandEvent::class)]
final readonly class ProductSupplyStatusListener
{
    public function __construct(
        private ProductSupplyStatusCollection $collection
    ) {}

    public function onKernelController(ControllerEvent $event): void
    {
        $this->collection->cases();

        if(in_array(ProductSupplyStatusType::class, get_declared_classes(), true))
        {
            $this->collection->cases();
        }
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->collection->cases();
    }
}
