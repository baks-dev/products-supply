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

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

/**
 * Парсит pdf документ, разбивает его на отдельные файлы с одной страницей и сохраняет их постфиксом page_
 */
#[AsMessageHandler(priority: 10)]
final readonly class PdfPagesProductSupplyDispatcher
{
    public function __construct(
        private Filesystem $filesystem
    ) {}

    public function __invoke(FileScannerProductSupplyMessage $message): void
    {
        $directory = new RecursiveDirectoryIterator($message->getDir());
        $iterator = new RecursiveIteratorIterator($directory);

        foreach($iterator as $info)
        {
            // This condition execution costs less than the previous one
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

            $process = new Process([
                'pdftk', $info->getRealPath(), 'burst', 'output',
                $info->getPath().DIRECTORY_SEPARATOR.uniqid('page_', true).'.%d.pdf'
            ]);

            $process
                ->setTimeout(null)
                ->mustRun();

            /** Удаляем после обработки основной файл PDF и doc_data.txt */
            $this->filesystem->remove([$info->getRealPath(), $info->getPath().DIRECTORY_SEPARATOR.'doc_data.txt']);
        }
    }
}
