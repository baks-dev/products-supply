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
use BaksDev\Products\Product\Repository\Ids\ProductIdsByBarcodesRepository\ProductIdsByBarcodesInterface;
use BaksDev\Products\Product\Repository\Ids\ProductIdsByBarcodesRepository\ProductIdsByBarcodesResult;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Messenger\ProductSign\ProcessReservation\ProcessReservationProductSignMessage;
use BaksDev\Products\Supply\UseCase\Admin\New\Invariable\NewProductSupplyInvariableDTO;
use BaksDev\Products\Supply\UseCase\Admin\New\NewProductSupplyDTO;
use BaksDev\Products\Supply\UseCase\Admin\New\NewProductSupplyHandler;
use BaksDev\Products\Supply\UseCase\Admin\New\Personal\NewProductSupplyPersonalDTO;
use BaksDev\Products\Supply\UseCase\Admin\New\Product\NewProductSupplyProductDTO;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Парсит xlsx документ и создает ProductSupply со статусом NEW и коллекцией продуктов
 */
#[AsMessageHandler(priority: 8)]
final readonly class XlsxParseProductSupplyDispatcher
{
    private const string CONTAINER_NUMBER = 'B';

    private const string PRODUCT_TOTAL = 'I';

    private const string BARCODE = 'M';

    public function __construct(
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private ProductIdsByBarcodesInterface $productIdentifiersByBarcodeRepository,
        private NewProductSupplyHandler $productSupplyHandler,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    public function __invoke(FileScannerProductSupplyMessage $message): void
    {
        /** Массив поставок с продуктами */
        $ProductSupplys = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($message->getDir())
        );

        /** @var SplFileInfo $xlsxFile */
        foreach($iterator as $xlsxFile)
        {

            if(
                false === $xlsxFile->isFile() ||
                false === $xlsxFile->getRealPath() ||
                false === file_exists($xlsxFile->getRealPath()) ||
                false === ($xlsxFile->getExtension() === 'xlsx') ||
                false === str_starts_with($xlsxFile->getFilename(), 'original')
            )
            {
                continue;
            }

            /**
             * Загружаем файл.
             * IOFactory автоматически определит тип файла (XLSX, XLS, CSV и т.д.)
             * и выберет соответствующий ридер.
             */
            $spreadsheet = IOFactory::load($xlsxFile->getRealPath());

            /** Итерируемся по страницам */
            foreach($spreadsheet->getAllSheets() as $worksheet)
            {
                /** Итерируемся по стокам страницы */
                foreach($worksheet->getRowIterator() as $row)
                {
                    // Получаем номер текущей строки
                    $rowIndex = $row->getRowIndex();

                    /** Пропускаем первую строку - заголовки таблицы */
                    if($rowIndex === 1)
                    {
                        continue;
                    }

                    /** Номер контейнера */
                    $containerNumber = $worksheet->getCell(self::CONTAINER_NUMBER.$rowIndex)->getValue();

                    /** Количество продукции */
                    $productTotal = $worksheet->getCell(self::PRODUCT_TOTAL.$rowIndex)->getValue();

                    /** Штрихкод (GTIN) */
                    $barcode = (string) $worksheet->getCell(self::BARCODE.$rowIndex)->getValue();

                    /**
                     * Заканчиваем обработку страницы:
                     * - строка без номера контейнера
                     * - строка без номера штрихкода
                     */
                    if(null === $containerNumber || true === empty($barcode))
                    {
                        break;
                    }

                    /**
                     * Открываем поставку
                     */

                    if(false === array_key_exists($containerNumber, $ProductSupplys))
                    {
                        $ProductSupplyDTO = new NewProductSupplyDTO();

                        /** Номер контейнера */
                        $ProductSupplyContainerDTO = new NewProductSupplyInvariableDTO();
                        $ProductSupplyContainerDTO->setContainer($containerNumber);
                        $ProductSupplyDTO->setInvariable($ProductSupplyContainerDTO);

                        /** Информация о пользователе */
                        $ProductSupplyPersonalDTO = new NewProductSupplyPersonalDTO();
                        $ProductSupplyPersonalDTO->setUsr($message->getUsr());
                        $ProductSupplyPersonalDTO->setProfile($message->getProfile());
                        $ProductSupplyDTO->setPersonal($ProductSupplyPersonalDTO);

                        /** Сохраняем открытую поставку */
                        $ProductSupplys[$containerNumber] = $ProductSupplyDTO;
                    }

                    /**
                     * Добавляем продукты в ОТКРЫТУЮ поставку
                     */

                    if(true === array_key_exists($containerNumber, $ProductSupplys))
                    {

                        /** Продукт в поставке */
                        $ProductSupplyProductDTO = new NewProductSupplyProductDTO();
                        $ProductSupplyProductDTO->setBarcode($barcode);
                        $ProductSupplyProductDTO->setTotal($productTotal);

                        $barcodes = [$barcode];

                        /** Если штрихкод начинается с 0 - добавляем вариант без 0 */
                        if(str_starts_with($barcode, '0'))
                        {
                            $barcodes[] = ltrim($barcode, '0');
                        }

                        /** Находим продукт по штрихкодам (с 0 и без) */
                        $ProductIdentifiersDTO = $this->productIdentifiersByBarcodeRepository
                            ->byBarcodes($barcodes)
                            ->find();

                        /** Если продукт найден - поставка создается С ПРОДУКТОМ */
                        if(true === $ProductIdentifiersDTO instanceof ProductIdsByBarcodesResult)
                        {
                            $ProductSupplyProductDTO
                                ->setProduct($ProductIdentifiersDTO->getProduct())
                                ->setOfferConst($ProductIdentifiersDTO->getOfferConst())
                                ->setVariationConst($ProductIdentifiersDTO->getVariationConst())
                                ->setModificationConst($ProductIdentifiersDTO->getModificationConst());
                        }

                        /** Поставка создается БЕЗ ПРОДУКТА */
                        if(false === $ProductIdentifiersDTO instanceof ProductIdsByBarcodesResult)
                        {
                            $this->logger->info(
                                message: 'Не удалось найти продукт по штрихкоду: '.$barcode,
                                context: [self::class.':'.__LINE__],
                            );
                        }

                        /**
                         * Добавляем продукт в открытую поставку
                         *
                         * @var $ProductSupplys array{string, NewProductSupplyDTO}
                         */
                        $ProductSupplys[$containerNumber]->addProduct($ProductSupplyProductDTO);
                    }
                }
            }

            /** Удаляем файл после парсинга */
            new Filesystem()->remove($xlsxFile->getPathname());
        }

        /**
         * Создаем поставки
         */

        if(false === empty($ProductSupplys))
        {
            $this->logger->info(
                message: 'Успешно распарсили xlsx с поставкой',
                context: [
                    self::class.':'.__LINE__,
                ],
            );

            /** @var NewProductSupplyDTO $supply */
            foreach($ProductSupplys as $supply)
            {
                $handle = $this->productSupplyHandler->handle($supply);

                if(false === $handle instanceof ProductSupply)
                {
                    /** Если дубликат */
                    if(false === $handle)
                    {
                        $this->logger->warning(
                            message: sprintf(
                                'Поставка с номером контейнера `%s` уже создана',
                                $supply->getInvariable()->getContainer(),
                            ),
                            context: [
                                self::class.':'.__LINE__,
                                var_export($supply, true),
                            ],
                        );

                        continue;
                    }

                    $this->logger->critical(
                        message: 'Ошибка при создании поставки: '.$handle,
                        context: [
                            self::class.':'.__LINE__,
                            var_export($supply, true),
                        ],
                    );
                }

                if(true === $handle instanceof ProductSupply)
                {
                    $this->logger->info(
                        message: sprintf(
                            'успешно создали поставку %s',
                            $handle->getId()
                        ),
                        context: [self::class.':'.__LINE__],
                    );

                    /** Процесс бронирования Честных знаков на продукты в поставке */
                    foreach($supply->getProduct() as $product)
                    {
                        $total = $product->getTotal();

                        /** Запускаем процесс бронирования на каждую единицу продукции в поставке */
                        for($i = 0; $i < $total; $i++)
                        {
                            $this->logger->info(
                                message: sprintf(
                                    'Резервируем Честный знак: `%s` продукт из `%s` в поставке %s',
                                    $i + 1, $total, $handle->getId()
                                ),
                                context: [
                                    'контейнер' => $supply->getInvariable()->getContainer(),
                                    self::class.':'.__LINE__,
                                    var_export($product, true),
                                ],
                            );

                            $this->messageDispatch
                                ->dispatch(
                                    message: new ProcessReservationProductSignMessage(
                                        supply: $handle->getId(),
                                        user: $message->getUsr(),
                                        profile: $message->getProfile(),
                                        product: $product->getProduct(),
                                        offer: $product->getOfferConst(),
                                        variation: $product->getVariationConst(),
                                        modification: $product->getModificationConst(),
                                    ),
                                    transport: 'products-supply',
                                );
                        }
                    }
                }
            }
        }
    }
}
