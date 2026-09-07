<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\Context;
use Composer\Factory;

class ModelCommand extends BaseCommand
{
    use NormalizesShortOptions;

    protected function configure()
    {
        $this->setName('xqkeji:model')
            ->setDescription('创建模型类')
            ->addArgument('name', InputArgument::OPTIONAL, '模型名称')
            ->setHelp(<<<'EOF'
创建模型类

<info>用法示例：</info>

  <comment># 创建模型类</comment>
  composer xqkeji:model user
  composer xqkeji:model user_type
  composer xqkeji:model UserType

<info>说明：</info>

  - 模型名称支持大小写，自动转为大驼峰（如 user_type → UserType）
  - 模型类创建在当前模块的 model/ 目录下
  - 继承自 xqkeji\mvc\model\Model 类
  - 需要先使用 xqkeji:use 切换到目标模块

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        
        // 如果没有提供模型名，显示帮助信息
        if (empty($name)) {
            $output->writeln($this->getSynopsis(true));
            $output->writeln('');
            $output->writeln($this->getProcessedHelp());
            return 0;
        }
        
        // 验证模型名称（支持大小写字母、数字和下划线）
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
            $output->writeln('<error>模型名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
            return 1;
        }
        
        $context = new Context($this->getIO(), $this->requireComposer());
        
        // 获取当前模块
        $currentModule = $context->getCurrentModule();
        if ($currentModule === null) {
            $output->writeln('<error>未设置当前模块，请先使用 composer xqkeji:use -- module_name</error>');
            return 1;
        }
        
        // 获取有效的模块路径（支持本地模块和 composer 模块）
        $modulePath = $context->getValidModulePath();
        if ($modulePath === null) {
            $output->writeln("<error>模块 '{$currentModule}' 无效或不存在，请检查：</error>");
            $output->writeln('  1. 模块是否在 app/ 目录下存在');
            $output->writeln('  2. 模块是否是 composer 模块（通过 config/composer.php 配置）');
            $output->writeln('  3. 模块的 config/acl.php 文件是否存在');
            return 1;
        }
        
        // 转换为大驼峰类名
        $className = $this->toCamelCase($name);
        
        // 创建 model 目录
        $modelPath = $modulePath . DIRECTORY_SEPARATOR . 'model';
        if (!is_dir($modelPath)) {
            mkdir($modelPath, 0755, true);
        }
        
        // 创建模型文件
        $filePath = $modelPath . DIRECTORY_SEPARATOR . $className . '.php';
        if (is_file($filePath)) {
            $output->writeln("<error>模型已存在: $filePath</error>");
            return 1;
        }
        
        // 生成模型类内容
        $content = $this->generateModelContent($currentModule, $className);
        file_put_contents($filePath, $content);
        
        $output->writeln("<info>✓ 模型已创建: $filePath</info>");
        
        return 0;
    }
    
    /**
     * 生成模型类内容
     */
    private function generateModelContent(string $moduleName, string $className): string
    {
        $namespace = "app\\{$moduleName}\\model";
        
        return <<<PHP
<?php
namespace {$namespace};

use xqkeji\mvc\model\Model;

class {$className} extends Model
{

}

PHP;
    }
    
    /**
     * 将下划线命名转换为大驼峰命名
     */
    private function toCamelCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
}
