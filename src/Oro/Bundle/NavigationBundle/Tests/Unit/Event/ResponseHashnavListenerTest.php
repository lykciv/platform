<?php

namespace Oro\Bundle\NavigationBundle\Tests\Unit\Event;

use Oro\Bundle\NavigationBundle\Event\ResponseHashnavListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class ResponseHashnavListenerTest extends \PHPUnit\Framework\TestCase
{
    private const TEST_URL = 'http://test_url/';
    private const TEMPLATE = '@OroNavigation/HashNav/redirect.html.twig';

    private ResponseHashnavListener $listener;

    private Request $request;

    private Response $response;

    private Environment|\PHPUnit\Framework\MockObject\MockObject $twig;

    private ResponseEvent|\PHPUnit\Framework\MockObject\MockObject $event;

    private TokenStorageInterface|\PHPUnit\Framework\MockObject\MockObject $tokenStorage;

    protected function setUp(): void
    {
        $this->response = new Response();
        $this->request  = Request::create(self::TEST_URL);
        $this->request->headers->add([ResponseHashnavListener::HASH_NAVIGATION_HEADER => true]);
        $this->event = $this->createMock(ResponseEvent::class);

        $this->event->expects(self::any())
            ->method('getRequest')
            ->willReturn($this->request);

        $this->event->expects(self::any())
            ->method('getResponse')
            ->willReturn($this->response);

        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->listener = $this->getListener(false);
    }

    public function testPlainRequest(): void
    {
        $testBody = 'test';
        $this->response->setContent($testBody);

        $this->listener->onResponse($this->event);

        self::assertEquals($testBody, $this->response->getContent());
    }

    public function testHashRequestWOUser(): void
    {
        $this->response->setStatusCode(302);
        $this->response->headers->add(['location' => self::TEST_URL]);

        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn(false);

        $this->event->expects(self::once())
            ->method('setResponse');

        $template = 'rendered_template_content';
        $this->twig->expects(self::once())
            ->method('render')
            ->with(
                self::TEMPLATE,
                [
                    'full_redirect' => true,
                    'location'      => self::TEST_URL
                ]
            )
            ->willReturn($template);

        $this->listener->onResponse($this->event);
    }

    public function testHashRequestWithFullRedirectAttribute(): void
    {
        $this->response->setStatusCode(302);
        $this->response->headers->add(['location' => self::TEST_URL]);

        $this->request->attributes->set('_fullRedirect', true);

        $this->tokenStorage->expects(self::never())
            ->method('getToken');

        $this->event->expects(self::once())
            ->method('setResponse');

        $template = 'rendered_template_content';
        $this->twig->expects(self::once())
            ->method('render')
            ->with(
                self::TEMPLATE,
                [
                    'full_redirect' => true,
                    'location'      => self::TEST_URL
                ]
            )
            ->willReturn($template);

        $this->listener->onResponse($this->event);
    }

    public function testHashRequestNotFound(): void
    {
        $this->response->setStatusCode(404);
        $this->serverErrorHandle();
    }

    public function testFullRedirectProducedInProdEnv(): void
    {
        $expected = ['full_redirect' => 1, 'location' => self::TEST_URL];
        $this->response->headers->add(['location' => self::TEST_URL]);
        $this->response->setStatusCode(503);

        $template = 'rendered_template_content';
        $this->twig
            ->expects(self::once())
            ->method('render')
            ->with('@OroNavigation/HashNav/redirect.html.twig', $expected)
            ->willReturn($template);

        $this->event->expects(self::once())->method('setResponse')->with($this->response);
        $this->listener->onResponse($this->event);
    }

    public function testFullRedirectNotProducedInDevEnv(): void
    {
        $listener = $this->getListener(true);
        $this->response->headers->add(['location' => self::TEST_URL]);
        $this->response->setStatusCode(503);
        $this->twig->expects(self::never())->method('render');

        $this->event->expects(self::once())->method('setResponse');
        $listener->onResponse($this->event);
    }

    private function getListener($isDebug): ResponseHashnavListener
    {
        return new ResponseHashnavListener($this->tokenStorage, $this->twig, $isDebug);
    }

    private function serverErrorHandle(): void
    {
        $this->event->expects(self::once())
            ->method('setResponse');

        $template = 'rendered_template_content';
        $this->twig->expects(self::once())
            ->method('render')
            ->with(
                self::TEMPLATE,
                [
                    'full_redirect' => true,
                    'location'      => self::TEST_URL
                ]
            )
            ->willReturn($template);

        $this->listener->onResponse($this->event);
    }
}
