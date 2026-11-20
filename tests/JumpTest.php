<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use Closure;
use think\App;
use think\exception\HttpResponseException;
use think\Request;
use think\Response;
use PHPUnit\Framework\TestCase;
use youvision\store\admin\support\jump\Jump;

final class JumpTest extends TestCase
{
    private function controller(Request $request, array $config = []): JumpController
    {
        $app = new App(dirname(__DIR__));
        $app->config->set(array_replace_recursive(
            (array) require dirname(__DIR__) . '/src/config/store_admin.php',
            $config
        ), 'store_admin');

        return new JumpController($app, $request);
    }

    private function ajaxRequest(): Request
    {
        return (new Request())->withServer(['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Response $response): array
    {
        return (array) json_decode((string) $response->getContent(), true);
    }

    public function testSuccessJson(): void
    {
        $controller = $this->controller($this->ajaxRequest());
        $response = $controller->successResponse('操作成功', '/admin/index', ['id' => 1], 5);

        self::assertSame(200, $response->getCode());
        $payload = $this->payload($response);
        self::assertSame(1, $payload['code']);
        self::assertSame('操作成功', $payload['msg']);
        self::assertSame(['id' => 1], $payload['data']);
        self::assertSame('/admin/index', $payload['url']);
        self::assertSame(5, $payload['wait']);
    }

    public function testErrorUsesBackUrlForJsonWithoutUrl(): void
    {
        $controller = $this->controller($this->ajaxRequest());
        $payload = $this->payload($controller->errorResponse('操作失败'));

        self::assertSame(0, $payload['code']);
        self::assertSame('', $payload['url']);
        self::assertSame(3, $payload['wait']);
    }

    public function testErrorUsesHistoryBackForHtml(): void
    {
        $controller = $this->controller(new Request());
        $response = $controller->errorResponse('操作失败');

        self::assertInstanceOf(\think\response\View::class, $response);
    }

    public function testResult(): void
    {
        $controller = $this->controller($this->ajaxRequest());
        $payload = $this->payload($controller->resultResponse(['id' => 1]));

        self::assertSame(0, $payload['code']);
        self::assertSame('', $payload['msg']);
        self::assertSame(['id' => 1], $payload['data']);
        self::assertIsInt($payload['time']);
    }

    public function testRedirect(): void
    {
        $controller = $this->controller($this->ajaxRequest());
        $response = $controller->redirectResponse('/admin/index', 301);

        self::assertSame(301, $response->getCode());
        self::assertSame('/admin/index', $response->getHeader('Location'));
    }

    public function testConfiguredCodesAndWait(): void
    {
        $controller = $this->controller($this->ajaxRequest(), [
            'jump' => [
                'success_code' => 9,
                'wait' => 8,
            ],
        ]);

        $payload = $this->payload($controller->successResponse('ok'));

        self::assertSame(9, $payload['code']);
        self::assertSame(8, $payload['wait']);
    }

    public function testTemplateExists(): void
    {
        self::assertFileExists(dirname(__DIR__) . '/theme/view/jump/default.html');
        self::assertFileExists(dirname(__DIR__) . '/theme/view/jump/error.html');
    }
}

final class JumpController
{
    use Jump;

    protected ?App $app = null;

    protected ?Request $request = null;

    public function __construct(App $app, Request $request)
    {
        $this->app = $app;
        $this->request = $request;
    }

    public function successResponse(
        mixed $msg = '',
        ?string $url = null,
        mixed $data = '',
        ?int $wait = null
    ): Response {
        return $this->capture(function () use ($msg, $url, $data, $wait): void {
            $this->success($msg, $url, $data, $wait);
        });
    }

    public function errorResponse(
        mixed $msg = '',
        ?string $url = null,
        mixed $data = '',
        ?int $wait = null
    ): Response {
        return $this->capture(function () use ($msg, $url, $data, $wait): void {
            $this->error($msg, $url, $data, $wait);
        });
    }

    public function resultResponse(mixed $data, ?int $code = null, mixed $msg = '', string $type = ''): Response
    {
        return $this->capture(function () use ($data, $code, $msg, $type): void {
            $this->result($data, $code, $msg, $type);
        });
    }

    public function redirectResponse(string $url, ?int $code = null): Response
    {
        return $this->capture(function () use ($url, $code): void {
            $this->redirect($url, $code);
        });
    }

    private function capture(Closure $callback): Response
    {
        try {
            $callback();
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }

        throw new \RuntimeException('Jump response was not thrown');
    }
}