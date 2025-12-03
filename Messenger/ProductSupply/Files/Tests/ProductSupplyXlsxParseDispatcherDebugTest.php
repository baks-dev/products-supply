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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\Files\Tests;

use BaksDev\Products\Product\Repository\CurrentProductByArticle\CurrentProductDTO;
use BaksDev\Products\Product\Repository\CurrentProductByArticle\ProductConstByBarcodeInterface;
use BaksDev\Products\Supply\UseCase\Admin\New\Invariable\NewProductSupplyInvariableDTO;
use BaksDev\Products\Supply\UseCase\Admin\New\NewProductSupplyDTO;
use BaksDev\Products\Supply\UseCase\Admin\New\NewProductSupplyHandler;
use BaksDev\Products\Supply\UseCase\Admin\New\Product\NewProductSupplyProductDTO;
use DirectoryIterator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

#[When(env: 'test')]
class ProductSupplyXlsxParseDispatcherDebugTest extends KernelTestCase
{
    private const string CONTAINER_NUMBER = 'B';

    private const string PRODUCT_COUNT = 'I';

    private const string BARCODE = 'M';

    private static string|false $testDir = false;

    public static function setUpBeforeClass(): void
    {
        /** @var ContainerBagInterface $containerBag */
        $container = self::getContainer();
        $containerBag = $container->get(ContainerBagInterface::class);

        /** Создаем путь к тестовой директории */
        $testUploadDir = implode(DIRECTORY_SEPARATOR, [
            $containerBag->get('kernel.project_dir'), 'public', 'upload', 'tests', 'xlsx'
        ]);

        self::$testDir = true === is_dir($testUploadDir) ? $testUploadDir : false;
    }

    public function testExel(): void
    {
        self::assertTrue(true);

        /** Не начинаем тест, если не создана соответсвующая директория */
        if(false === self::$testDir)
        {
            self::assertTrue(true);
            return;
        }


        /** @var ProductConstByBarcodeInterface $productConstByBarcodeRepository */
        $productConstByBarcodeRepository = self::getContainer()->get(ProductConstByBarcodeInterface::class);

        /** @var NewProductSupplyHandler $ProductSupplyHandler */
        $ProductSupplyHandler = self::getContainer()->get(NewProductSupplyHandler::class);


        $ProductsSupply = [];

        foreach(new DirectoryIterator(self::$testDir) as $xlsxFile)
        {
            if($xlsxFile->getExtension() !== 'xlsx')
            {
                continue;
            }

            if(false === $xlsxFile->getRealPath() || false === file_exists($xlsxFile->getRealPath()))
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
                /** Итерируемся по стокам страницы - ПРОДУКТЫ */
                foreach($worksheet->getRowIterator() as $row)
                {
                    // Получаем номер текущей строки
                    $rowIndex = $row->getRowIndex();

                    /** Пропускаем строку с заголовками */
                    if($rowIndex === 1)
                    {
                        continue;
                    }

                    /** Номер контейнера */
                    $containerNumber = $worksheet->getCell(self::CONTAINER_NUMBER.$rowIndex)->getValue();

                    /** Количество продукции */
                    $productCount = $worksheet->getCell(self::PRODUCT_COUNT.$rowIndex)->getValue();

                    /** Штрихкод (GTIN) */
                    $barcode = $worksheet->getCell(self::BARCODE.$rowIndex)->getValue();

                    /**
                     * Заканчиваем обработку страницы:
                     * - строка без номера контейнера
                     * - строка без номера штрихкода
                     */
                    if(null === $containerNumber || null === $barcode)
                    {
                        break;
                    }

                    if(false === array_key_exists($containerNumber, $ProductsSupply))
                    {
                        $ProductSupplyDTO = new NewProductSupplyDTO();

                        /** Номер контейнера */
                        $ProductSupplyContainerDTO = new NewProductSupplyInvariableDTO();
                        $ProductSupplyContainerDTO->setNumber($containerNumber);
                        $ProductSupplyDTO->setInvariable($ProductSupplyContainerDTO);

                        /** Сохраняем открытую поставку */
                        $ProductsSupply[$containerNumber] = $ProductSupplyDTO;
                    }

                    /**
                     * Добавляем продукты в ОТКРЫТУЮ поставку
                     */

                    if(true === array_key_exists($containerNumber, $ProductsSupply))
                    {

                        /** Продукт в поставке */
                        $ProductSupplyProductDTO = new NewProductSupplyProductDTO();
                        $ProductSupplyProductDTO->setBarcode($barcode);

                        /** Находим продукт по штрихкоду */
                        $CurrentProductDTO = $productConstByBarcodeRepository->find($barcode);

                        /** Поставка создается без продукта */
                        if(false === $CurrentProductDTO instanceof CurrentProductDTO)
                        {
                        }

                        if(true === $CurrentProductDTO instanceof CurrentProductDTO)
                        {
                            $ProductSupplyProductDTO
                                ->setProduct($CurrentProductDTO->getProduct())
                                ->setOfferConst($CurrentProductDTO->getOfferConst())
                                ->setVariationConst($CurrentProductDTO->getVariationConst())
                                ->setModificationConst($CurrentProductDTO->getModificationConst());
                        }

                        /**
                         * @var $ProductsSupply array{string, NewProductSupplyDTO}
                         */
                        $ProductsSupply[$containerNumber]->addProduct($ProductSupplyProductDTO);
                    }

                }
            }
        }

        return;

        /** @var NewProductSupplyDTO $supply */
        foreach($ProductsSupply as $supply)
        {
            $handle = $ProductSupplyHandler->handle($supply);

        }
    }

}
