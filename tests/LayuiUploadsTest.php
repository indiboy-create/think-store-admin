<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use think\App;
use think\Request;
use think\file\UploadedFile;
use PHPUnit\Framework\TestCase;
use youvision\store\admin\controller\UploadController;
use youvision\store\admin\support\layui\LayuiUploads;

final class LayuiUploadsTest extends TestCase
{
    private string $root;

    private App $app;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__) . '/runtime/test-uploads-' . bin2hex(random_bytes(4));
        $this->app = new App(dirname(__DIR__));

        $config = (array) require dirname(__DIR__) . '/src/config.php';
        $config['upload']['disk'] = 'public';
        $config['upload']['allowed_ext'] = ['png', 'jpg', 'jpeg', 'txt'];
        $config['upload']['max_size'] = 1024;
        $config['upload']['max_files'] = 2;

        $this->app->config->set($config, 'store_admin');
        $this->app->config->set([
            'default' => 'local',
            'disks' => [
                'public' => [
                    'type' => 'local',
                    'root' => $this->root,
                    'url' => '/storage',
                    'visibility' => 'public',
                ],
            ],
        ], 'filesystem');
    }

    private function uploadedFile(string $name, string $content, string $mime = 'application/octet-stream'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'layui-upload-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, $mime, UPLOAD_ERR_OK, true);
    }

    /**
     * 模拟 Request::file() 返回 UploadedFile，避免依赖真实 $_FILES
     *
     * @param mixed $upload
     */
    private function requestWith($upload): Request
    {
        return new class($upload) extends Request {
            public function __construct(private mixed $upload)
            {
                parent::__construct();
            }

            public function file(string $name = '')
            {
                return $this->upload;
            }
        };
    }

    private function pngFile(string $name = 'photo.png'): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );

        return $this->uploadedFile($name, (string) $png, 'image/png');
    }

    public function testValidateAndUploadSingle(): void
    {
        $file = $this->pngFile();
        $meta = LayuiUploads::validate($file);

        self::assertSame('png', $meta['ext']);
        self::assertSame('image/png', $meta['mime']);

        $result = LayuiUploads::single($file);

        self::assertSame(0, $result['code']);
        self::assertSame('/storage/uploads/' . date('Ymd') . '/' . basename($result['data']['path']), $result['data']['src']);
        self::assertStringEndsWith('.png', $result['data']['path']);
        self::assertFileExists($this->root . '/' . $result['data']['path']);
    }

    public function testHandleRequest(): void
    {
        $file = $this->pngFile();
        $request = $this->requestWith($file);

        $result = LayuiUploads::handle($request);

        self::assertSame(0, $result['code']);
        self::assertSame('photo.png', $result['data']['name']);
        self::assertStringStartsWith('/storage/uploads/', $result['data']['src']);
    }

    public function testMultipleUpload(): void
    {
        $first = $this->pngFile('a.png');
        $second = $this->pngFile('b.png');
        $request = $this->requestWith([$first, $second]);

        $result = LayuiUploads::handle($request);

        self::assertSame(0, $result['code']);
        self::assertCount(2, $result['data']);
        self::assertCount(2, $result['files']);
        self::assertNotSame($result['data'][0]['path'], $result['data'][1]['path']);
    }

    public function testInvalidExtension(): void
    {
        $result = LayuiUploads::single($this->uploadedFile('shell.php', '<?php echo 1;'));

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('文件类型', $result['msg']);
    }

    public function testOversizeFile(): void
    {
        $result = LayuiUploads::single($this->uploadedFile('large.png', str_repeat('a', 2048), 'image/png'));

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('大小', $result['msg']);
    }

    public function testFakeImageIsRejected(): void
    {
        $result = LayuiUploads::single($this->uploadedFile('fake.png', 'not an image', 'image/png'));

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('图片', $result['msg']);
    }

    public function testTooManyFiles(): void
    {
        $result = LayuiUploads::multiple([
            $this->pngFile('a.png'),
            $this->pngFile('b.png'),
            $this->pngFile('c.png'),
        ]);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('最多上传', $result['msg']);
    }

    public function testMissingFile(): void
    {
        $result = LayuiUploads::handle(new Request());

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('未选择', $result['msg']);
    }

    public function testControllerReturnsJsonResponse(): void
    {
        $request = $this->requestWith($this->pngFile());
        $response = (new UploadController())->upload($request);

        self::assertSame(200, $response->getCode());
        self::assertStringContainsString('"code":0', (string) $response->getContent());
    }
}