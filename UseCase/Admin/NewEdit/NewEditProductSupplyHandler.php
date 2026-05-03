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

namespace BaksDev\Products\Supply\UseCase\Admin\NewEdit;

use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Validator\ValidatorCollectionInterface;
use BaksDev\Files\Resources\Upload\File\FileUploadInterface;
use BaksDev\Files\Resources\Upload\Image\ImageUploadInterface;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Messenger\ProductSupply\LoadFilesSigns\LoadFilesSignsMessage;
use BaksDev\Products\Supply\Messenger\ProductSupply\LoadFilesSupply\LoadFilesSupplyMessage;
use BaksDev\Products\Supply\Messenger\ProductSupplyMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class NewEditProductSupplyHandler extends AbstractHandler
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $project_dir,
        private Filesystem $filesystem,

        EntityManagerInterface $entityManager,
        MessageDispatchInterface $messageDispatch,
        ValidatorCollectionInterface $validatorCollection,
        ImageUploadInterface $imageUpload,
        FileUploadInterface $fileUpload
    )
    {
        parent::__construct($entityManager, $messageDispatch, $validatorCollection, $imageUpload, $fileUpload);
    }

    public function handle(NewEditProductSupplyDTO $command): ProductSupply|string
    {
        $this
            ->setCommand($command)
            ->preEventPersistOrUpdate(ProductSupply::class, ProductSupplyEvent::class);

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->flush();

        /** Создаем только поставку если список файлов пуст */
        if($command->getFiles()->getFiles()->isEmpty())
        {
            $this->messageDispatch
                ->addClearCacheOther('products-sign')
                ->dispatch(
                    message: new ProductSupplyMessage($this->main->getId(), $this->main->getEvent(), $command->getEvent()),
                    transport: 'products-supply',
                );

            return $this->main;
        }


        /**
         * Добавляем файлы в поставку
         */

        $uploadDir = implode(DIRECTORY_SEPARATOR, [
            $this->project_dir,
            'public', 'upload', 'barcode', 'products-supply', $this->main->getId(),
        ]);


        if(false === $this->filesystem->exists($uploadDir))
        {
            try
            {
                $this->filesystem->mkdir($uploadDir);
            }
            catch(IOExceptionInterface $exception)
            {
                $this->validatorCollection->error(
                    message: 'Ошибка при создании директорий',
                    context: [
                        $exception->getMessage(),
                        $uploadDir,
                        self::class.':'.__LINE__,
                    ],
                );

                $this->messageDispatch
                    ->addClearCacheOther('products-sign')
                    ->dispatch(
                        message: new ProductSupplyMessage($this->main->getId(), $this->main->getEvent(), $command->getEvent()),
                        transport: 'products-supply',
                    );

                return $this->main;
            }
        }

        /**
         * Загружаем прикрепленные файлы на сервер
         */

        foreach($command->getFiles()->getFiles() as $ProductSupplyFileDTO)
        {
            /** @var UploadedFile $file */
            foreach($ProductSupplyFileDTO->files as $file)
            {
                if(
                    false === in_array($file->getMimeType(), [
                        'application/pdf',
                        'application/acrobat',
                        'application/nappdf',
                        'application/x-pdf',
                        'image/pdf',
                    ])
                )
                {
                    $this->validatorCollection->error(
                        message: sprintf('Неподдерживаемый формат файла %s', $file->getMimeType()),
                        context: [self::class.':'.__LINE__],
                    );

                    continue;
                }


                $ProductSupplyPdfDir = $uploadDir.DIRECTORY_SEPARATOR.'pdf';

                if(false === $this->filesystem->exists($ProductSupplyPdfDir))
                {
                    $this->filesystem->mkdir($ProductSupplyPdfDir);
                }

                $fileName = uniqid('original_', true).'.pdf';
                $file->move($ProductSupplyPdfDir, $fileName);

            }
        }


        $this->messageDispatch
            ->addClearCacheOther('products-sign')
            ->dispatch(
                message: new ProductSupplyMessage($this->main->getId(), $this->main->getEvent(), $command->getEvent()),
                transport: 'products-supply',
            );

        return $this->main;
    }
}