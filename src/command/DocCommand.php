<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\CommandProvider;

/**
 * xqkeji:doc —— 反射导出全部 xqkeji:* 命令的结构化清单。
 *
 * 直接读取每个命令【活的】 configure()（name/description/arguments/options/help），
 * 生成 docs/commands.json（机器/AI 读）与 docs/commands.md（人读）。因为数据来自命令定义本身，
 * 加参数、改说明后重跑一次即可刷新，文档与代码不会漂移。
 */
class DocCommand extends BaseCommand
{
    /** 需要过滤掉的 Console/Symfony 内置全局选项（非命令自身定义） */
    private const GLOBAL_OPTIONS = [
        'help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction',
    ];

    protected function configure()
    {
        $this->setName('xqkeji:doc')
            ->setDescription('导出全部 xqkeji:* 命令清单为 docs/commands.json 与 docs/commands.md（供 AI/文档快速读懂，改命令后重跑刷新）')
            ->addOption('output-dir', 'o', InputOption::VALUE_OPTIONAL, '输出目录（默认为插件仓库根下的 docs/）')
            ->setHelp(<<<'EOF'
反射导出全部命令的结构化清单，方便 AI 与文档快速读懂本生成器。

<info>用法示例：</info>

  <comment># 生成 docs/commands.json + docs/commands.md（默认输出到插件仓库根的 docs/）</comment>
  composer xqkeji:doc

  <comment># 指定输出目录</comment>
  composer xqkeji:doc -o ../docs
  composer xqkeji:doc --output-dir=/path/to/dir

<info>说明：</info>

  - 清单数据直接来自各命令 configure() 中的 setName/setDescription/addArgument/addOption/setHelp，
    新增选项或修改说明后重新运行本命令即可刷新，文档不会与代码脱节。
  - commands.json：结构化（name/description/source/arguments/options/help），供程序与 AI 读取。
  - commands.md：同一数据的可读版，含参数表与每个命令的完整 help。
  - 会自动过滤 Console 内置全局选项（--help/--quiet/--verbose/--version/--ansi/--no-ansi/--no-interaction）。
  - 默认输出到插件仓库自身根目录（本命令文件所在 src 的上一级）的 docs/，与在哪个工程里运行无关。
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // 本文件位于 {repoRoot}/src/command/DocCommand.php，向上两级才是插件仓库根
        $repoRoot = realpath(dirname(__DIR__, 2));
        if ($repoRoot === false) {
            $repoRoot = dirname(__DIR__, 2);
        }

        $outDir = $input->getOption('output-dir');
        if (empty($outDir)) {
            $outDir = $repoRoot . DIRECTORY_SEPARATOR . 'docs';
        }
        if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
            $this->getIO()->write("<error>无法创建输出目录: {$outDir}</error>");
            return 1;
        }

        $commands = (new CommandProvider())->getCommands();

        $data = [
            'generatedBy'  => 'xqkeji:doc',
            'sourceOfTruth' => '各命令 configure() 的运行时反射',
            'commandCount' => 0,
            'commands'     => [],
        ];
        foreach ($commands as $cmd) {
            $data['commands'][] = $this->describe($cmd, $repoRoot);
        }
        $data['commandCount'] = count($data['commands']);

        $jsonPath = $outDir . DIRECTORY_SEPARATOR . 'commands.json';
        $mdPath   = $outDir . DIRECTORY_SEPARATOR . 'commands.md';

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->getIO()->write('<error>JSON 编码失败</error>');
            return 1;
        }
        file_put_contents($jsonPath, $json . "\n");
        file_put_contents($mdPath, $this->renderMarkdown($data));

        $io = $this->getIO();
        $io->write("<info>✓ 已导出 {$data['commandCount']} 个命令清单：</info>");
        $io->write("  JSON:     {$jsonPath}");
        $io->write("  Markdown: {$mdPath}");
        return 0;
    }

    /**
     * 反射单个命令为结构化数组。
     */
    private function describe(BaseCommand $cmd, string $repoRoot): array
    {
        $definition = $cmd->getDefinition();

        $arguments = [];
        foreach ($definition->getArguments() as $arg) {
            $arguments[] = [
                'name'        => $arg->getName(),
                'required'    => $arg->isRequired(),
                'isArray'     => $arg->isArray(),
                'description' => $arg->getDescription(),
            ];
        }

        $options = [];
        foreach ($definition->getOptions() as $opt) {
            if (in_array($opt->getName(), self::GLOBAL_OPTIONS, true)) {
                continue;
            }
            $valueMode = 'none';
            if ($opt->isValueRequired()) {
                $valueMode = 'required';
            } elseif ($opt->isValueOptional()) {
                $valueMode = 'optional';
            }
            $options[] = [
                'name'        => $opt->getName(),
                'shortcut'    => $opt->getShortcut(),
                'acceptValue' => $opt->acceptValue(),
                'valueMode'   => $valueMode,
                'isArray'     => $opt->isArray(),
                'description' => $opt->getDescription(),
            ];
        }

        return [
            'name'        => $cmd->getName(),
            'description' => $cmd->getDescription(),
            'source'      => $this->relativeSource($cmd, $repoRoot),
            'arguments'   => $arguments,
            'options'     => $options,
            'help'        => (string) $cmd->getHelp(),
        ];
    }

    /**
     * 取命令类文件相对仓库根的路径（src/command/XxxCommand.php）。
     */
    private function relativeSource(BaseCommand $cmd, string $repoRoot): string
    {
        try {
            $file = (new \ReflectionClass($cmd))->getFileName() ?: '';
        } catch (\Throwable $e) {
            return '';
        }
        $file = str_replace('\\', '/', $file);
        $root = str_replace('\\', '/', rtrim($repoRoot, '/\\')) . '/';
        if ($file !== '' && strpos($file, $root) === 0) {
            return substr($file, strlen($root));
        }
        return basename($file);
    }

    /**
     * 把结构化清单渲染为可读 Markdown。
     */
    private function renderMarkdown(array $data): string
    {
        $lines = [];
        $lines[] = '# xqkeji 生成器命令清单';
        $lines[] = '';
        $lines[] = '> 本文件由 `composer xqkeji:doc` 从各命令 configure() 反射自动生成，请勿手工编辑；改命令后重跑刷新。';
        $lines[] = '> 共 ' . $data['commandCount'] . ' 个命令。机器可读版见同目录 `commands.json`。';
        $lines[] = '';

        // 概览表
        $lines[] = '## 命令一览';
        $lines[] = '';
        $lines[] = '| 命令 | 说明 | 源文件 |';
        $lines[] = '| --- | --- | --- |';
        foreach ($data['commands'] as $c) {
            $desc = str_replace(['|', "\n"], ['\\|', ' '], (string) $c['description']);
            $lines[] = '| `' . $c['name'] . '` | ' . $desc . ' | ' . ($c['source'] ?: '-') . ' |';
        }
        $lines[] = '';

        // 逐命令详情
        foreach ($data['commands'] as $c) {
            $lines[] = '## ' . $c['name'];
            $lines[] = '';
            $lines[] = (string) $c['description'];
            $lines[] = '';
            $lines[] = '- 源文件：`' . ($c['source'] ?: '-') . '`';
            $lines[] = '';

            if (!empty($c['arguments'])) {
                $lines[] = '### 位置参数';
                $lines[] = '';
                $lines[] = '| 名称 | 必填 | 数组 | 说明 |';
                $lines[] = '| --- | --- | --- | --- |';
                foreach ($c['arguments'] as $a) {
                    $d = str_replace(['|', "\n"], ['\\|', ' '], (string) $a['description']);
                    $lines[] = '| `' . $a['name'] . '` | ' . ($a['required'] ? '是' : '否')
                        . ' | ' . ($a['isArray'] ? '是' : '否') . ' | ' . $d . ' |';
                }
                $lines[] = '';
            }

            if (!empty($c['options'])) {
                $lines[] = '### 选项';
                $lines[] = '';
                $lines[] = '| 选项 | 短名 | 取值 | 数组 | 说明 |';
                $lines[] = '| --- | --- | --- | --- | --- |';
                foreach ($c['options'] as $o) {
                    $d = str_replace(['|', "\n"], ['\\|', ' '], (string) $o['description']);
                    $valueLabel = ['none' => '不需要值', 'optional' => '可选值', 'required' => '需要值'][$o['valueMode']] ?? $o['valueMode'];
                    $lines[] = '| `--' . $o['name'] . '` | ' . ($o['shortcut'] ? '-' . $o['shortcut'] : '-')
                        . ' | ' . $valueLabel . ' | ' . ($o['isArray'] ? '是' : '否') . ' | ' . $d . ' |';
                }
                $lines[] = '';
            }

            if (trim((string) $c['help']) !== '') {
                $lines[] = '### 帮助 / 示例';
                $lines[] = '';
                $lines[] = '```';
                $lines[] = rtrim($this->stripAnsi((string) $c['help']));
                $lines[] = '```';
                $lines[] = '';
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * 去掉 help 文本里的 Symfony Console 标记（<info>/<comment>/<question> 等）与 ANSI 码，保留可读正文。
     */
    private function stripAnsi(string $text): string
    {
        $text = preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text;
        $text = preg_replace('/<\/?(?:info|comment|question|error|fg=\w+|href=[^>]+)>/', '', $text) ?? $text;
        return $text;
    }
}
