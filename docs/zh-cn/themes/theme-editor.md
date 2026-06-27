# 主题编辑器

状态：**Beta**。主题编辑器用于在后台安全编辑主题文件。入口：
**外观 -> 主题编辑器**。

编辑器不会改变 Theme API。它只能访问当前选择的主题，不能访问 FIREBALL CMS
核心文件、其他主题或外部路径。

## 功能

- 从主题文件树打开文件；
- 在允许的目录中创建文件和文件夹；
- 重命名和删除项目；
- 保存文本文件；
- 预览并替换 `assets/images` 中的图片；
- 查看备份历史；
- 恢复任意已保存版本；
- 编辑系统主题 `default` 时显示警告；
- 创建系统主题副本。

## 编辑区域

当前 Beta 使用普通的 `<textarea class="theme-editor-code">`。前端逻辑通过
editor adapter 访问编辑器，因此以后接入 Monaco Editor 或 CodeMirror 时，不需要
重写打开、保存、校验和恢复逻辑。

## 文件、校验和备份

可编辑文本格式：PHP、HTML、CSS、JavaScript、JSON、Markdown、TXT 和 SVG。
新文件只允许 `.php`、`.css`、`.js`、`.json`、`.md`、`.txt`。PHP 文件只能位于
`templates` 和 `partials`。

保存前会使用 `php -l` 检查 PHP。JSON 必须有效。`theme.json` 必须包含
`name`、`slug`、`version`、`author`、`description`、`preview`，并且 slug 必须与
主题目录一致。

每次保存、删除和恢复前都会在 `storage/theme-backups` 创建备份。每个文件最多保留
20 个版本，包含日期、用户、路径和大小。

## 安全限制

所有路径都限制在当前主题中。`realpath()` 用于防止访问外部位置。`../`、绝对路径、
隐藏路径段、符号链接、其他主题、CMS 核心文件和危险文件类型都会被拒绝。恢复操作会
校验备份 metadata 是否匹配当前主题和文件。

所有操作和错误都会写入 `storage/logs/theme-editor.log`。

## Beta 限制

编辑器目前不是完整 IDE。自动补全、可视化 diff 和 Child Themes 会在后续阶段提供。
