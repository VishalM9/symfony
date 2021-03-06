<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Mime;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class BodyRendererTest extends TestCase
{
    public function testRenderTextOnly()
    {
        $email = $this->prepareEmail('Text', null);
        $this->assertEquals('Text', $email->getBody()->bodyToString());
    }

    public function testRenderHtmlOnly()
    {
        $html = '<head>head</head><b>HTML</b><style type="text/css">css</style>';
        $email = $this->prepareEmail(null, $html);
        $body = $email->getBody();
        $this->assertInstanceOf(AlternativePart::class, $body);
        $this->assertEquals('HTML', $body->getParts()[0]->bodyToString());
        $this->assertEquals(str_replace('=', '=3D', $html), $body->getParts()[1]->bodyToString());
    }

    public function testRenderMultiLineHtmlOnly()
    {
        $html = <<<HTML
<head>
<style type="text/css">
css
</style>
</head>
<b>HTML</b>
HTML;
        $email = $this->prepareEmail(null, $html);
        $body = $email->getBody();
        $this->assertInstanceOf(AlternativePart::class, $body);
        $this->assertEquals('HTML', str_replace(["\r", "\n"], '', $body->getParts()[0]->bodyToString()));
        $this->assertEquals(str_replace(['=', "\n"], ['=3D', "\r\n"], $html), $body->getParts()[1]->bodyToString());
    }

    public function testRenderHtmlOnlyWithTextSet()
    {
        $email = $this->prepareEmail(null, '<b>HTML</b>');
        $email->text('Text');
        $body = $email->getBody();
        $this->assertInstanceOf(AlternativePart::class, $body);
        $this->assertEquals('Text', $body->getParts()[0]->bodyToString());
        $this->assertEquals('<b>HTML</b>', $body->getParts()[1]->bodyToString());
    }

    public function testRenderTextAndHtml()
    {
        $email = $this->prepareEmail('Text', '<b>HTML</b>');
        $body = $email->getBody();
        $this->assertInstanceOf(AlternativePart::class, $body);
        $this->assertEquals('Text', $body->getParts()[0]->bodyToString());
        $this->assertEquals('<b>HTML</b>', $body->getParts()[1]->bodyToString());
    }

    public function testRenderWithContextReservedEmailEntry()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->prepareEmail('Text', '', ['email' => 'reserved!']);
    }

    private function prepareEmail(?string $text, ?string $html, array $context = []): TemplatedEmail
    {
        $twig = new Environment(new ArrayLoader([
            'text' => $text,
            'html' => $html,
            'document.txt' => 'Some text document...',
            'image.jpg' => 'Some image data',
        ]));
        $renderer = new BodyRenderer($twig);
        $email = (new TemplatedEmail())
            ->to('fabien@symfony.com')
            ->from('helene@symfony.com')
            ->context($context)
        ;
        if (null !== $text) {
            $email->textTemplate('text');
        }
        if (null !== $html) {
            $email->htmlTemplate('html');
        }
        $renderer->render($email);

        return $email;
    }
}
