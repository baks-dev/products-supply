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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\Files;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Supply\Messenger\ProductSupply\Scanner\ScannerProductSupplyMessage;
use DirectoryIterator;
use Exception;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

/**
 * Парсит pdf документ, обрезает пустую область в файлах и сохраняет их с постфиксом crop_
 */
#[AsMessageHandler(priority: 9)]
final readonly class PdfCropProductSupplyDispatcher
{
    public function __construct(
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private Filesystem $filesystem,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    public function __invoke(FileScannerProductSupplyMessage $message): void
    {
        $directory = new RecursiveDirectoryIterator($message->getDir());
        $iterator = new RecursiveIteratorIterator($directory);

        /** @var DirectoryIterator $info */
        foreach($iterator as $info)
        {
            if(
                false === $info->isFile() ||
                false === $info->getRealPath() ||
                false === ($info->getExtension() === 'pdf') ||
                false === file_exists($info->getRealPath()) ||
                false === str_starts_with($info->getFilename(), 'page')
            )
            {
                continue;
            }

            /**
             * Проверяем размер файла (пропускаем пустые страницы переименовав в error.txt)
             */

            if($info->getSize() < 100)
            {
                $this->filesystem->rename($info->getRealPath(), $info->getRealPath().'.error.txt');

                $this->logger->critical(
                    message: 'Ошибка при удалении неразмеченной пустой области в файле PDF',
                    context: [$info->getRealPath(), self::class.':'.__LINE__],
                );

                continue;
            }

            $cropFilename = $info->getPath().DIRECTORY_SEPARATOR.uniqid('crop_', true).'.pdf';

            /**
             * Обрезаем пустую область
             */

            try
            {
                /** Создает файл с префиксом crop_ */
                $processCrop = new Process(['sudo', 'pdfcrop', '--margins', '1', $info->getRealPath(), $cropFilename]);
                $processCrop
                    ->setTimeout(null)
                    ->mustRun();

                /** Удаляем после обработки основной файл PDF */
                $this->filesystem->remove($info->getRealPath());
            }
            catch(Exception)
            {
                /** Пробуем просканировать без обрезки */
                $this->filesystem->rename($info->getRealPath(), $cropFilename);
            }

            /** Задание на сканирование crop pdf */
            $ProductSignScannerMessage = new ScannerProductSupplyMessage(
                path: $cropFilename,
                usr: $message->getUsr(),
                profile: $message->getProfile(),
            );

            $this->messageDispatch->dispatch(
                message: $ProductSignScannerMessage,
                transport: 'barcode',
            );

        }
    }
}