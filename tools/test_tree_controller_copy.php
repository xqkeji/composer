<?php
/**
 * 树状控制器自动复制逻辑的等价功能验证（隔离测试）
 *
 * 说明：composer xqkeji:table 的完整 e2e 需要 wnmmp/php 运行时 + edu 的 vendor/
 * （本沙箱未安装），因此这里用与 Table::ensureTreeController 完全一致的算法，
 * 作用于真实的 src/example/src/controller/tree 模板，验证：
 *   1. 目标 controller/{ClassName}/ 目录被创建
 *   2. 模板文件被复制
 *   3. 命名空间占位符 {MODULE_NAME}/{CONTROLLER_NAME} 被正确替换
 *   4. 已存在目录时跳过（幂等）
 */

$srcDir = 'F:/docker/code/php/composer/src';
$tmpRoot = sys_get_temp_dir() . '/tree_copy_test_' . getmypid();
@mkdir($tmpRoot, 0777, true);

// 模拟一个模块路径
$modulePath = $tmpRoot . '/app/edu';
@mkdir($modulePath . '/controller', 0777, true);

$currentModule = 'edu';
$className = 'TestTree'; // 对应表格名 test_tree

// ---- 与 Table::ensureTreeController 完全一致的算法 ----
function ensureTreeController(string $modulePath, string $currentModule, string $className, string $srcDir): array
{
    $log = [];
    $controllerDir = $modulePath . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . $className;

    if (is_dir($controllerDir)) {
        $log[] = "SKIP: 目录已存在 {$controllerDir}";
        return $log;
    }

    $treeTemplateDir = $srcDir . DIRECTORY_SEPARATOR . 'example' . DIRECTORY_SEPARATOR
        . 'src' . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . 'tree';

    if (!is_dir($treeTemplateDir)) {
        $log[] = "ERROR: 模板目录缺失 {$treeTemplateDir}";
        return $log;
    }

    if (!mkdir($controllerDir, 0755, true) && !is_dir($controllerDir)) {
        $log[] = "ERROR: 创建目录失败 {$controllerDir}";
        return $log;
    }
    $log[] = "MKDIR: {$controllerDir}";

    $files = glob($treeTemplateDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
    foreach ($files as $templateFile) {
        $content = file_get_contents($templateFile);
        $content = str_replace(
            ['{MODULE_NAME}', '{CONTROLLER_NAME}'],
            [$currentModule, $className],
            $content
        );
        $target = $controllerDir . DIRECTORY_SEPARATOR . basename($templateFile);
        file_put_contents($target, $content);
        $log[] = "COPY: " . basename($templateFile) . " -> {$target}";
    }
    return $log;
}

echo "=== 第一次复制（应创建并复制）===\n";
foreach (ensureTreeController($modulePath, $currentModule, $className, $srcDir) as $l) {
    echo "  {$l}\n";
}

echo "\n=== 第二次复制（应幂等跳过）===\n";
foreach (ensureTreeController($modulePath, $currentModule, $className, $srcDir) as $l) {
    echo "  {$l}\n";
}

echo "\n=== 校验生成文件 ===\n";
$controllerDir = $modulePath . '/controller/' . $className;
$expectNs = "xqkeji\\app\\edu\\controller\\TestTree";
$ok = true;
foreach (['Add.php', 'Admin.php', 'Move.php'] as $f) {
    $p = $controllerDir . '/' . $f;
    if (!is_file($p)) {
        echo "  FAIL: 缺失 {$p}\n";
        $ok = false;
        continue;
    }
    $c = file_get_contents($p);
    $hasNs = strpos($c, "namespace {$expectNs};") !== false;
    $noPlaceholder = strpos($c, '{MODULE_NAME}') === false && strpos($c, '{CONTROLLER_NAME}') === false;
    $status = ($hasNs && $noPlaceholder) ? 'OK' : 'FAIL';
    if ($status === 'FAIL') $ok = false;
    echo "  [{$status}] {$f}: namespace=" . ($hasNs ? 'yes' : 'no') . " placeholder残留=" . ($noPlaceholder ? 'no' : 'yes') . "\n";
    echo "    --- head ---\n" . preg_replace('/^/m', '    ', implode("\n", array_slice(explode("\n", $c), 0, 4))) . "\n";
}

echo "\n=== 清理临时目录 ===\n";
array_map('unlink', glob($controllerDir . '/*.php'));
rmdir($controllerDir);
rmdir($modulePath . '/controller');
rmdir($modulePath);
rmdir($tmpRoot);
echo "  done\n";

exit($ok ? 0 : 1);
