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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\Scanner;

use BaksDev\Barcode\Reader\BarcodeRead;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Products\Product\Repository\Ids\ProductIdsByBarcodesRepository\ProductIdsByBarcodesInterface;
use BaksDev\Products\Product\Repository\Ids\ProductIdsByBarcodesRepository\ProductIdsByBarcodesResult;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\UseCase\Admin\New\ProductSignHandler;
use BaksDev\Products\Supply\UseCase\Admin\ProductsSign\New\ProductSignNewDTO;
use DateTimeImmutable;
use Exception;
use Imagick;
use Psr\Log\LoggerInterface;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Сканирует pdf файлы с честными знаками
 */
#[AsMessageHandler(priority: 0)]
final readonly class ScannerProductSupplyDispatcher
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private ProductSignHandler $productSignHandler,
        private BarcodeRead $barcodeRead,
        private Filesystem $filesystem,
        private ProductIdsByBarcodesInterface $productIdentifiersByBarcodeRepository,
    ) {}

    public function __invoke(ScannerProductSupplyMessage $message): void
    {
        /** Файла больше не существует */
        if(false === $this->filesystem->exists($message->getRealPath()))
        {
            return;
        }

        $file = new SplFileInfo($message->getRealPath());

        /** Директория загрузки изображения с кодом после сканирования */
        $productSupplyScanDir = implode(DIRECTORY_SEPARATOR, [
            $this->projectDir, 'public', 'upload', 'product_supply_code', '',
        ]);

        /** Если директория загрузки не найдена - создаем с правами 0700 */
        $this->filesystem->exists($productSupplyScanDir) ?: $this->filesystem->mkdir($productSupplyScanDir);

        if(
            false === $file->isFile() ||
            false === $file->getRealPath() ||
            false === file_exists($file->getRealPath()) ||
            false === ($file->getExtension() === 'pdf') ||
            false === str_starts_with($file->getFilename(), 'crop')
        )
        {
            $this->logger->critical(
                message: 'Ошибка при сканировании файла Честного знака.',
                context: [self::class.':'.__LINE__]
            );

            return;
        }

        /**
         * Открываем PDF для подсчета страниц на случай если их несколько
         */
        $cropFilePath = $file->getRealPath();

        Imagick::setResourceLimit(Imagick::RESOURCETYPE_TIME, 3600);
        $Imagick = new Imagick();
        $Imagick->setResolution(500, 500);
        $Imagick->readImage($cropFilePath);
        $pages = $Imagick->getNumberImages(); // количество страниц в файле

        /** Переименовываем в начале сканирования Честного знака */
        $scanProcessFileName = str_replace('crop', 'scan_process', $cropFilePath);
        $this->filesystem->rename($cropFilePath, $scanProcessFileName, true);

        for($number = 0; $number < $pages; $number++)
        {
            /** Преобразуем PDF страницу в PNG и сохраняем временный файл для расчета его хеша */
            $Imagick->setIteratorIndex($number);
            $Imagick->setImageFormat('png');

            /**
             * В некоторых случаях может вызывать ошибку.
             * В таком случае сохраняем без рамки и пробуем отсканировать как есть
             */
            try
            {
                $Imagick->borderImage('white', 5, 5);
            }
            catch(Exception $e)
            {
                $this->logger->critical(
                    message: ' Ошибка при добавлении рамки к изображению. Пробуем отсканировать как есть.',
                    context: [
                        $e->getMessage(),
                        self::class.':'.__LINE__
                    ]
                );
            }

            /**
             * Записываем изображение в указанное имя файла
             */

            $fileTemp = $productSupplyScanDir.uniqid('', true).'.png';

            $Imagick->writeImage($fileTemp);
            $Imagick->clear();

            /** Рассчитываем хеш файла для перемещения */
            $fileTempHash = md5_file($fileTemp);
            $dirMove = $productSupplyScanDir.$fileTempHash.DIRECTORY_SEPARATOR;
            $fileMove = $dirMove.'image.png';

            /** Если директория для перемещения не найдена - создаем  */
            true === $this->filesystem->exists($dirMove) ?: $this->filesystem->mkdir($dirMove);

            /**
             * Перемещаем в указанную директорию если файла не существует.
             * Если в перемещаемой директории файл существует - удаляем временный файл.
             */
            true === $this->filesystem->exists($fileMove)
                ? $this->filesystem->remove($fileTemp)
                : $this->filesystem->rename($fileTemp, $fileMove);

            /**
             * Сканируем Честный знак
             */

            $decode = $this->barcodeRead->decode($fileMove);

            /** Код из изображения */
            $code = $decode->getText();

            /** Префикс файла в случае успеха распознавания */
            $scanFileName = str_replace('scan_process', 'scan_success', $scanProcessFileName);

            $ProductSignNewDTO = new ProductSignNewDTO();

            /** Ошибка при сканировании */
            if(empty($code) || $decode->isError() || str_starts_with($code, '(00)'))
            {
                $code = uniqid('error_', true);
                $ProductSignNewDTO->setErrorStatus();

                /** Код партии */
                $partCode = $code;

                /** Префикс файла в случае ошибки */
                $scanFileName = str_replace('scan_success', 'scan_error', $scanFileName);
            }
            else
            {
                /** Получаем Штрихкод (GTIN) из Честного знака */
                $parseCode = preg_match('/^\(\d+\)(.*?)\(\d+\)/', $code, $matches);

                if(0 === $parseCode || false === $parseCode)
                {
                    /** Код партии */
                    $partCode = $code;

                    /** Префикс в случае ошибки */
                    $scanFileName = str_replace('scan_success', 'scan_error', $scanFileName);

                    $this->logger->critical(
                        message: ' Не удалось извлечь штрихкод после сканирования Честного знака. Code: '.$code,
                        context: [
                            $parseCode,
                            self::class.':'.__LINE__,
                        ]
                    );
                }

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
                        $this->logger->warning(message: sprintf(
                            'Не удалось найти продукт по штрихкоду %s из Честного знака. Честный знак будет создан без продукта',
                            $partCode
                        ),
                            context: [
                                'штрихкоды' => $barcodes,
                                self::class.':'.__LINE__,
                            ]
                        );
                    }
                }
            }

            /**
             * Переименовываем файл после процесса сканирования Честного знака с префиксом:
             * - scan_success - успешное сканирование
             * - scan_error - ошибка при сканировании
             */
            $this->filesystem->rename($scanProcessFileName, $scanFileName, true);

            /**
             * Переименовываем директорию по коду честного знака (для уникальности)
             */

            $scanDirName = md5($code);
            $renameDir = $productSupplyScanDir.$scanDirName.DIRECTORY_SEPARATOR;

            if(true === $this->filesystem->exists($renameDir))
            {
                // Удаляем директорию если уже имеется
                $this->filesystem->remove($dirMove);
            }
            else
            {
                // переименовываем директорию если не существует
                $this->filesystem->rename($dirMove, $renameDir);
            }

            /**
             * Присваиваем результат сканера
             */

            $ProductSignNewDTO->getCode()
                ->setCode($code)
                ->setName($scanDirName)
                ->setPngExt();

            /** Генерируем идентификатор для группировки Честных знаков */
            $part = new ProductSignUid()
                ->stringToUuid($partCode.(new DateTimeImmutable('now')->format('Ymd')));

            $ProductSignNewDTO->getInvariable()
                ->setPart($part) // номер группы для объединения честных знаков
                ->setUsr($message->getUsr())
                ->setProfile($message->getProfile());

            $handle = $this->productSignHandler->handle($ProductSignNewDTO);

            if(false === ($handle instanceof ProductSign))
            {
                if(false === $handle)
                {
                    $this->logger->warning(message: sprintf('products-sign: Дубликат честного знака %s: ', $code));

                    /** Удаляем после обработки файл PDF */
                    $this->filesystem->remove($scanFileName);

                    continue;
                }

                $this->logger->critical(
                    message: sprintf('products-sign: Ошибка %s при сохранении информации о Честном знаке: ', $handle),
                    context: [self::class.':'.__LINE__],
                );
            }
            else
            {
                $this->logger->info(
                    sprintf('products-sign: Честный знак создан: %s: %s', $handle->getId(), $code),
                    [self::class.':'.__LINE__],
                );

                /** Создаем команду для отправки файла CDN */
                $this->messageDispatch->dispatch(
                    new CDNUploadImageMessage($handle->getId(), ProductSignCode::class, $fileTempHash),
                    transport: 'files-res-low',
                );

                /** Удаляем после обработки файл PDF */
                $this->filesystem->remove($scanFileName);
            }
        }
    }
}
