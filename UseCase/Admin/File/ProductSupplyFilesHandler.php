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

namespace BaksDev\Products\Supply\UseCase\Admin\File;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Supply\Messenger\ProductSupply\Files\FileScannerProductSupplyMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Загружает файлы на сервер
 */
final readonly class ProductSupplyFilesHandler
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $project_dir,
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private Filesystem $filesystem,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    public function handle(ProductSupplyFilesDTO $command): bool
    {
        /**
         * Директория загрузки файлов
         */

        $uploadDir = implode(DIRECTORY_SEPARATOR, [
            $this->project_dir,
            'public', 'upload', 'barcode', 'products-supply', $command->getUsr(), $command->getProfile()
        ]);

        /**
         * Создаем директорию, если не была создана
         */

        if(false === $this->filesystem->exists($uploadDir))
        {
            try
            {
                $this->filesystem->mkdir($uploadDir);
            }
            catch(IOExceptionInterface $exception)
            {
                $this->logger->critical(
                    message: 'Ошибка при создании директорий',
                    context: [
                        $exception->getMessage(),
                        $uploadDir,
                        self::class.':'.__LINE__,
                    ],
                );

                return false;
            }
        }

        /**
         * Загружаем прикрепленные файлы на сервер
         */

        foreach($command->getFiles() as $ProductSupplyFileDTO)
        {
            /** @var UploadedFile $file */
            foreach($ProductSupplyFileDTO->files as $file)
            {
                if(
                    in_array($file->getMimeType(), [
                        'application/pdf',
                        'application/acrobat',
                        'application/nappdf',
                        'application/x-pdf',
                        'image/pdf',
                    ])
                )
                {
                    $ProductSupplyPdfDir = $uploadDir.DIRECTORY_SEPARATOR.'pdf';

                    if(false === $this->filesystem->exists($ProductSupplyPdfDir))
                    {
                        $this->filesystem->mkdir($ProductSupplyPdfDir);
                    }

                    $fileName = uniqid('original_', true).'.pdf';
                    $file->move($ProductSupplyPdfDir, $fileName);
                }
            }
        }

        /** Обрабатываем все загруженные файлы */
        $this->messageDispatch->dispatch(
            message: new FileScannerProductSupplyMessage(
                dir: $uploadDir,
                usr: $command->getUsr(),
                profile: $command->getProfile(),
            ),
            transport: 'products-supply',
        );

        return true;
    }
}
