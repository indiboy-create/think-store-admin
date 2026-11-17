<?php

declare(strict_types=1);

namespace youvision\store\admin\support;

use think\facade\Config;
use think\facade\Filesystem;
use think\file\UploadedFile;
use think\Request;

/**
 * Layui upload 服务端接口。
 *
 * 基于 topthink/think-filesystem 存储文件，返回 Layui upload 约定的
 * {code: 0, msg: '', data: {src, ...}} 结构。
 */
final class LayuiUploads
{
    public const ERROR = 1;

    /**
     * 处理上传请求
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function handle(Request $request, array $options = []): array
    {
        $options = self::options($options);

        if (!(bool) ($options['enabled'] ?? true)) {
            return self::error('上传接口未启用');
        }

        try {
            $files = self::normalize($request->file((string) ($options['field'] ?? 'file')));

            if ($files === []) {
                return self::error('未选择上传文件');
            }

            $maxFiles = max(1, (int) ($options['max_files'] ?? 9));

            if (count($files) > $maxFiles) {
                return self::error('最多上传 ' . $maxFiles . ' 个文件');
            }

            $metas = [];

            foreach ($files as $file) {
                $metas[] = self::validate($file, $options);
            }

            $items = [];
            $storedPaths = [];

            try {
                foreach ($files as $index => $file) {
                    $item = self::store($file, $metas[$index], $options);
                    $items[] = $item;
                    $storedPaths[] = $item['path'];
                }
            } catch (\Throwable $e) {
                foreach ($storedPaths as $path) {
                    self::deletePath($path, $options);
                }

                throw $e;
            }

            return self::response($items, $options);
        } catch (\Throwable $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function single(UploadedFile $file, array $options = []): array
    {
        try {
            $options = self::options($options);

            return self::response([
                self::store($file, self::validate($file, $options), $options),
            ], $options);
        } catch (\Throwable $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * @param array<int, UploadedFile> $files
     * @param array<string, mixed>     $options
     * @return array<string, mixed>
     */
    public static function multiple(array $files, array $options = []): array
    {
        $options = self::options($options);
        $maxFiles = max(1, (int) ($options['max_files'] ?? 9));

        if ($files === []) {
            return self::error('未选择上传文件');
        }

        if (count($files) > $maxFiles) {
            return self::error('最多上传 ' . $maxFiles . ' 个文件');
        }

        try {
            $metas = [];
            foreach ($files as $file) {
                $metas[] = self::validate($file, $options);
            }

            $items = [];
            try {
                foreach ($files as $index => $file) {
                    $items[] = self::store($file, $metas[$index], $options);
                }
            } catch (\Throwable $e) {
                foreach ($items as $item) {
                    self::deletePath($item['path'], $options);
                }

                throw $e;
            }

            return self::response($items, $options);
        } catch (\Throwable $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * 校验单个文件，失败时抛出异常
     *
     * @param array<string, mixed> $options
     * @return array{name: string, ext: string, mime: string, size: int}
     */
    public static function validate(UploadedFile $file, array $options = []): array
    {
        $options = self::options($options);

        if (!$file->isValid()) {
            throw new \RuntimeException('上传文件无效');
        }

        $size = (int) $file->getSize();

        if ($size <= 0) {
            throw new \RuntimeException('上传文件为空');
        }

        if ($size > (int) ($options['max_size'] ?? 10485760)) {
            throw new \RuntimeException('文件大小超过限制');
        }

        $name = basename((string) $file->getOriginalName());
        $ext = strtolower((string) $file->getOriginalExtension());

        if ($ext === '') {
            throw new \RuntimeException('文件后缀不能为空');
        }

        $allowed = array_map('strtolower', (array) ($options['allowed_ext'] ?? []));
        $denied = array_map('strtolower', (array) ($options['denied_ext'] ?? []));

        if (!in_array('*', $allowed, true) && !in_array($ext, $allowed, true)) {
            throw new \RuntimeException('不允许上传该文件类型');
        }

        if (in_array($ext, $denied, true)) {
            throw new \RuntimeException('不允许上传该文件类型');
        }

        try {
            $mime = strtolower((string) $file->getMime());
        } catch (\Throwable) {
            $mime = strtolower((string) $file->getOriginalMime());
        }

        $allowedMime = array_map('strtolower', (array) ($options['allowed_mime'] ?? []));

        if ($allowedMime !== [] && !in_array($mime, $allowedMime, true)) {
            throw new \RuntimeException('文件 MIME 类型不允许');
        }

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)
            && @getimagesize($file->getRealPath()) === false) {
            throw new \RuntimeException('图片文件内容无效');
        }

        return [
            'name' => $name,
            'ext' => $ext,
            'mime' => $mime,
            'size' => $size,
        ];
    }

    /**
     * 保存单个文件
     *
     * @param array{name: string, ext: string, mime: string, size: int} $meta
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function store(UploadedFile $file, array $meta, array $options = []): array
    {
        $options = self::options($options);
        $disk = (string) ($options['disk'] ?? 'public');

        try {
            $driver = Filesystem::disk($disk);
        } catch (\Throwable) {
            throw new \RuntimeException('文件存储磁盘不可用');
        }

        $path = self::path($options);
        $name = bin2hex(random_bytes(16)) . '.' . $meta['ext'];

        try {
            $storedPath = $driver->putFileAs($path, $file, $name);
        } catch (\Throwable) {
            throw new \RuntimeException('文件保存失败');
        }

        if (!is_string($storedPath) || $storedPath === '') {
            throw new \RuntimeException('文件保存失败');
        }

        $url = self::url($storedPath, $driver, $options);

        return [
            'src' => $url,
            'url' => $url,
            'path' => $storedPath,
            'name' => $meta['name'],
            'size' => $meta['size'],
            'ext' => $meta['ext'],
            'mime' => $meta['mime'],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed>             $options
     * @return array<string, mixed>
     */
    public static function response(array $items, array $options = []): array
    {
        $multiple = count($items) > 1 || (bool) ($options['multiple'] ?? false);

        return [
            'code' => 0,
            'msg' => '',
            'data' => $multiple ? $items : ($items[0] ?? null),
            'files' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function error(string $msg, int $code = self::ERROR): array
    {
        return [
            'code' => $code,
            'msg' => $msg,
            'data' => null,
            'files' => [],
        ];
    }

    /**
     * 删除已存储文件
     *
     * @param array<string, mixed> $options
     */
    public static function remove(string $path, array $options = []): bool
    {
        $options = self::options($options);

        try {
            Filesystem::disk((string) ($options['disk'] ?? 'public'))->delete($path);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param mixed $file
     * @return array<int, UploadedFile>
     */
    private static function normalize($file): array
    {
        if ($file instanceof UploadedFile) {
            return [$file];
        }

        if (!is_array($file)) {
            return [];
        }

        $files = [];

        foreach ($file as $item) {
            if ($item instanceof UploadedFile) {
                $files[] = $item;
            } elseif (is_array($item)) {
                $files = array_merge($files, self::normalize($item));
            }
        }

        return $files;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function options(array $options = []): array
    {
        $default = [
            'enabled' => true,
            'field' => 'file',
            'disk' => 'public',
            'path' => 'uploads/{date}',
            'url_prefix' => '/storage',
            'allowed_ext' => [
                'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
                'pdf', 'zip', 'rar', '7z',
                'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt',
            ],
            'denied_ext' => [
                'php', 'phtml', 'phar', 'jsp', 'asp', 'aspx',
                'exe', 'sh', 'bat', 'cmd', 'html', 'htm', 'svg', 'js', 'xml',
            ],
            'allowed_mime' => [],
            'max_size' => 10 * 1024 * 1024,
            'max_files' => 9,
            'multiple' => false,
        ];

        try {
            $config = Config::get('store_admin.upload', []);
        } catch (\Throwable) {
            $config = [];
        }

        return array_replace($default, is_array($config) ? $config : [], $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function path(array $options): string
    {
        $path = str_replace(
            ['{date}', '{Y}', '{m}', '{d}'],
            [date('Ymd'), date('Y'), date('m'), date('d')],
            (string) ($options['path'] ?? 'uploads/{date}')
        );
        $path = trim(str_replace('\\', '/', $path), '/');

        if ($path === '' || strpos($path, '..') !== false) {
            throw new \RuntimeException('上传目录配置无效');
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function url(string $path, object $driver, array $options): string
    {
        try {
            return $driver->url($path);
        } catch (\Throwable) {
            $prefix = trim((string) ($options['url_prefix'] ?? ''), '/');

            return ($prefix === '' ? '/' : '/' . $prefix . '/') . ltrim($path, '/');
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function deletePath(string $path, array $options): void
    {
        try {
            Filesystem::disk((string) ($options['disk'] ?? 'public'))->delete($path);
        } catch (\Throwable) {
        }
    }
}