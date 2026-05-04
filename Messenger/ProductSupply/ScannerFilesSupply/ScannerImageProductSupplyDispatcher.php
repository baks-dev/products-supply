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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\ScannerFilesSupply;

use BaksDev\Barcode\Reader\BarcodeRead;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Products\Product\Repository\Ids\ProductIdsByBarcodesRepository\ProductIdsByBarcodesInterface;
use BaksDev\Products\Product\Repository\Ids\ProductIdsByBarcodesRepository\ProductIdsByBarcodesResult;
use BaksDev\Products\Product\Repository\ProductInvariable\ProductInvariableInterface;
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\UseCase\Admin\New\ProductSignHandler;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Messenger\ProductSupply\UpdateProductSupply\UpdateProductSupplyDispatcher;
use BaksDev\Products\Supply\Messenger\ProductSupply\UpdateProductSupply\UpdateProductSupplyMessage;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\UseCase\Admin\ProductsSign\New\ProductSignNewDTO;
use Doctrine\ORM\Mapping\Table;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Сканирует файл с изображением честного знака и добавляем в базу
 */
#[Autoconfigure(shared: false)]
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
        private CurrentProductSupplyEventInterface $CurrentProductSupplyEventRepository,
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


        /** Получаем информацию о поставке */

        $CurrentProductSupplyEvent = $this->CurrentProductSupplyEventRepository
            ->forMain($message->getSupplyIdentifier())
            ->find();

        if(false === ($CurrentProductSupplyEvent instanceof ProductSupplyEvent))
        {
            $this->logger->critical(
                'products-supply: Ошибка при получении события поставки',
                [self::class.':'.__LINE__, (string) $message->getSupplyIdentifier()],
            );

            return;
        }


        /** Код из изображения */
        $code = $decode->getText();

        /** Получаем Штрихкод (GTIN) из Честного знака */
        $parseCode = preg_match('/^\(\d+\)(.*?)\(\d+\)/', $code, $matches);

        if($parseCode !== 1)
        {
            $this->logger->critical(
                message: sprintf(
                    'products-supply: Не удалось извлечь штрихкод после сканирования Честного знака. Code: %s',
                    $code,
                ),
                context: [self::class.':'.__LINE__],
            );

            $this->filesystem->remove($message->getRealPath());

            return;
        }

        /**
         * Находим продукт по штрихкоду
         */

        /** Код партии */
        $partCode = $matches[1];
        $barcodes = [$matches[1]];

        /** Если штрихкод начинается с 0 - добавляем вариант без 0 */
        if(str_starts_with($matches[1], '0'))
        {
            $barcodes[] = ltrim($matches[1], '0');
        }

        /** Продукт по штрихкоду */
        $ProductIdsByBarcodesResult = $this->productIdentifiersByBarcodeRepository
            ->byBarcodes($barcodes)
            ->find();


        if(false === ($ProductIdsByBarcodesResult instanceof ProductIdsByBarcodesResult))
        {
            $this->logger->warning(
                message: sprintf(
                    'Не удалось найти продукт по штрихкоду %s из Честного знака',
                    $partCode,
                ),
                context: [
                    'штрихкоды' => $barcodes,
                    self::class.':'.__LINE__,
                ],
            );

            return;
        }


        if(false === ($ProductIdsByBarcodesResult->getInvariable() instanceof ProductInvariableUid))
        {
            $this->logger->warning(
                message: sprintf(
                    'Не удалось определить Invariable продукта по штрихкоду %s из Честного знака',
                    $partCode,
                ),
                context: [
                    'штрихкоды' => $barcodes,
                    self::class.':'.__LINE__,
                ],
            );

            return;
        }


        /**
         * Создаем полный путь для сохранения изображения с кодом по таблице сущности
         */

        $pathCode = null;
        $pathCode[] = $this->projectDir;
        $pathCode[] = 'public';
        $pathCode[] = 'upload';
        $pathCode[] = $current->getArguments()['name'];
        $pathCode[] = '';

        $scanDirName = md5($code);
        $productSignDir = implode(DIRECTORY_SEPARATOR, $pathCode);

        $renameDir = $productSignDir.$scanDirName.DIRECTORY_SEPARATOR.'image.png';


        /**
         * Перемещаем файл с изображением в директорию честного знака если md5 директории не существует
         */

        if(false === $this->filesystem->exists($renameDir))
        {
            /** Создаем директорию для перемещения если отсутствует  */
            $productImageSignDir = $productSignDir.$scanDirName;

            if(false === $this->filesystem->exists($productImageSignDir))
            {
                $this->filesystem->mkdir($productImageSignDir);
            }

            $this->filesystem->rename($message->getRealPath(), $renameDir, true);
        }

        /**
         * Присваиваем результат сканера
         */

        $ProductSignNewDTO = new ProductSignNewDTO();

        $ProductSignNewDTO
            ->getSupply()
            ->setValue($message->getSupplyIdentifier());

        $ProductSignNewDTO->getCode()
            ->setCode($code)
            ->setName($scanDirName)
            ->setPngExt();

        /** Присваиваем пользователя и профиль склада */
        $ProductSignNewDTO->getInvariable()
            ->setPart($message->getPart())
            ->setUsr($CurrentProductSupplyEvent->getSupplyUser())
            ->setProfile($CurrentProductSupplyEvent->getSupplyProfile())
            ->setProduct($ProductIdsByBarcodesResult->getProduct())
            ->setOffer($ProductIdsByBarcodesResult->getOfferConst())
            ->setVariation($ProductIdsByBarcodesResult->getVariationConst())
            ->setModification($ProductIdsByBarcodesResult->getModificationConst());

        $ProductSign = $this->productSignHandler->handle($ProductSignNewDTO);

        if(false === ($ProductSign instanceof ProductSign))
        {
            if(false !== $ProductSign)
            {
                $this->logger->critical(
                    message: sprintf('products-supply: Ошибка %s при сохранении информации о Честном знаке: ', $ProductSign),
                    context: [self::class.':'.__LINE__],
                );
            }

            return;
        }

        $this->logger->info(
            sprintf('Добавили честный знак: %s', $code),
            [self::class.':'.__LINE__],
        );

        /**
         * Создаем команду для отправки файла CDN
         *
         * @see CDNUploadImageDispatcher
         */

        $CDNUploadImageMessage = new CDNUploadImageMessage(
            id: $ProductSign->getId(),
            entity: ProductSignCode::class,
            dir: $scanDirName,
        );

        $this->messageDispatch->dispatch(
            message: $CDNUploadImageMessage,
            transport: 'files-res-low',
        );

        /**
         * Создаем комманду на добавление единицы продукции к поставке
         *
         * @see UpdateProductSupplyDispatcher
         */

        $UpdateProductSupplyMessage = new UpdateProductSupplyMessage(
            event: $CurrentProductSupplyEvent->getId(),
            product: $ProductIdsByBarcodesResult->getInvariable(),
            total: 1,
        );

        $this->messageDispatch->dispatch(
            message: $UpdateProductSupplyMessage,
            transport: 'barcode',
        );
    }
}
