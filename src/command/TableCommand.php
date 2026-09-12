<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\Table;

class TableCommand extends BaseCommand
{
    use NormalizesShortOptions;

    protected function configure()
    {
        $this->setName('xqkeji:table')
            ->setDescription('创建表格类')
            ->addArgument('name', InputArgument::OPTIONAL, '表格名称')
            ->addOption('element', 'e', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '表格元素列表（可多次使用，或用逗号分隔：id,Username）')
            ->addOption('tree', 'T', InputOption::VALUE_NONE, '创建树形表格（继承 TreegridTable）')
            ->setHelp(<<<'EOF'
创建表格类和表格元素

<info>用法示例：</info>

  <comment># 创建普通表格（继承 Table）</comment>
  composer xqkeji:table User

  <comment># 创建普通表格（带元素列表）</comment>
  composer xqkeji:table User -e id -e Username -e SwitchCheck -e LoginTime -e EditDelete
  composer xqkeji:table User -e id,Username,SwitchCheck,LoginTime,EditDelete

  <comment># 创建树形表格（继承 TreegridTable）</comment>
  composer xqkeji:table User -T -e id -e Username -e SwitchCheck -e LoginTime -e EditDelete

  <comment># 创建表格（创建新元素时交互式输入中文名称）</comment>
  composer xqkeji:table User -e id,username,switch_check,login_time,edit_delete

<info>说明：</info>

  - 表格名支持大小写，自动转为大驼峰（如 user → User、user_list → UserList）
  - 表格元素名支持小写加下划线或大驼峰，命令行时可以用小写加_或-的格式，自动转为大驼峰
  - 表格元素通过 -e/--element 指定（可多次使用，也可用逗号分隔：-e id,Username），创建树表时忽略该参数改用内置默认元素
  - -e 值可用引号包裹，引号内逗号分隔支持带空格：-e "User Name, Login Time"（无引号时逗号后请勿加空格，否则会被 shell 拆成多个参数）
  - 表格类创建在当前模块的 table/ 目录下
  - 表格元素创建在当前模块的 table/element/ 目录下
  - 如果元素在 base 模块已存在，使用 @ElementName 引入
  - 如果元素在当前模块已存在或新创建，使用 ~ElementName 引入
  - 创建新元素时会交互式询问中文名称，已存在的元素不会询问
  - 使用 -T/--tree 创建树形表格（继承 TreegridTable），不使用则继承 Table
  - 创建树形表格时会自动检查并复制对应的树状控制器动作类（controller/{表格名}/）
  - 需要先使用 xqkeji:use 切换到目标模块

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        
        // 如果没有提供表格名，显示帮助信息
        if (empty($name)) {
            $output->writeln($this->getSynopsis(true));
            $output->writeln('');
            $output->writeln($this->getProcessedHelp());
            return 0;
        }
        
        // 验证表格名称（支持大小写字母、数字和下划线）
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
            $output->writeln('<error>表格名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
            return 1;
        }
        
        $elements = $this->flattenElements($input->getOption('element'));
        $isTree = $input->getOption('tree');
        
        $table = new Table($this->getIO(), $this->requireComposer());
        $table->createTable($name, $elements, $input, $output, $isTree);
        
        return 0;
    }

    /**
     * 把 -e/--element 选项（IS_ARRAY，单个值可能为逗号分隔）展平为元素名数组
     */
    private function flattenElements($raw): array
    {
        $elements = [];
        foreach ((array) $raw as $item) {
            foreach (explode(',', (string) $item) as $el) {
                $el = trim($el);
                if ($el !== '') {
                    $elements[] = $el;
                }
            }
        }
        return $elements;
    }
}
