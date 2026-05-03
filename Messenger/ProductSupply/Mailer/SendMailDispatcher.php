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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\Mailer;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Messenger\ProductSupplyMessage;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\Repository\ProductSign\ProductSignCodesBySupply\ProductSignCodesBySupplyInterface;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusCreate;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/** При статусе поставки Create «Готова к отправке» отправляем уведомление на email */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class SendMailDispatcher
{
    private const TEMPLATE = '@products-supply/admin/email/sign.html.twig';

    public function __construct(
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private ParameterBagInterface $parameters,
        private TranslatorInterface $translator,
        private CurrentProductSupplyEventInterface $CurrentProductSupplyEventRepository,
        private ProductSignCodesBySupplyInterface $productSignCodesBySupplyRepository,
        private MailerInterface $mailer,
    ) {}

    public function __invoke(ProductSupplyMessage $message): void
    {
        $ProductSupplyEvent = $this->CurrentProductSupplyEventRepository
            ->forMain($message->getId())
            ->find();

        if(false === ($ProductSupplyEvent instanceof ProductSupplyEvent))
        {
            $this->logger->critical(
                'products-supply: Событие поставки не найдено',
                [self::class.':'.__LINE__, var_export($message, true)]);

            return;
        }

        if(false === $ProductSupplyEvent->getStatus()->equals(ProductSupplyStatusCreate::class))
        {
            return;
        }

        /** Создаем TXT файл с честными знаками */

        $codes = $this->productSignCodesBySupplyRepository
            ->forSupply($ProductSupplyEvent->getMain())
            ->findAll();

        if(false === $codes || false === $codes->valid())
        {
            $this->logger->error(
                sprintf('%s: Честных знаков в поставке не найдено', $ProductSupplyEvent->getInvariable()->getNumber()),
                [self::class.':'.__LINE__, var_export($message, true),],
            );

            return;
        }

        /** Отправляем все честные знаки на Email */

        // Создаем письмо для отправки пользователю
        $templatedEmail = new TemplatedEmail();

        $templatedEmail
            ->from(new Address(
                $this->parameters->get('PROJECT_NO_REPLY'),
                $this->parameters->get('PROJECT_NAME'),
            ))
            ->to($this->parameters->get('PROJECT_MAIL_SIGN'))
            ->subject($this->translator->trans(
                id: 'supply.create',
                parameters: ['%number%' => $ProductSupplyEvent->getInvariable()->getNumber()],
                domain: 'products-supply.sign',
            ))//->htmlTemplate(self::TEMPLATE)
        ;

        $attach = '';

        foreach($codes as $code)
        {
            $attach .= $code->getBigCode().PHP_EOL;
        }

        $templatedEmail->attach($attach, 'codes.txt', 'text/plain');

        $this->mailer->send($templatedEmail); // отправляем письмо пользователю

    }
}
