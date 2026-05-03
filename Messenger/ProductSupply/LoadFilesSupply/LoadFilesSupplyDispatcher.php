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
 */

declare(strict_types=1);

namespace BaksDev\Products\Supply\Messenger\ProductSupply\LoadFilesSupply;


use BaksDev\Barcode\Pdf\PdfCropImg;
use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Centrifugo\Services\Notification\CentrifugoNotification;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Supply\Messenger\ProductSupply\Lock\ProductSupplyUnlockMessage;
use BaksDev\Products\Supply\Messenger\ProductSupply\ScannerFilesSupply\ScannerImageProductSupplyMessage;
use BaksDev\Products\Supply\Messenger\ProductSupplyMessage;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Парсит pdf документ, разбивает его на отдельные файлы с одной страницей и сохраняет как изображение */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class LoadFilesSupplyDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private string $project_dir,
        private Filesystem $filesystem,
        private PdfCropImg $PdfCropImg,
        private MessageDispatchInterface $MessageDispatch,
    ) {}

    public function __invoke(ProductSupplyMessage $message): void
    {
        /** Директория загрузки файла */
        $uploadDir = implode(DIRECTORY_SEPARATOR, [
            $this->project_dir,
            'public',
            'upload',
            'barcode',
            'products-supply',
            (string) $message->getId(),
        ]);

        if(false === $this->filesystem->exists($uploadDir))
        {
            return;
        }

        $directory = new RecursiveDirectoryIterator($uploadDir);
        $iterator = new RecursiveIteratorIterator($directory);

        foreach($iterator as $info)
        {
            if(
                false === $info->isFile() ||
                false === $info->getRealPath() ||
                false === ($info->getExtension() === 'pdf') ||
                false === file_exists($info->getRealPath()) ||
                false === str_starts_with($info->getFilename(), 'original')
            )
            {
                continue;
            }

            $part = new ProductSupplyUid();

            $isCrop = $this->PdfCropImg
                ->path($info->getPath())
                ->filename($info->getFilename())
                ->crop((string) $part);

            if(false === $isCrop)
            {
                $this->logger->critical(
                    'Ошибка при обработке файла PDF',
                    [self::class.':'.__LINE__, $info->getPath()],
                );

                continue;
            }

            /**
             * Создаем сообщения на сканер стикеров честного знака
             */

            $imgDirPath = $info->getPath().DIRECTORY_SEPARATOR.$part;
            $imgDirectory = new RecursiveDirectoryIterator($imgDirPath);
            $imgIterator = new RecursiveIteratorIterator($imgDirectory);

            foreach($imgIterator as $image)
            {
                if(
                    false === $image->isFile() ||
                    false === $image->getRealPath() ||
                    false === ($image->getExtension() === 'png') ||
                    false === file_exists($image->getRealPath())
                )
                {
                    continue;
                }

                $ScannerImageProductSupplyMessage = new ScannerImageProductSupplyMessage(
                    path: $image->getRealPath(),
                    supply: $message->getId(),
                    part: $part,
                );

                $this->MessageDispatch
                    ->dispatch(
                        message: $ScannerImageProductSupplyMessage,
                        transport: 'barcode',
                    );
            }

            // Удаляем после обработки файл PDF
            $this->filesystem->remove($info->getRealPath());

        }

        /**
         * Снимаем блокировку с поставки добавляя в очередь со сканером с низким приоритетом
         */

        $ProductSupplyUnlockMessage = new ProductSupplyUnlockMessage($message->getId());

        $this->MessageDispatch->dispatch(
            message: $ProductSupplyUnlockMessage,
            transport: 'barcode-low',
        );
    }
}
