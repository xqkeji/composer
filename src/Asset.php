<?php
namespace xqkeji\composer;

class Asset
{
    public static function createDir($path, $mode = 0775, $recursive = true)
    {
        if (is_dir($path)) {
            return true;
        }
        $parentDir = dirname($path);
        if ($recursive && !is_dir($parentDir) && $parentDir !== $path) {
            static::createDir($parentDir, $mode, true);
        }
        try {
            if (!mkdir($path, $mode)) {
                return false;
            }
        } catch (\Exception $e) {
            if (!is_dir($path)) {
                throw new \Exception("Failed to create directory \"$path\": " . $e->getMessage(), $e->getCode());
            }
        }
        try {
            return chmod($path, $mode);
        } catch (\Exception $e) {
            throw new \Exception("Failed to change permissions for directory \"$path\": " . $e->getMessage(), $e->getCode());
        }
    }
    public static function copyDirs(array $paths) : void
    {
        if(!empty($paths))
        {
            foreach($paths as $src=>$dst)
            {
                self::copyDir($src,$dst);
            }
        }
    }
    public static function rmDirs(array $paths) : void
    {
        if(!empty($paths))
        {
            foreach($paths as $dst)
            {
                self::rmDir($dst);
            }
        }
    }
    public static function copyDir(string $src,string $dst) : void
    {
        $dstExists = is_dir($dst);
        if (!$dstExists) {
            self::createDir($dst);
        }

        $handle = opendir($src);
        if ($handle === false) {
            throw new \InvalidArgumentException("Unable to open directory: $src");
        }
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $from = $src . DIRECTORY_SEPARATOR . $file;
            $to = $dst . DIRECTORY_SEPARATOR . $file;
            if (is_file($from)) {
                copy($from, $to);
                @chmod($to, 0775);

            } else {
                static::copyDir($from, $to);
            }
        }
        closedir($handle);
    }

    /**
     * 递归删除目录。
     *
     * Windows 上不再吞掉文件删除失败：任何一项删不掉都会抛出带路径的异常，
     * 并在删除前给出残留项，避免最后只报含糊的 "Directory not empty"。
     */
    public static function rmDir(string $dir)
    {
        clearstatcache(true, $dir);
        if (!is_dir($dir)) {
            return;
        }
        if (DIRECTORY_SEPARATOR === '\\') {
            // 目录自身只读也会导致子文件删不掉。
            @chmod($dir, 0775);
        }

        // 链接/junction 直接删链接本身，绝不递归进入目标目录。
        if (static::isLocalLink($dir)) {
            static::unlink($dir);
            return;
        }

        $handle = @opendir($dir);
        if ($handle === false) {
            $error = error_get_last();
            throw new \RuntimeException("无法打开目录: $dir" . ($error ? ' - ' . $error['message'] : ''));
        }
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            clearstatcache(true, $path);
            if (is_link($path) || static::isLocalLink($path)) {
                static::unlink($path);
            } elseif (is_dir($path)) {
                static::rmDir($path);
            } else {
                static::unlink($path);
            }
        }
        closedir($handle);

        clearstatcache(true, $dir);
        $entries = @scandir($dir);
        if ($entries === false) {
            if (!is_dir($dir)) {
                return;
            }
            $error = error_get_last();
            throw new \RuntimeException("无法读取目录: $dir" . ($error ? ' - ' . $error['message'] : ''));
        }
        $remain = array_values(array_diff($entries, ['.', '..']));
        if (!empty($remain)) {
            throw new \RuntimeException("目录删除失败，仍有残留项: $dir (" . implode(', ', $remain) . ")");
        }
        if (DIRECTORY_SEPARATOR === '\\') {
            @chmod($dir, 0775);
        }
        if (!@rmdir($dir)) {
            $error = error_get_last();
            throw new \RuntimeException("无法删除目录: $dir" . ($error ? ' - ' . $error['message'] : ''));
        }
    }

    /**
     * 删除文件或目录链接。
     *
     * Windows 上先清除只读属性，再重试若干次；最终失败时抛出异常并携带系统错误信息。
     */
    public static function unlink(string $path)
    {
        clearstatcache(true, $path);
        $isDirLink = static::isLocalLink($path) && is_dir($path);

        if (DIRECTORY_SEPARATOR === '\\' && !$isDirLink) {
            // Windows 的 chmod 实际是切换只读属性，0666 可清除只读位。
            // 不要先用 is_file() 判断：权限不足时 is_file() 也会返回 false。
            @chmod($path, 0666);
            // chmod 在某些 ACL/文件系统上不生效，attrib 再兜底清一次只读。
            @exec('attrib -R "' . str_replace('/', '\\', $path) . '" 2>NUL');
            clearstatcache(true, $path);
        }

        $attempts = DIRECTORY_SEPARATOR === '\\' ? 8 : 2;
        $lastError = null;
        for ($i = 0; $i < $attempts; $i++) {
            // 无条件尝试删除。file_exists()/is_link() 在权限不足时都可能返回 false，
            // 不能拿它们当“已经删掉”的依据。
            $ok = $isDirLink ? @rmdir($path) : @unlink($path);
            if ($ok) {
                return true;
            }
            $error = error_get_last();
            $message = $error['message'] ?? '';
            $lastError = $message !== '' ? $message : 'unknown error';

            // 只有明确是“文件不存在”才视为已删除；权限错误不能吞掉。
            if (stripos($message, 'No such file') !== false
                || stripos($message, '系统找不到指定的文件') !== false) {
                if (!@lstat($path)) {
                    return true;
                }
            }
            if ($i + 1 < $attempts) {
                usleep(DIRECTORY_SEPARATOR === '\\' ? 200000 : 100000);
            }
        }
        throw new \RuntimeException("无法删除" . ($isDirLink ? '链接目录' : '文件') . ": $path - $lastError");
    }

    /**
     * 判断路径是否为 symlink 或 Windows junction。
     * junction 的 is_link() 返回 false，需要用 realpath 与自身路径比对来识别。
     */
    private static function isLocalLink(string $path): bool
    {
        if (is_link($path)) {
            return true;
        }
        if (DIRECTORY_SEPARATOR !== '\\') {
            return false;
        }
        $absolute = $path;
        $isAbsolute = strlen($path) >= 3
            && $path[1] === ':'
            && ($path[2] === '\\' || $path[2] === '/');
        if (!$isAbsolute) {
            $absolute = getcwd() . DIRECTORY_SEPARATOR . $path;
        }
        $normalized = rtrim(str_replace('/', '\\', $absolute), '\\');
        $real = realpath($path);
        return $real !== false && strcasecmp(rtrim($real, '\\'), $normalized) !== 0;
    }
}
