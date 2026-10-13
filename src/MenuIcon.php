<?php
namespace xqkeji\composer;

/**
 * 菜单图标推荐（bootstrap-icons 类名）
 *
 * 纯离线关键词表：中文按菜单标题子串匹配，英文按控制器蛇形词根精确匹配，
 * 都没命中时按控制器名做确定性哈希从兜底池取值（同名恒同图标，但不同控制器不会全一样）。
 * 表内图标名均已对 vendor/xqkeji/xq-com-bootstrap-icons 的 bootstrap-icons.css 校验存在。
 */
class MenuIcon
{
    /**
     * 兜底默认图标（词表与兜底池都不可用时）
     */
    public const DEFAULT_ICON = 'bi bi-list';

    /**
     * 图标 => 关键词（中文词按标题子串匹配，英文词按控制器词根精确匹配）
     */
    private const ICONS = [
        'bi-person' => ['用户', '会员', '客户', '员工', '人员', '粉丝', 'user', 'users', 'member', 'members', 'account', 'accounts', 'customer', 'customers', 'staff', 'employee', 'employees', 'person', 'follower', 'fans'],
        'bi-person-badge' => ['管理员', '超级管理员', 'administrator'],
        'bi-building' => ['部门', '组织', '机构', '单位', '公司', 'dept', 'depts', 'department', 'departments', 'organization', 'organisation', 'org', 'unit', 'company', 'companies', 'corp', 'agency'],
        'bi-shield-lock' => ['权限', '授权', '鉴权', '越权', 'role', 'roles', 'permission', 'permissions', 'privilege', 'privileges', 'acl', 'authority'],
        'bi-key' => ['密码', '密钥', '口令', 'password', 'passwords', 'pwd', 'secret', 'secrets', 'token', 'apikey'],
        'bi-list-nested' => ['菜单', '导航', 'menu', 'menus', 'nav', 'navigation'],
        'bi-file-earmark-text' => ['文章', '内容', '资讯', '新闻', '稿件', '博文', 'article', 'articles', 'post', 'posts', 'content', 'contents', 'news', 'draft', 'drafts'],
        'bi-folder2' => ['分类', '栏目', '目录', 'category', 'categories', 'cate', 'cates', 'column', 'columns', 'catalog', 'catalogue'],
        'bi-tags' => ['标签', 'tag', 'tags', 'label', 'labels'],
        'bi-bag-fill' => ['商品', '产品', '货品', '宝贝', 'goods', 'good', 'product', 'products', 'item', 'items', 'merchandise', 'sku'],
        'bi-receipt' => ['订单', '工单', '运单', 'order', 'orders', 'invoice'],
        'bi-box-seam' => ['库存', '仓库', '仓储', '出入库', 'stock', 'inventory', 'warehouse', 'warehouses', 'storage'],
        'bi-currency-dollar' => ['财务', '金额', '支付', '结算', '账单', '交易', '收费', '资金', '价格', '充值', '提现', '钱包', 'finance', 'money', 'amount', 'payment', 'payments', 'pay', 'bill', 'bills', 'settle', 'settlement', 'trade', 'wallet', 'price', 'prices', 'cash', 'fee', 'refund'],
        'bi-bar-chart-fill' => ['统计', '报表', '数据', '图表', '分析', '看板', 'stat', 'stats', 'statistic', 'statistics', 'report', 'reports', 'data', 'chart', 'charts', 'analysis', 'dashboard', 'dashboards'],
        'bi-journal-text' => ['日志', '记录', '履历', 'log', 'logs', 'record', 'records', 'history', 'journals'],
        'bi-chat-dots' => ['消息', '通知', '公告', '提醒', '私信', 'message', 'messages', 'msg', 'notice', 'notices', 'notification', 'notifications', 'announce', 'announcement', 'reminder'],
        'bi-envelope' => ['邮件', '邮箱', '信件', 'mail', 'email', 'emails', 'inbox'],
        'bi-chat-square-text' => ['评论', '回复', '留言', 'comment', 'comments', 'reply', 'replies', 'feedback'],
        'bi-folder' => ['文件', '附件', '资料', '素材', 'file', 'files', 'attach', 'attachment', 'attachments', 'resource', 'resources'],
        'bi-images' => ['图片', '相册', '图库', '照片', 'image', 'images', 'picture', 'pictures', 'photo', 'photos', 'gallery', 'albums', 'album'],
        'bi-camera-video' => ['视频', '影音', '直播', 'video', 'videos', 'media', 'live'],
        'bi-gear' => ['设置', '配置', '参数', 'setting', 'settings', 'config', 'configs', 'configuration', 'option', 'options', 'setup'],
        'bi-cpu' => ['系统', '系统管理', 'system', 'systems', 'sys'],
        'bi-calendar3' => ['日程', '日历', '排课', '课表', '时间', '学期', 'schedule', 'schedules', 'calendar', 'calendars', 'date', 'dates', 'term', 'terms', 'semester'],
        'bi-clock-history' => ['任务', '待办', '工时', '班次', 'task', 'tasks', 'todo', 'job', 'jobs', 'timer'],
        'bi-geo-alt' => ['地址', '区域', '地图', '城市', '省份', '地区', '定位', '位置', 'address', 'addresses', 'area', 'areas', 'region', 'regions', 'map', 'maps', 'city', 'cities', 'province', 'district', 'location', 'positions'],
        'bi-truck' => ['物流', '配送', '运输', '快递', '发货', 'logistics', 'delivery', 'ship', 'shipping', 'transport', 'express', 'freight'],
        'bi-shop' => ['店铺', '门店', '商店', 'shop', 'shops', 'store', 'stores'],
        'bi-house-door' => ['首页', '主页', 'home', 'homes'],
        'bi-box-arrow-in-right' => ['登录', '登陆', '注册', '报名', 'login', 'logins', 'signin', 'signup', 'register', 'registration'],
        'bi-book' => ['帮助', '文档', '手册', '说明', '字典', '词典', '题库', 'help', 'doc', 'docs', 'manual', 'guide', 'guides', 'faq', 'dictionary', 'dict'],
        'bi-search' => ['搜索', '检索', 'search', 'searches'],
        'bi-mortarboard' => ['学生', '学员', '教师', '老师', '讲师', '教授', '毕业', 'school', 'student', 'students', 'pupil', 'pupils', 'scholar', 'teacher', 'teachers', 'tutor', 'tutors', 'professor', 'lecturer', 'instructor', 'graduate'],
        'bi-journal-bookmark' => ['课程', '学科', '课时', '专业', 'course', 'courses', 'subject', 'subjects', 'lesson', 'lessons', 'major', 'majors'],
        'bi-people' => ['班级', '团队', '群组', '小组', 'class', 'classes', 'group', 'groups', 'team', 'teams'],
        'bi-clipboard-data-fill' => ['成绩', '分数', '考试', '测评', '答卷', 'score', 'scores', 'grade', 'grades', 'exam', 'exams', 'result', 'results'],
        'bi-pencil-square' => ['作业', '编辑', '写作', 'homework', 'exercise', 'exercises'],
        'bi-list-check' => ['投票', '问卷', '调查', '清单', 'vote', 'votes', 'survey', 'surveys', 'questionnaire', 'checklist'],
        'bi-diagram-3' => ['流程', '审批', '工作流', '审核', '架构', 'process', 'workflow', 'flow', 'approve', 'approval', 'audit'],
        'bi-star' => ['收藏', '评价', '星级', 'favorite', 'favourite', 'favorites', 'favourites', 'collect', 'star', 'stars', 'review', 'reviews'],
        'bi-hand-thumbs-up' => ['点赞', '好评', 'like', 'likes', 'praise'],
        'bi-cart' => ['购物车', 'cart', 'carts'],
        'bi-ticket' => ['优惠券', '票券', '券', '门票', 'coupon', 'coupons', 'ticket', 'tickets', 'voucher'],
        'bi-megaphone' => ['广告', '推广', '宣传', 'banner', 'advert', 'advertisement', 'ads', 'promotion'],
        'bi-headset' => ['客服', '售后', '工单处理', 'service', 'support', 'kefu'],
        'bi-hdd' => ['备份', '恢复', '归档', 'backup', 'backups', 'restore', 'archive', 'archives'],
        'bi-database' => ['数据库', 'database', 'databases', 'db'],
        'bi-plug' => ['接口', '对接', 'api', 'apis', 'interface', 'interfaces', 'hook'],
        'bi-puzzle' => ['插件', '扩展', '模块', 'plugin', 'plugins', 'extension', 'extensions', 'module', 'modules'],
        'bi-window-dock' => ['模板', '布局', 'template', 'templates', 'layout', 'layouts'],
        'bi-palette' => ['主题', '样式', '皮肤', '颜色', 'theme', 'themes', 'style', 'styles', 'skin', 'color', 'colors'],
        'bi-phone' => ['设备', '手机', '移动端', 'device', 'devices', 'phone', 'phones', 'mobile'],
        'bi-globe' => ['网站', '站点', '网络', '网址', 'site', 'sites', 'website', 'websites', 'web', 'network'],
        'bi-window-sidebar' => ['页面', '前台', 'page', 'pages'],
        'bi-ui-checks' => ['表单', 'form', 'forms'],
        'bi-table' => ['表格', 'table', 'tables'],
        'bi-arrow-left-right' => ['导入', '导出', '同步', '交换', 'import', 'imports', 'export', 'exports', 'sync', 'syncs'],
        'bi-activity' => ['监控', '运维', '告警', '健康', 'monitor', 'monitors', 'ops', 'alert', 'alerts', 'health'],
        'bi-shield-check' => ['安全', '风控', '防护', 'security', 'safety'],
        'bi-bookmark' => ['版本', '收藏集', 'version', 'versions'],
        'bi-briefcase' => ['项目', '业务', '工程', 'project', 'projects', 'business', 'engineering'],
        'bi-stopwatch' => ['考勤', '签到', '打卡', 'attendance', 'checkin'],
        'bi-bell' => ['订阅', '推送', 'subscribe', 'subscription', 'push'],
    ];

    /**
     * 未命中关键词时的兜底池：按控制器名做确定性哈希，保证同名稳定、异名分散
     */
    private const FALLBACK = [
        'bi-grid',
        'bi-collection',
        'bi-layers',
        'bi-clipboard',
        'bi-box',
        'bi-card-list',
        'bi-card-text',
        'bi-list-ul',
        'bi-file-text',
        'bi-graph-up',
        'bi-table',
        'bi-sliders',
        'bi-wrench',
        'bi-terminal',
        'bi-compass',
        'bi-flag',
        'bi-broadcast',
        'bi-funnel',
        'bi-emoji-smile',
        'bi-window',
        'bi-inbox',
        'bi-lightning',
        'bi-device-ssd',
        'bi-ethernet',
    ];

    /**
     * 推荐菜单图标
     *
     * @param string $title      控制器/表格中文名（菜单标题来源）
     * @param string $configName 控制器小写下划线名
     * @param string $explicit   显式指定的图标（-i/--icon），非空即直接采用
     *
     * @return array [图标类样式（如 'bi bi-person'）, 命中来源说明]
     */
    public static function recommend(string $title, string $configName, string $explicit = ''): array
    {
        if (trim($explicit) !== '') {
            return [self::normalize($explicit), '显式指定'];
        }

        $matched = self::matchTitle($title);
        if ($matched !== null) {
            return [$matched[0], '标题关键词「' . $matched[1] . '」'];
        }

        $matched = self::matchRoot($configName);
        if ($matched !== null) {
            return [$matched[0], '控制器词根「' . $matched[1] . '」'];
        }

        if (!empty(self::FALLBACK)) {
            $icon = self::FALLBACK[crc32($configName) % count(self::FALLBACK)];
            return ['bi ' . $icon, '名称哈希兜底池'];
        }

        return [self::DEFAULT_ICON, '默认图标'];
    }

    /**
     * 归一化用户输入的图标：接受 `bi-person` / `bi bi-person` / `person` 三种写法
     */
    public static function normalize(string $icon): string
    {
        $icon = trim($icon);
        $icon = preg_replace('/\s+/', ' ', $icon);

        if (strpos($icon, 'bi ') === 0) {
            return $icon;
        }
        if (strpos($icon, 'bi-') === 0) {
            return 'bi ' . $icon;
        }
        return 'bi bi-' . str_replace(' ', '-', $icon);
    }

    /**
     * 中文标题子串匹配：取命中的最长关键词，避免「用户组」被「用户」抢先
     *
     * @return array null 或 [图标类样式, 命中的关键词]
     */
    private static function matchTitle(string $title): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $best = null;
        foreach (self::keywordMap() as $keyword => $icon) {
            if (strpos($title, $keyword) !== false && ($best === null || strlen($keyword) > strlen($best[0]))) {
                $best = [$keyword, $icon];
            }
        }

        return $best === null ? null : ['bi ' . $best[1], $best[0]];
    }

    /**
     * 控制器蛇形词根精确匹配（按词根在名字中出现的先后顺序）
     *
     * @return array null 或 [图标类样式, 命中的词根]
     */
    private static function matchRoot(string $configName): ?array
    {
        $tokens = preg_split('/[_.\-\s]+/', strtolower(trim($configName)));
        if (!is_array($tokens)) {
            return null;
        }

        $map = self::keywordMap();
        foreach ($tokens as $token) {
            if ($token !== '' && isset($map[$token])) {
                return ['bi ' . $map[$token], $token];
            }
        }

        return null;
    }

    /**
     * 关键词 => 图标 的扁平表（首次构建后缓存）
     */
    private static function keywordMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        foreach (self::ICONS as $icon => $keywords) {
            foreach ($keywords as $keyword) {
                $keyword = strtolower(trim($keyword));
                if ($keyword !== '' && !isset($map[$keyword])) {
                    $map[$keyword] = $icon;
                }
            }
        }

        return $map;
    }
}
