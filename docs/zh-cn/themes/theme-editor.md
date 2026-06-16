# 主题编辑器

在管理后台打开 **外观 → 主题编辑器**。编辑器只能访问当前选择的主题，不能访问
FIREBALL CMS 核心文件。

可以以文本方式编辑 PHP、HTML、CSS、JavaScript、JSON、Markdown 和 TXT 文件。
`assets/images` 中已有的图片可以预览和替换。

新文件只允许使用 `.php`、`.css`、`.js`、`.json`、`.md` 或 `.txt` 扩展名。
只能在 `templates`、`partials` 和 `assets` 中创建文件夹。PHP 文件只能放在
`templates` 和 `partials` 中。文本文件最大 1 MB，图片最大 5 MB。

保存前会检查 PHP 语法。JSON 必须有效，`theme.json` 必须包含 `name`、`slug`、
`version`、`author`、`description` 和 `preview`。

保存、删除和恢复前会在 `storage/theme-backups` 中创建备份，每个文件保留最近
20 个版本。

所有路径都通过 `realpath()` 验证。绝对路径、目录穿越、隐藏文件、符号链接、
禁止的扩展名、其他主题和核心文件都会被拒绝。操作日志保存在
`storage/logs/theme-editor.log`。
