# xqkeji 低代码生成器（指针 / pointer）

本仓库全部 `composer xqkeji:*` 脚手架命令的说明以根目录单一真相源为准，请勿在本文件重复内容：

1. 先读根目录 `AGENTS.md`（命令地图、心智模型、`@`/`~`/`select_` 约定、`-a` 追加用法、工作流示例）。
2. 精确参数查 `docs/commands.json`（机器可读）或 `docs/commands.md`（可读版）。
3. 改了任何命令后运行 `composer xqkeji:doc` 刷新 docs/，不要手工编辑 docs/。

前提：命令需在已安装本插件、含闭源扩展 `php-xqkeji` 的宿主业务工程内执行；本仓库不是自己的宿主。操作顺序通常先 `composer xqkeji:use <module>` 切到目标模块。
