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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Supply\Messenger\ProductSupply\Scanner;

use BaksDev\Barcode\Reader\BarcodeRead;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Products\Product\Repository\Ids\ProductIdsByBarcodesRepository\ProductIdsByBarcodesInterface;
use BaksDev\Products\Product\Repository\Ids\ProductIdsByBarcodesRepository\ProductIdsByBarcodesResult;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\UseCase\Admin\New\ProductSignHandler;
use BaksDev\Products\Supply\UseCase\Admin\ProductsSign\New\ProductSignNewDTO;
use Doctrine\ORM\Mapping\Table;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Сканирует файлы с честными знаками
 */
#[AsMessageHandler(priority: 0)]
final readonly class ScannerImageProductSupplyDispatcher
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private ProductSignHandler $productSignHandler,
        private BarcodeRead $barcodeRead,
        private Filesystem $filesystem,
        private ProductIdsByBarcodesInterface $productIdentifiersByBarcodeRepository,
    ) {}

    public function __invoke(ScannerImageProductSupplyMessage $message): void
    {
        if(false === $this->filesystem->exists($message->getRealPath()))
        {
            return;
        }

        /** Директория загрузки изображения с кодом по названию таблицы ProductSignCode */

        $ref = new ReflectionClass(ProductSignCode::class);
        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));

        if(false === isset($current->getArguments()['name']))
        {
            $this->logger->critical(
                sprintf(
                    'products-supply:: Невозможно определить название таблицы из класса сущности %s',
                    ProductSignCode::class,
                ),
                [self::class.':'.__LINE__],
            );
        }


        /**
         * Сканируем Честный знак
         */

        $decode = $this->barcodeRead->decode($message->getRealPath());

        if(true === $decode->isError())
        {
            $this->logger->critical(
                'products-supply:: Ошибка при сканировании файла ',
                [self::class.':'.__LINE__, $message->getRealPath()],
            );

            return;
        }

        /** Код из изображения */
        $code = $decode->getText();

        /** Получаем Штрихкод (GTIN) из Честного знака */
        $parseCode = preg_match('/^\(\d+\)(.*?)\(\d+\)/', $code, $matches);

        if(0 === $parseCode || false === $parseCode)
        {
            $this->logger->critical(
                message: 'products-supply: Не удалось извлечь штрихкод после сканирования Честного знака. Code: '.$code,
                context: [self::class.':'.__LINE__, $code],
            );

            $this->filesystem->remove($message->getRealPath());

            return;
        }


        $ProductSignNewDTO = new ProductSignNewDTO();


        /** Находим продукт по штрихкоду */
        if(1 === $parseCode)
        {
            /** Код партии */
            $partCode = $matches[1];
            $barcodes = [$matches[1]];

            /** Если штрихкод начинается с 0 - добавляем вариант без 0 */
            if(str_starts_with($matches[1], '0'))
            {
                $barcodes[] = ltrim($matches[1], '0');
            }

            /** Продукт по штрихкоду */
            $product = $this->productIdentifiersByBarcodeRepository
                ->byBarcodes($barcodes)
                ->find();

            /** Присваиваем продукт */
            if(true === $product instanceof ProductIdsByBarcodesResult)
            {
                $ProductSignNewDTO->getInvariable()
                    ->setProduct($product->getProduct())
                    ->setOffer($product->getOfferConst())
                    ->setVariation($product->getVariationConst())
                    ->setModification($product->getModificationConst());
            }

            /** Если продукт не найден */
            if(false === $product instanceof ProductIdsByBarcodesResult)
            {
                $this->logger->warning(
                    message: sprintf(
                        'Не удалось найти продукт по штрихкоду %s из Честного знака. Честный знак НЕ БУДЕТ создан',
                        $partCode,
                    ),
                    context: [
                        'штрихкоды' => $barcodes,
                        self::class.':'.__LINE__,
                    ],
                );

                return;
            }
        }

        /** Создаем полный путь для сохранения изображения с кодом по таблице сущности*/
        $pathCode = null;
        $pathCode[] = $this->projectDir;
        $pathCode[] = 'public';
        $pathCode[] = 'upload';
        $pathCode[] = $current->getArguments()['name'];
        $pathCode[] = '';

        $scanDirName = md5($code);
        $productSignDir = implode(DIRECTORY_SEPARATOR, $pathCode);


        $renameDir = $productSignDir.$scanDirName.DIRECTORY_SEPARATOR.'image.png';

        if(true === $this->filesystem->exists($renameDir))
        {
            // Удаляем файл если уже имеется
            $this->filesystem->remove($message->getRealPath());
            return;
        }

        /** Создаем директорию для перемещения если отсутствует  */
        $productImageSignDir = $productSignDir.$scanDirName;

        if(false === $this->filesystem->exists($productImageSignDir))
        {
            $this->filesystem->mkdir($productImageSignDir);
        }

        /**
         * Перемещаем файл с изображением в директорию честного знака и присваиваем результат сканера
         */

        $this->filesystem->rename($message->getRealPath(), $renameDir, true);

        $ProductSignNewDTO->getCode()
            ->setCode($code)
            ->setName($scanDirName)
            ->setPngExt();

        $ProductSignNewDTO->getInvariable()
            ->setPart($message->getPart())
            ->setUsr($message->getUsr())
            ->setProfile($message->getProfile());

        $handle = $this->productSignHandler->handle($ProductSignNewDTO);

        if(false === ($handle instanceof ProductSign))
        {
            if(false === $handle)
            {
                $this->logger->warning(
                    message: sprintf('Дубликат честного знака %s: ', $code),
                    context: [self::class.':'.__LINE__],
                );
                return;
            }

            $this->logger->critical(
                message: sprintf('products-supply: Ошибка %s при сохранении информации о Честном знаке: ', $handle),
                context: [self::class.':'.__LINE__],
            );
        }
        else
        {
            $this->logger->info(
                sprintf('Добавили честный знак: id - %s; code - %s', $handle->getId(), $code),
                [self::class.':'.__LINE__],
            );

            /** Создаем команду для отправки файла CDN */
            $this->messageDispatch->dispatch(
                new CDNUploadImageMessage($handle->getId(), ProductSignCode::class, $scanDirName),
                transport: 'files-res-low',
            );
        }
    }
}
